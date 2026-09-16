# Despliegue autónomo de CAOPE

CAOPE despliega `main` desde su consola técnica sin acceso SSH entrante, tokens
de cPanel, rutas del servidor ni secretos de producción en GitHub Actions.

En servidores Debian administrados por FESI se instala una sola vez el agente
sin privilegios descrito en [production-agent.md](production-agent.md). Después
de esa instalación no deben conservarse cron de CAOPE bajo `root`.

## Instrucción única para FESI

FESI realiza estos pasos, en este orden, durante la primera instalación segura:

1. **Antes de actualizar Git**, conservar `backend/.env` y revisar localmente
   estas variables, sin compartir sus valores:

   ```ini
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://xocoyotzin.iztacala.unam.mx/caope
   APP_KEY=<clave nueva y exclusiva de 32 bytes>
   APP_PREVIOUS_KEYS=
   SESSION_DRIVER=database
   SESSION_ENCRYPT=true
   SESSION_SECURE_COOKIE=true
   DB_DUMP_SKIP_SSL=true
   BACKUP_ARCHIVE_PASSWORD=<secreto aleatorio independiente de 32 o más caracteres>
   ```

   `TRUSTED_HOSTS` puede omitirse para usar la lista segura incluida. Si ya
   existe, sólo puede contener `caope.ayudafesi.com` y
   `xocoyotzin.iztacala.unam.mx`. La nueva `APP_KEY` y la contraseña de respaldo
   deben generarse fuera del repositorio y guardarse en el gestor de secretos de
   FESI. No se debe copiar una clave de `.env.testing`, commits o documentación.
   `DB_DUMP_SKIP_SSL=true` sólo desactiva TLS para el proceso de respaldo porque
   el servidor MySQL institucional no lo ofrece; no cambia la conexión normal de
   CAOPE. El proceso fuerza el indicador `skip-ssl`, compatible con el cliente
   MariaDB que Debian proporciona como `mysqldump`.
2. En Debian de producción, actualizar el checkout persistente de
   `Sciosdev/caope` en `main`, conservando `backend/.env` y `.git`.
3. Ejecutar una sola vez, como `root`,
   `scripts/install-production-agent.sh`, siguiendo el bloque exacto de
   [production-agent.md](production-agent.md). El instalador crea el usuario
   técnico, scheduler y trabajador de colas; elimina únicamente los cron
   anteriores de este checkout y ejecuta el bootstrap final. No se configura
   manualmente otro cron.

El servidor de pruebas en cPanel conserva su mecanismo propio: `.cpanel.yml`
detecta PHP y Composer y ejecuta dependencias, respaldo, migraciones y cachés.

No se solicita a FESI que comparta host, puerto, usuario, llave SSH, rutas,
ejecutables, `APP_KEY`, contraseñas ni tokens de GitHub/cPanel.

Rotar `APP_KEY` cierra las sesiones existentes y hace necesario recuperar la
activación de `/caope/desarrollo`; no borra expedientes ni archivos clínicos.

`php artisan caope:security-audit --profile=production` comprueba esas
condiciones sin imprimir sus valores. Los scripts ejecutan la misma auditoría
antes de crear el respaldo y antes de activar mantenimiento. En despliegues
posteriores, un fallo ocurre antes de `git fetch` y no modifica la aplicación.
En el primer despliegue de cPanel, el checkout ya fue actualizado antes de que
`.cpanel.yml` se ejecute: si la auditoría falla, no se toca la base de datos ni
se activa mantenimiento; se corrige `backend/.env` y se vuelve a pulsar
**Deploy HEAD Commit** para completar cachés y migraciones.

Los scripts usan `--profile=auto` si no reciben una selección explícita: sólo
adoptan el perfil transitorio de pruebas cuando `APP_ENV` es exactamente
`staging`; cualquier otro valor se audita como producción. El scheduler envía
el perfil de forma explícita según la modalidad activada en la consola.

El checkout debe quedar en `main`, seguir a `origin/main` y pertenecer al agente
`caope-deploy` instalado una sola vez. CAOPE comprueba estas condiciones en la
consola y bloquea el botón si alguna falta.

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

La consola actualiza el progreso cada 15 segundos durante un intento y cada
minuto cuando está inactiva. Conserva la confirmación y el token que se estén
escribiendo. Muestra por separado la versión instalada, la solicitada, las
etapas del agente y el resultado de GitHub. Una interrupción o una recuperación
incompleta siguen bloqueando nuevos despliegues hasta comprobar el resultado;
la antigüedad de un intento por sí sola no demuestra que haya terminado.

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
versionados. Nunca ejecuta seeders, `migrate:fresh` ni comandos proporcionados
desde el navegador. El único `reset --hard` permitido está encapsulado en el
rollback automático: sólo puede volver al SHA local conocido que estaba activo
antes de que el propio agente avanzara un checkout limpio.

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

## Protección y retención de respaldos

El scheduler genera el respaldo cifrado a las 02:00, aplica la política de
retención a las 03:00 y verifica su salud a las 04:00. El archivo conserva la
base de datos y los documentos clínicos privados, pero excluye `.env`, la caché
de configuración, logs, credenciales del desarrollador, exportaciones
temporales y respaldos anteriores. Las notificaciones por correo permanecen
desactivadas salvo que se configure `BACKUP_NOTIFICATION_EMAIL`.

## Ambiente de pruebas existente

`https://caope.ayudafesi.com` conserva temporalmente el bearer configurado en
`DEVELOPER_CONSOLE_DEPLOY_WEBHOOK_TOKEN` para no interrumpir su workflow actual.
El mismo script genérico detecta la raíz del repositorio y recibe el PHP que ya
ejecuta el scheduler; no contiene rutas particulares de cPanel.
El nombre anterior `scripts/deploy-cpanel-staging.sh` se conserva como alias para
que una ruta privada ya guardada en `.env` continúe funcionando sin editarla.
Los cambios versionados existentes se muestran como advertencia en pruebas, pero
siguen siendo un error bloqueante en la activación segura de producción.

Durante la transición, pruebas usa el perfil `staging`: permite
`APP_ENV=staging` con una advertencia, pero exige igualmente depuración
desactivada, `APP_URL=https://caope.ayudafesi.com`, una `APP_KEY` nueva,
`APP_PREVIOUS_KEYS` vacío, sesiones cifradas en base de datos, cookie segura y contraseña de respaldo. Estos valores
se agregan a `backend/.env` antes de la actualización que instala este bloque.
La ausencia de cualquiera deshabilita el botón y bloquea la solicitud antes de
registrar o enviar un despliegue; la auditoría nunca muestra el valor
configurado.

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

## Validación de cambios en el agente

Las pruebas automatizadas del agente comprueban su lógica y simulan fallos.
Los escenarios de shell usan Git, bloqueos y señales reales, pero sustituyen
Composer y las órdenes Artisan. Un CI aprobado no sustituye un ensayo completo
del despliegue ni confirma que un respaldo del servidor pueda restaurarse.

Antes de publicar una modificación del agente:

1. Preparar una máquina de pruebas independiente, con una copia obtenida de Git,
   datos ficticios y servicios y permisos equivalentes al servidor. No montar
   carpetas del equipo ni proporcionar credenciales de producción.
2. Probar la actualización desde la versión instalada, ejecutando Composer,
   migraciones, scheduler y trabajador de colas reales. La primera actualización
   comienza con el script antiguo y debe ensayarse también.
3. Provocar una interrupción durante dependencias y otra después de migrar.
   Comprobar que la versión anterior funciona con el esquema resultante y que
   conserva los registros de prueba. Si la recuperación falla, debe mantener
   la aplicación en mantenimiento.
4. Restaurar el respaldo en una base de pruebas separada y comprobar sus datos.
   Registrar el SHA ensayado, los resultados y los tiempos antes de autorizar
   la publicación. El chequeo `/up` no acredita estos resultados.

Los nuevos escenarios de shell conservan sus archivos temporales para revisión;
la plataforma de CI descarta el runner. No ejecutan limpieza recursiva automática.

## Recuperación de una falla

El script usa `flock` para impedir despliegues concurrentes, incluso cuando se
limpia la caché Laravel. El sistema operativo libera ese bloqueo cuando termina
su propietario. El directorio anterior `.deploy-lock` se conserva como protección
frente al bootstrap: sólo se recupera automáticamente si su propietario y su
terminación pueden comprobarse. Un bloqueo legado sin propietario verificable
se conserva y se muestra en el diagnóstico.

Antes de activar mantenimiento se guarda un diario privado en
`backend/storage/app/deployment/recovery.json`. Si producción falla después de
avanzar el checkout, vuelve al SHA anterior, reinstala el lock anterior de
Composer y reconstruye las cachés. Sólo levanta la aplicación después de verificar
que la recuperación terminó. Este
rollback de código no deshace migraciones ya confirmadas; por eso las migraciones
de CAOPE deben conservar compatibilidad hacia atrás. El agente nunca inicia el
despliegue si detecta cambios locales versionados.

Si se interrumpe el proceso, el siguiente ciclo del scheduler existente
ejecuta la recuperación desde `repair-runtime.sh`, antes de iniciar Laravel.
Así puede reparar un directorio `vendor` incompleto. No necesita que el marcador
de autorización siga vigente. `result.json` conserva el resultado y permite
importarlo al historial cuando Laravel vuelve a funcionar. Los archivos sólo
contienen identificadores, estados y revisiones, nunca tokens.

Mientras exista un diario pendiente, el servicio de colas pospone su arranque
y reintenta con su política existente. Así su límite de inicio no interrumpe
una restauración larga. Cada ejecución comprueba bajo bloqueo que conserva la
misma solicitud autorizada antes de instalarla y registrar su progreso.

El intento dispone de 30 minutos y hasta seis minutos para la salida y
recuperación. Laravel supervisa el proceso con un margen de 37 minutos y medio,
inferior a los 40 minutos del servicio instalado. GitHub espera hasta 45 minutos.
Una salida sin confirmación positiva no se anuncia como instalación correcta.

La recuperación requiere el modo de mantenimiento por archivo:
`APP_MAINTENANCE_DRIVER=file` (valor predeterminado). Se auditan tanto la
configuración activa como la siguiente configuración antes de modificar el
checkout. No se sustituye un mantenimiento que ya estuviera activo.

Estas capacidades quedan disponibles después de instalar esta versión con
éxito. Con el agente documentado ya instalado no hace falta reinstalar unidades
systemd ni conceder permisos adicionales. La actualización inicial todavía la
ejecuta el script de la versión que esté instalada en ese momento.

Para diagnosticar desde el navegador:

1. abrir `/caope/desarrollo`;
2. revisar la comprobación roja;
3. abrir **Ver en GitHub** en el historial;
4. revisar en el historial la etapa, la última señal del agente y el diagnóstico
   de recuperación. Para un análisis técnico adicional se conserva
   `backend/storage/logs/developer-deploy.log`.

**Colas** sólo confirma actividad cuando observa un trabajador reciente de la
conexión y cola configuradas; mientras el trabajador carga por primera vez esta
versión puede mostrar una advertencia. **GitHub** comprueba lectura del workflow,
con una caché de un minuto; el permiso de ejecutar se valida al activar o renovar
credenciales. Una comprobación de lectura no se presenta como prueba de escritura.
