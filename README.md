# CAOPE – Sistema de Seguimiento de Expedientes (monorepo)

## Estructura
- `backend/`: Aplicación Laravel 12 con SQLite para el MVP académico-clínico.
- `public-assets/`: assets NobleUI listos para publicarse en Laravel (`public/assets`).
- `_template/`: demos, documentación y SCSS originales del tema (solo referencia).
- `docs/`: documentación funcional, de planeación y operación (`docs/blueprint.md`, `docs/environments.md`).
- `preview.html`: mock estático para validar estilos sin correr Laravel.

## Estado actual
- Laravel 12 con autenticación base pendiente, pero con módulo de **Expedientes** leyendo datos ficticios desde SQLite.
- Layout NobleUI integrado en Blade y tablas con DataTables + filtros básicos.
- Seeder que genera 80 expedientes de ejemplo (`php artisan migrate --seed`).
- Entorno local listo con `php artisan serve`.

## Convenciones de ramas
- **main**: rama estable para releases y despliegues a producción.
- **develop**: base por defecto para Pull Requests y trabajo continuo.
- **feature/<tarea>**: ramas derivadas desde `develop` para nuevas funcionalidades o fixes concretos. Reemplaza `<tarea>` por el identificador corto de la actividad (por ejemplo, `feature/123-formulario-intake`).

Antes de abrir un PR, asegúrate de que apunte a `develop` salvo que se especifique lo contrario.

## Política de protección de ramas

Configura la protección de ramas directamente en la interfaz de GitHub siguiendo estos lineamientos:

1. **Ramas protegidas**: aplica la política sobre la rama estable de trabajo (`develop` o `main`, según corresponda).
2. **Checks obligatorios**: marca como requisitos los jobs de CI `lint`, `test` y `build` para poder completar el merge.
3. **Revisión obligatoria**: habilita la casilla de “Require a pull request before merging” e impide merges sin al menos una revisión aprobatoria.
4. **Pushes restringidos**: bloquea los `force push` y los pushes directos marcando “Restrict who can push to matching branches” o “Do not allow bypassing the above settings”.
5. **Sin fusiones directas**: fuerza que todos los cambios ingresen por Pull Request activando “Allow force pushes” en estado *deshabilitado*.

Documenta cualquier excepción temporal en el handbook interno y levanta un recordatorio para revertirla una vez atendido el incidente.

## Pasos rápidos para desarrollar
1. `cd backend`
2. `composer install && npm install`
3. Copiar `.env.example` → `.env` (hay uno de referencia en la raíz) y ajustar `DB_CONNECTION=sqlite`.
4. `php artisan key:generate`
5. `php artisan migrate --seed`
6. `php artisan storage:link` (expone `storage/app/public` en `public/storage`)
7. Verifica que `storage/app/private` exista con permisos restringidos para el proceso de PHP.
8. `php artisan serve`

> Nota: configura `FILESYSTEM_DISK` y `FILESYSTEM_DISK_PRIVATE` en `.env` según el entorno. En código, utiliza `config('filesystems.private_default')` para obtener el disco privado activo.

> Opcional: `npm run dev` si deseas recompilar assets Vite propios. NobleUI ya está publicado en `public/assets`.

## Validaciones CI locales

Sigue estos pasos para replicar los jobs del workflow de GitHub Actions antes de subir cambios:

### Lint (`lint`)
1. `cd backend`
2. Instala Pint de forma global (una sola vez es suficiente): `composer global require laravel/pint`
3. Ejecuta la verificación de estilo: `pint --test`

### Pruebas (`test`)
1. `cd backend`
2. Asegúrate de que exista `database/database.sqlite` (puedes crearlo con `touch database/database.sqlite` si falta).
3. Copia `.env.example` a `.env` y genera la llave de la aplicación: `cp .env.example .env && php artisan key:generate`
4. Ejecuta las migraciones (con semillas si es necesario): `php artisan migrate --seed`
5. Lanza la prueba rápida de humo: `php artisan test --testsuite=Feature`
6. Ejecuta el validador de Blade: `php artisan blade:validate`

### Build (`build`)
1. `cd backend`
2. Instala dependencias de Composer si no lo has hecho: `composer install`
3. Si existe `package.json`, instala dependencias de Node y construye los assets: `npm ci && npm run build`
4. Si no existe `package.json`, ejecuta un chequeo rápido de rutas: `php artisan route:list`

### Docker Compose (opcional)

También puedes levantar el entorno local con Docker usando SQLite por defecto:

```bash
touch backend/database/database.sqlite
cp backend/.env.example backend/.env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

El servicio queda disponible en <http://localhost:8000>. Consulta `docs/environments.md` para conocer los requisitos y la operativa de cada entorno (local, staging y producción).

## Próximos módulos (según blueprint)
- Roles/Permisos (Alumno, Docente, Coordinación, Admin).
- Flujo completo de expedientes (secciones, autosave, timeline y cierre).
- Consentimiento informado y control de revisiones.
- Registro de sesiones/atenciones con anexos por expediente.
- Reportes iniciales de estados, alertas y actividad.

Revisa `docs/blueprint.md` para el detalle funcional completo y próximos entregables.
