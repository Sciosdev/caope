# Despliegue en Subdirectorio

Esta guía explica cómo configurar CAOPE para ejecutarse en un subdirectorio, por ejemplo: `hola.com/caope` en lugar de `hola.com`.

## Requisitos previos

- Nginx, Apache u otro servidor web configurado (no usar `php artisan serve` en producción para subdirectorios)
- Acceso para modificar configuración del servidor

## Cambios en Laravel

### 1. Configurar `APP_URL` en `.env`

```dotenv
APP_URL=http://hola.com/caope
```

Reemplaza `hola.com/caope` con tu dominio y subdirectorio real.

### 2. Configurar la ruta de sesiones

Edita `backend/.env`:

```dotenv
SESSION_PATH=/caope
```

Esto asegura que las cookies se almacenen correctamente en el subdirectorio.

### 3. Actualizar la ruta pública en `public/index.php` (si es necesario)

**Nota:** En Laravel 12, el punto de entrada normalmente maneja esto automáticamente. Pero si usas un subdirectorio, verifica que:

```php
// backend/public/index.php
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
// Laravel maneja automáticamente las rutas basadas en APP_URL
```

### 4. Vite & Assets

El `laravel-vite-plugin` detecta automáticamente `APP_URL` y genera las rutas de assets correctamente.

Si necesitas forzar una ruta de build específica, edita `backend/vite.config.js`:

```javascript
export default defineConfig({
    plugins: [
        laravel({
            buildDirectory: 'assets/build',
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/expedientes/anexos.js'],
            refresh: true,
            // Opcional: fuerza la ruta base si lo necesitas
            // base: '/caope/',
        }),
    ],
    // ...
});
```

## Configuración del Servidor Web

### Nginx

Crea una configuración de servidor virtual en `/etc/nginx/sites-available/caope.conf`:

```nginx
server {
    listen 80;
    server_name hola.com www.hola.com;

    root /var/www/caope/backend/public;
    index index.php;

    location /caope {
        alias /var/www/caope/backend/public;
        try_files $uri $uri/ /caope/index.php?$query_string;

        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /var/www/caope/backend/public/index.php;
            include fastcgi_params;
        }
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fasturi;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Habilita el sitio:

```bash
sudo ln -s /etc/nginx/sites-available/caope.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Apache

Crea `.htaccess` en `backend/public/`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /caope/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ /caope/index.php?/$1 [L]
</IfModule>
```

O configura el VirtualHost:

```apache
<VirtualHost *:80>
    ServerName hola.com
    ServerAlias www.hola.com
    DocumentRoot /var/www/caope

    Alias /caope /var/www/caope/backend/public

    <Directory /var/www/caope/backend/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteBase /caope/
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^(.*)$ index.php?/$1 [L]
        </IfModule>
    </Directory>
</VirtualHost>
```

## Despliegue paso a paso

### 1. Clonar y preparar el proyecto

```bash
cd /var/www
git clone https://github.com/Sciosdev/caope.git
cd caope/backend
```

### 2. Instalar dependencias

```bash
composer install
npm install
```

### 3. Configurar ambiente

```bash
cp .env.example .env
php artisan key:generate

# Edita .env y asegúrate de que esté:
# APP_URL=http://hola.com/caope
# SESSION_PATH=/caope
```

### 4. Ejecutar migraciones

```bash
php artisan migrate --seed
php artisan storage:link
```

### 5. Compilar assets

```bash
npm run build
```

### 6. Permisos

```bash
sudo chown -R www-data:www-data /var/www/caope
sudo chmod -R 755 /var/www/caope
sudo chmod -R 775 /var/www/caope/backend/storage
sudo chmod -R 775 /var/www/caope/backend/bootstrap/cache
```

### 7. Reiniciar el servidor web

```bash
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
# o para Apache:
sudo systemctl restart apache2
```

## Verificación

Accede a `http://hola.com/caope` en tu navegador. Deberías ver:

- ✅ Login sin errores de rutas
- ✅ Assets (CSS/JS) cargando correctamente
- ✅ Rutas internas funcionando (`/caope/dashboard`, `/caope/expedientes`, etc.)
- ✅ Imágenes visibles (SDRI logo, etc.)

Si ves errores 404 en assets, revisa:

```bash
# Assets compilados en la ruta correcta
ls -la backend/public/assets/build/

# Archivo de manifiesto
cat backend/public/assets/build/manifest.json
```

## Problemas comunes

### "No se cargan los assets"

**Causa:** Las rutas de assets no incluyen el subdirectorio.

**Solución:**
1. Verifica `APP_URL` en `.env`
2. Ejecuta `npm run build` nuevamente
3. Limpia la caché: `php artisan cache:clear`

### "Las cookies no se guardan"

**Causa:** `SESSION_PATH` no coincide con el subdirectorio.

**Solución:**
```dotenv
SESSION_PATH=/caope
```

### "Redirecciones inefectivas"

**Causa:** Las rutas generadas por `route()` o `redirect()` no incluyen la base.

**Solución:** Verifica que `APP_URL` sea exacto. Laravel detecta automáticamente la base de URL.

### "Errores 404 en todas las rutas"

**Causa:** El servidor web no está configurado para el subdirectorio.

**Solución:** 
- Revisa la configuración de `RewriteBase` (Apache) o `location` (Nginx)
- Asegúrate de que `index.php` sea la entrada correcta

## Development local (opcional)

Si deseas probar localmente en un subdirectorio sin cambiar `APP_URL`, puedes usar PHP con subdirectorio:

```bash
cd backend

# Crear un alias local
php artisan serve --host 127.0.0.1 --port 8000

# Acceder con: http://127.0.0.1:8000/
# (Las rutas internas usan / como base local)
```

Para una prueba más realista con subdirectorio local, usa Nginx/Apache localmente con la misma config que producción.

## Referencias

- [Laravel Configuration - APP_URL](https://laravel.com/docs/12.x/configuration#application-url)
- [Session Configuration](https://laravel.com/docs/12.x/session)
- [Vite Asset Handling](https://laravel.com/docs/12.x/vite)
- [Nginx Subdirectory Deployment](https://nginx.org/en/docs/http/ngx_http_core_module.html#alias)
- [Apache URL Rewriting](https://httpd.apache.org/docs/current/mod/mod_rewrite.html)
