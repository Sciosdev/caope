# Despliegue autónomo de CAOPE

CAOPE despliega `main` desde su consola técnica sin acceso SSH entrante, tokens
de cPanel, rutas del servidor ni secretos de producción en GitHub Actions.

## Instrucción para FESI

FESI sólo realiza estos pasos una vez:

1. En su herramienta de despliegue Git, actualizar el checkout persistente de
   `Sciosdev/caope` en la rama `main`. Deben conservar `backend/.env` y el
   directorio `.git`; `.cpanel.yml` detecta PHP y Composer y ejecuta por sí solo
   las dependencias, migraciones y cachés.
2. Conservar una única ejecución de `php artisan schedule:run` cada minuto. Si
   el scheduler ya funciona, no deben modificarlo.

No se solicita a FESI ningún host, puerto, usuario, llave SSH, ruta, ejecutable,
token de GitHub o token de cPanel.

El checkout debe quedar en `main`, seguir a `origin/main` y ser escribible por
el mismo usuario que ejecuta el scheduler. CAOPE comprueba estas condiciones en
la consola y bloquea el botón si alguna falta.

## Activación desde el navegador

Después de que FESI confirme el último despliegue manual:

1. Iniciar sesión en producción con una cuenta activa que tenga rol `admin`.
2. Abrir `/caope/desarrollo/activar` o elegir **Activar despliegues** en el menú
   de la cuenta.
3. Confirmar nuevamente la contraseña cuando CAOPE lo solicite.
4. Crear un fine-grained personal access token de GitHub:
   - propietario: `Sciosdev`;
   - repositorio seleccionado: `caope`;
   - permiso **Actions: Read and write**;
   - **Metadata: Read** se agrega automáticamente.
5. Pegar el token, elegir la cuenta que usará la consola y pulsar
   **Validar y activar**.
6. En `/caope/desarrollo`, actualizar las comprobaciones. No se debe desplegar
   mientras exista algún indicador rojo.

CAOPE comprueba los permisos del token antes de guardarlo. El valor se cifra
con `APP_KEY` en `backend/storage/app/developer-console/settings.enc`, nunca se
muestra de nuevo y no se almacena en la tabla de parámetros.

Si el token vence, se reemplaza en **Renovar token de GitHub**, dentro de la
misma consola. Si el archivo cifrado se daña o cambia `APP_KEY`, un administrador
puede recuperar la activación desde `/caope/desarrollo/activar` sin solicitar
acceso al servidor.

## Despliegues posteriores

1. Integrar el cambio aprobado en `main`.
2. Entrar a `/caope/desarrollo`.
3. Escribir `DESPLEGAR`.
4. Esperar a que el historial muestre **Completado** y las comprobaciones sigan
   sin errores.

El workflow:

1. fija el commit exacto asociado a la ejecución;
2. instala dependencias y ejecuta la compuerta de pruebas;
3. valida Blade y los assets compilados;
4. llama al endpoint de producción con el SHA, la solicitud auditada y el ID de
   la ejecución;
5. CAOPE consulta GitHub y acredita que el workflow, rama, SHA, intento, título
   y pruebas coinciden con la solicitud local;
6. el scheduler ejecuta el despliegue dentro del propio servidor;
7. genera un respaldo de base de datos, activa mantenimiento, avanza `main` sólo
   mediante fast-forward, instala Composer, ejecuta migraciones, reconstruye
   cachés, reinicia colas y vuelve a levantar la aplicación;
8. GitHub comprueba el SHA publicado y `/caope/up`.

Producción rechaza el despliegue si contiene cambios locales en archivos
versionados. Nunca ejecuta seeders, `migrate:fresh`, `reset --hard` ni comandos
proporcionados desde el navegador.

## Seguridad del callback de producción

El endpoint `/api/deployment/prepare` no confía en los datos enviados por el
navegador ni en un secreto compartido. Antes de crear una autorización de 30
minutos, exige:

- una solicitud UUID activa y reciente registrada por `/desarrollo`;
- el workflow `deploy.yml` exacto de `Sciosdev/caope`;
- evento `workflow_dispatch`, rama `main` y SHA exacto;
- número de intento y título correspondientes a la solicitud;
- job **Validate release** terminado correctamente;
- job **Deploy to production** actualmente en ejecución.

El marcador autorizado se elimina al terminar o fallar el intento. El registro
técnico queda en `backend/storage/logs/developer-deploy.log`.

## Ambiente de pruebas existente

`https://caope.ayudafesi.com` conserva temporalmente el bearer configurado en
`DEVELOPER_CONSOLE_DEPLOY_WEBHOOK_TOKEN` para no interrumpir su workflow actual.
El mismo script genérico detecta la raíz del repositorio y recibe el PHP que ya
ejecuta el scheduler; no contiene rutas particulares de cPanel.
El nombre anterior `scripts/deploy-cpanel-staging.sh` se conserva como alias para
que una ruta privada ya guardada en `.env` continúe funcionando sin editarla.
Los cambios versionados existentes se muestran como advertencia en pruebas, pero
siguen siendo un error bloqueante en la activación segura de producción.

## Requisitos que muestra la consola

La consola comprueba automáticamente:

- versión y extensiones de PHP;
- base de datos y migraciones;
- caché y almacenamiento privado;
- assets compilados;
- colas y scheduler;
- respaldo reciente;
- `/bin/bash`, `proc_open`, Git, PHP y el script de despliegue;
- credenciales de GitHub.

El botón de despliegue queda deshabilitado si alguna comprobación está en rojo.

## Recuperación de una falla

El script usa un candado para impedir despliegues concurrentes. Si falla después
de activar mantenimiento, intenta ejecutar `artisan up` antes de salir. No
sobrescribe cambios locales ni fuerza el historial Git.

Para diagnosticar desde el navegador:

1. abrir `/caope/desarrollo`;
2. revisar la comprobación roja;
3. abrir **Ver en GitHub** en el historial;
4. si la actualización alcanzó al servidor, consultar
   `backend/storage/logs/developer-deploy.log` mediante el mecanismo de registros
   que FESI ya proporcione.
