# Asignación de expedientes y ajustes de consultorios

## Cambios incluidos en la publicación

- `facilitador_id` identifica a la cuenta responsable; `creado_por` conserva
  quién capturó el expediente. Administración, PAPS aprobado y el coordinador
  asignado pueden reasignar al facilitador desde Editar expediente.
- Las consultas, políticas, sesiones, notificaciones, tablas, reportes y XLS
  usan la cuenta asignada. La reasignación retira el acceso anterior.
- La migración conserva inicialmente la asignación basada en el creador. No
  vincula cuentas por nombres libres, que pueden estar repetidos. Los
  expedientes capturados por administración con un facilitador escrito solo
  como nombre necesitan seleccionar explícitamente su cuenta.
- PAPS aprobado puede abrir Solicitar edición en la bitácora, incluidas las
  reservas creadas desde expediente, proponer horario/cubículo y ver errores
  de validación. Administración conserva la aprobación; los cambios pendientes
  no modifican la reserva.

## Coordinación por estrategias

Se añadió una relación explícita entre coordinadores y estrategias, y
`estrategia_id` en expedientes. El acceso de coordinación depende únicamente de
esa clasificación y sus membresías. `coordinador_id` permanece como contacto de
seguimiento; no concede acceso por sí solo. La restricción también cubre roles
combinados, sesiones, anexos, reportes, métricas, exportaciones y reservas.

Para poner en uso la configuración después de publicar y migrar:

1. En **Usuarios → Editar → Estrategias que coordina**, administración o PAPS
   aprobado selecciona las estrategias de cada coordinador.
2. En **Expedientes → Editar → Estrategia del expediente**, administración o
   PAPS aprobado clasifica los registros existentes. El listado muestra los
   que todavía están **Sin clasificar**.
3. Quitar una membresía retira el acceso inmediatamente y cierra las sesiones
   de la cuenta modificada. Los coordinadores no pueden modificar sus propias
   membresías ni mover expedientes entre estrategias.

La migración no infiere clasificaciones ni membresías a partir de nombres o
del contacto histórico. Sin estrategia en el expediente o sin estrategias en
el coordinador, coordinación no tiene acceso. La creación permite seleccionar
una estrategia activa y mantiene consistente la estrategia de la reserva.

Las reservas también conservan una llave estable de estrategia. Renombrar una
estrategia o reutilizar su nombre no transfiere reservas antiguas a otro
coordinador. Al migrar, se vinculan las reservas que coinciden con un nombre
vigente del catálogo; las desconocidas permanecen fuera del alcance. Las
estrategias propuestas en solicitudes pendientes no se pueden renombrar ni
eliminar hasta atender esas solicitudes.

Los marcadores públicos consultados el 19 de octubre de 2026 informaron:

- Institucional: `30539ab1c483b6b9acbd53a10e839a38a4ba4c7e`.
- Pruebas: `a8961b9355355ce8893b81f86d973260202467db`.

Ambas revisiones incluían la restricción anterior por expediente. El nuevo
alcance por estrategias se preparó localmente; los marcadores no acreditan la
instalación de estos cambios. No se modificó ninguna instalación remota.

## Validación

- Compuerta `test:access-control` ampliada con gestión de estrategias, alcance
  de coordinación, eliminación y reintento de migraciones:
  **208 pruebas y 1535 aserciones aprobadas**. Las pruebas
  comprueban acceso por varias estrategias,
  bloqueo de enlaces directos ajenos, revocación inmediata, roles combinados,
  exportaciones y edición de membresías y clasificación.
- Compuerta `test:security-baseline`: **112 pruebas y 857 aserciones aprobadas**.
- Las dos migraciones nuevas pueden reanudarse si una interrupción deja columnas
  o tablas creadas antes de registrar la migración. Sus regresiones conservan
  registros, asignaciones y membresías; pasaron también en una base MariaDB
  10.11 aislada (**2 pruebas, 14 aserciones**), sin datos ni accesos de producción.
- Pint aprobado en 34 archivos PHP; vistas Blade compiladas. Se comprobó en
  Chrome sin interfaz la sincronización de estrategias y la independencia de
  consultorio/cubículo, usando el JavaScript actual de la vista. La recuperación
  del formulario conserva los datos después de errores de validación.
  El validador Blade terminó con código cero y observaciones de formato
  distribuidas en las vistas existentes.
- La revisión amplia detectó siete casos previos, reproducidos con fuentes
  aisladas de HEAD `e5337bf`: campo `nervioso` en tres pruebas, una notificación
  de tutor, un fixture de cubículo sin nombre y dos filtros de bitácora. No se
  modificaron esos casos fuera del alcance de esta corrección.
- Evidencia local ignorada y reproducible:
  `backend/storage/framework/testing/baseline-head-e5337bf-c8a3bbbb/`.

Esta publicación parte de `main` e incluye la corrección de permisos del
marcador de despliegue (`f72f82f`). No incorpora el PR de recuperación `#514`,
que conserva pendiente su ensayo integral.

Después de actualizar el código, ejecutar el instalador del agente para aplicar
dependencias, respaldo, migraciones, cachés y permisos. La aceptación requiere
además solicitar un despliegue nuevo desde la consola web y comprobar su
finalización; una pantalla de comprobaciones verde por sí sola no acredita ese
recorrido.
