# Despliegue controlado y consola del desarrollador

CAOPE utiliza un despliegue manual y auditado. La consola técnica solicita el
despliegue, GitHub Actions valida la versión y el servidor sólo acepta la rama
`main`. La aplicación web no ejecuta comandos de shell ni recibe una terminal.

## Flujo

1. Un usuario con rol `developer` confirma nuevamente su contraseña.
2. La consola registra la solicitud y llama a `workflow_dispatch` de GitHub.
3. GitHub instala dependencias, ejecuta la compuerta de pruebas de despliegue,
   valida Blade y compila los assets.
4. El job protegido `production` espera aprobación si el entorno de GitHub la
   tiene configurada.
5. GitHub se conecta por SSH, comprueba que el checkout esté limpio y que el
   commit remoto sea exactamente el que se validó.
6. El servidor genera un respaldo de la base de datos, activa mantenimiento,
   actualiza el código, instala dependencias y ejecuta las migraciones.
7. Se regeneran las cachés, se reinician las colas y se consulta `/up`.

Los despliegues no ejecutan seeders y nunca usan `migrate:fresh` en producción.

> **Deuda conocida:** la suite completa contiene actualmente fallos heredados
> ajenos a este módulo. Para no bloquear todos los despliegues, la compuerta
> ejecuta de forma obligatoria las pruebas unitarias, de autenticación, de
> seguridad y de la consola técnica. El CI general continúa mostrando los
> fallos restantes; cuando su línea base quede reparada, `deploy.yml` debe
> volver a ejecutar `php artisan test` sin filtros.

## Configuración única a cargo de FESI

### Servidor

- PHP 8.2 o posterior, Composer, Git, `curl` y la herramienta de dump de la
  base de datos deben estar disponibles para el usuario de despliegue.
- El repositorio debe estar clonado en una ruta dedicada. Esa ruta debe
  contener `.git/` y `backend/artisan`.
- El checkout de producción debe permanecer en `main` y no debe contener
  modificaciones locales en archivos versionados.
- El usuario SSH debe poder escribir en el checkout, `backend/storage` y
  `backend/bootstrap/cache`.
- La llave SSH debe ser exclusiva para GitHub Actions. No se deben reutilizar
  llaves personales.
- El cron de Laravel debe ejecutar cada minuto:

  ```cron
  * * * * * cd /ruta/caope/backend && /ruta/php artisan schedule:run >> /dev/null 2>&1
  ```

Antes de habilitar despliegues, FESI debe comprobar manualmente que funciona:

```bash
cd /ruta/caope/backend
/ruta/php artisan backup:run --only-db --no-interaction
/ruta/php artisan migrate:status
/ruta/php artisan about --only=environment
```

### Entorno `production` de GitHub

Crear el entorno **production** en Settings → Environments. Se recomienda
habilitar revisores obligatorios y restringirlo a la rama `main`.

Agregar estos secretos al entorno:

| Secreto | Uso |
| --- | --- |
| `DEPLOY_HOST` | Host o IP SSH del servidor. |
| `DEPLOY_USER` | Usuario SSH exclusivo para despliegue. |
| `DEPLOY_SSH_KEY` | Llave privada autorizada por FESI. |
| `DEPLOY_PATH` | Ruta absoluta de la raíz del checkout de CAOPE. |
| `DEPLOY_PORT` | Puerto SSH; opcional, por defecto 22. |
| `DEPLOY_PHP_BIN` | Ruta absoluta de PHP; opcional si `php` está en PATH. |
| `DEPLOY_COMPOSER_BIN` | Ruta absoluta de Composer; opcional si está en PATH. |
| `DEPLOY_HEALTH_URL` | URL base pública, incluyendo el subdirectorio si existe. |

`DEPLOY_PATH` nunca debe ser `/`, el home completo del usuario ni una ruta que
contenga otros proyectos.

### Variables del servidor

Agregar al `.env` de `backend`:

```dotenv
DEVELOPER_CONSOLE_ENABLED=true
DEVELOPER_CONSOLE_ALLOWED_IPS=
DEVELOPER_CONSOLE_PASSWORD_TIMEOUT=900
DEVELOPER_CONSOLE_GITHUB_API_URL=https://api.github.com
DEVELOPER_CONSOLE_GITHUB_REPOSITORY=Sciosdev/caope
DEVELOPER_CONSOLE_GITHUB_WORKFLOW=deploy.yml
DEVELOPER_CONSOLE_GITHUB_REF=main
DEVELOPER_CONSOLE_GITHUB_TOKEN=github_pat_REEMPLAZAR
```

El token debe ser fine-grained, estar limitado únicamente a `Sciosdev/caope`
y contar con **Metadata: Read** y **Actions: Read and write**. No necesita
permisos para modificar código. Debe guardarse exclusivamente en `.env`,
rotarse periódicamente y revocarse si se sospecha una exposición.

`DEVELOPER_CONSOLE_ALLOWED_IPS` acepta direcciones exactas separadas por coma.
Cuando se deja vacío, cualquier IP puede entrar, pero todavía se exige usuario,
rol técnico y confirmación reciente de contraseña.

Después de editar `.env`:

```bash
cd /ruta/caope/backend
/ruta/php artisan migrate --force
/ruta/php artisan config:cache
```

### Conceder acceso técnico

El rol `developer` no aparece en la administración de usuarios y no puede
asignarse desde el navegador. FESI debe ejecutarlo una sola vez sobre un usuario
existente y activo:

```bash
/ruta/php artisan caope:developer-access desarrollador@ejemplo.com
```

Para retirarlo:

```bash
/ruta/php artisan caope:developer-access desarrollador@ejemplo.com --revoke
```

## Comprobaciones mostradas

La consola revisa PHP y sus extensiones, base de datos, migraciones, caché,
almacenamiento privado, manifiesto de assets, colas, scheduler, respaldos y la
configuración de GitHub. Las pruebas de caché y almacenamiento escriben un valor
temporal y lo eliminan inmediatamente.

El heartbeat del scheduler puede tardar hasta tres minutos en aparecer como
correcto después de configurar el cron.

## Recuperación

Si CAOPE no responde, el workflow se puede iniciar directamente desde la
pestaña Actions de GitHub. La consola es una interfaz conveniente, no el único
mecanismo de recuperación.

El workflow siempre intenta sacar Laravel del modo mantenimiento al terminar,
incluso cuando falla. No realiza rollback automático de migraciones. Ante una
falla posterior a una migración se debe conservar el respaldo y seguir
[`restore-runbook.md`](restore-runbook.md).
