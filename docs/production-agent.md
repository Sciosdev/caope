# Agente autónomo de producción CAOPE

Esta instalación se realiza una sola vez por un administrador del servidor. Su
objetivo es que los despliegues y el mantenimiento ordinario de CAOPE no vuelvan
a requerir sesiones `root`.

## Qué instala

- Usuario de sistema `caope-deploy`, sin contraseña, shell ni privilegios `root`.
- Timer `caope-scheduler.timer` para tareas programadas y despliegues.
- Servicio `caope-queue.service` para la cola de base de datos.
- Grupo compartido `www-data` y permisos `setgid` para los datos de ejecución.
- Reparación preventiva de las rutas Laravel antes de cada ciclo del agente.
- Rollback automático al commit anterior si una actualización de producción
  falla después de avanzar el checkout.
- Caché, bloqueos, sesiones y cola persistentes en MySQL.
- Retiro de los cron de `root` que ejecutaban `schedule:run` o `queue:work` para
  este checkout. El crontab anterior queda respaldado bajo `/root`.

El agente puede actualizar únicamente el checkout de CAOPE y sus archivos de
ejecución. No obtiene `sudo`, no acepta comandos arbitrarios desde formularios
y no puede administrar el sistema operativo.

## Única intervención del administrador

Ejecutar como `root`, desde el checkout existente:

```bash
cd /var/www/htmlcaope/caope
git -c safe.directory="$PWD" fetch --no-tags origin main
git -c safe.directory="$PWD" merge --ff-only refs/remotes/origin/main
/bin/bash scripts/install-production-agent.sh
```

El último bloque debe mostrar:

```text
AGENTE_CAOPE=LISTO
SCHEDULER=ACTIVO
COLA=ACTIVA
CACHE=DATABASE
```

El instalador es idempotente: puede repetirse sin crear usuarios o servicios
duplicados. No imprime ni reemplaza `APP_KEY`, credenciales MySQL, tokens de
GitHub o la contraseña de los respaldos. Si GitHub/Composer responde con un
error temporal, el bootstrap se reintenta hasta tres veces y mantiene la
aplicación levantada entre intentos.

## Comprobación sin modificar el servidor

```bash
systemctl status caope-scheduler.timer caope-queue.service --no-pager
systemctl list-timers caope-scheduler.timer --no-pager
```

Después se debe abrir `/caope/desarrollo`, actualizar las comprobaciones y
realizar un despliegue normal. El checkout, Composer, Artisan, scheduler y cola
se ejecutarán como `caope-deploy`; Apache continuará ejecutándose como
`www-data`.

Si un despliegue posterior falla después de avanzar `main`, el agente intenta
restaurar automáticamente el commit y las dependencias anteriores antes de
levantar nuevamente la aplicación. El motivo técnico queda en el historial de
la consola y en `backend/storage/logs/developer-deploy.log`.

## Alcance real de la autonomía

La consola puede resolver despliegues, caché, colas, permisos internos,
marcadores y tareas Laravel sin acceso del administrador. Continúan fuera de su
alcance las averías del sistema operativo, Apache, MySQL, certificados, red,
disco o hardware. Dar esas facultades a una aplicación web exigiría acceso root
remoto y convertiría la consola en una puerta trasera.
