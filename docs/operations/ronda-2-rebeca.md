# CAOPE Ronda 2

Implementación local de los requisitos confirmados por Axel y Rebeca el
4 de noviembre de 2026. Esta ronda requiere un despliegue independiente para
entrar en producción; publicar el código no activa estos cambios en el servidor.
La entrega anterior es el [PR 516](https://github.com/Sciosdev/caope/pull/516).

## Varias estrategias y responsabilidad

Maestro significa Docente; no se crea otro rol. Docente y Estratega siguen
siendo roles distintos y ambos admiten varias estrategias explícitas. Para
consultar un expediente deben cumplirse las dos condiciones:

- La cuenta es su responsable, identificado por `expedientes.tutor_id`.
- La estrategia del expediente pertenece a las asignadas a esa cuenta.

Pertenecer a CREAS no permite ver casos de otro responsable. Ser responsable
no permite ver casos de estrategias no asignadas ni casos sin clasificar.
La misma intersección se comprueba en consultas, acceso directo, sesiones,
documentos, reportes y exportaciones. Retirar una estrategia revoca su acceso
sin esperar a que expire una relación previamente cargada.

Facilitador también admite varias estrategias. Conserva el acceso a sus
expedientes asignados, sin obtener acceso a los de otros facilitadores por
compartir estrategia. Se mantiene la prohibición de crear expedientes.

Las estrategias de participación se guardan en `participante_estrategia` y se
configuran en Administración → Usuarios → Editar. Son independientes de las
estrategias que coordina una cuenta. El alcance de Coordinador no se amplía
por combinarlo con Docente, Estratega o Facilitador. Admin y PAPS aprobado
conservan sus permisos administrativos existentes.

## Configuración después del despliegue

La migración no deduce pertenencias desde expedientes, nombres ni asignaciones
de coordinación. Tampoco cambia cuentas reales ni reparte casos.

1. Antes de desplegar, prepara con Rebeca la lista de pertenencias reales para
   poder configurarlas al activar la nueva versión.
2. Una cuenta administrativa autorizada entra en Usuarios y edita a cada
   Docente, Estratega o Facilitador.
3. Marca sus estrategias de participación y guarda. Si también es Coordinador,
   configura por separado las estrategias que coordina.
4. En cada expediente comprueba estrategia y responsable correctos.
5. Prueba con una cuenta de cada rol que vea un caso propio de una estrategia
   asignada y no pueda abrir casos ajenos o de otra estrategia.

Mientras un Docente o Estratega no tenga estrategias de participación
explícitas, no podrá consultar casos únicamente por estar registrado como
responsable. Esta restricción es intencionada; no asignar Coordinador como
atajo. Rebeca debe decidir las pertenencias reales.

## Baja de expedientes

La acción Gestionar baja presenta dos modalidades, solo para Admin y PAPS
aprobado. Cada formulario nuevo exige motivo operativo y escribir el folio
exacto. El motivo no debe incluir datos personales ni información clínica.

### Archivar y conservar

Retira el expediente de la operación diaria y conserva datos, sesiones,
consentimientos, anexos y documentos. La conservación mínima se calcula desde
el momento de archivar hasta diez años después. No acorta una protección más
larga ya registrada ni elimina nada automáticamente al cumplirse el plazo.

Archivados tiene un listado administrativo separado y consulta de solo
lectura para las cuentas autorizadas. Restaurar devuelve el caso a la
operación diaria, pero conserva la fecha de protección. La restauración no
permite borrar definitivamente ni destruir archivos retenidos por vías
individuales. También se impide que borrar una cuenta arrastre comentarios
clínicos retenidos; se puede desactivar el acceso de esa cuenta.

Los formularios antiguos que envíen un DELETE sin modalidad archivan de forma
segura; no ejecutan una eliminación definitiva implícita.

### Eliminar porque fue creado por error

Es una elección separada e irreversible para registros erróneos. Puede
aplicarse directamente a un caso activo sin protección, sin archivarlo antes.
Un expediente con protección vigente no puede purgarse, ni siquiera después
de restaurarlo. Se conserva una auditoría administrativa mínima de la operación.

La transacción elimina explícitamente los consentimientos que antes impedían
el borrado por su restricción de integridad. Las reservas y solicitudes
conservan su historial, sin el vínculo a un expediente eliminado definitivamente.

Los archivos se eliminan únicamente después de confirmar la transacción más
externa. Si esta se revierte, siguen intactos. Si falla el almacenamiento,
quedan referencias pendientes para reintentar; no se informa falsamente que
los archivos desaparecieron. Se preservan archivos compartidos con otro
registro y se rechazan rutas inseguras antes de borrar datos.

El scheduler existente ejecuta `caope:retry-expediente-file-deletions` cada
15 minutos. Solo procesa referencias ya generadas por una purga explícita;
no busca expedientes antiguos ni inicia eliminaciones por antigüedad.

## Verificación y entrega

Las pruebas de estrategias, archivo, restauración, retención, permisos de
descarga, revocación, transacciones y eliminación de archivos forman parte
de `composer test:access-control`. Las pruebas usan datos sintéticos y discos
simulados; no eliminan expedientes de producción.

Validación local con PHP 8.3.30, base SQLite en memoria y datos sintéticos:

- Compuerta de acceso: 441 pruebas y 4152 aserciones aprobadas. Incluye 75
  pruebas y 999 aserciones de las clases nuevas de esta ronda.
- Seguridad: 112 pruebas y 857 aserciones aprobadas.
- Unitarias: 33 pruebas y 324 aserciones aprobadas. La selección estable de
  despliegue aprobó otras 85 pruebas y 344 aserciones.
- Frontend: 18 pruebas aprobadas, compilación Vite correcta y assets publicados
  localmente en los destinos del repositorio.
- Pint sobre 56 archivos PHP, compilación Blade, higiene del repositorio y
  simulación de recuperación cPanel correctos. El validador Blade termina
  con código cero y mantiene observaciones de formato de las vistas existentes.

El diagnóstico de la suite histórica completa ejecutó 722 pruebas y 5974
aserciones, con seis fallos y un error fuera de las compuertas anteriores:
dos expectativas de interfaz en consentimientos, un fixture antiguo de
coordinación, dos filtros de bitácora, una expectativa de notificación de
reasignación y un fixture de cubículo sin nombre. Esos casos siguen
pendientes; no se presenta la suite completa como aprobada. La comprobación
final se ejecutó sin otros procesos PHP de prueba concurrentes.

El despliegue posterior requiere la aprobación habitual por la web; esta
entrega no lo inicia. La rama de trabajo es `codex/rebeca-round2`, basada en
`origin/main` de la ronda 1. No incluir cambios ajenos del árbol de trabajo
original como parte de esta entrega.

El error reportado en `CA-2026-0039` cuenta ahora con un flujo de borrado
transaccional que contempla consentimientos y archivos relacionados. No se
ha borrado ni probado destructivamente ese expediente real.

## Decisiones históricas pendientes

- Rebeca debe resolver las equivalencias ambiguas entre cubículos históricos
  y reservas. No inferirlas por coincidencia de número.
- Para empalmes anteriores debe indicar qué reserva conservar y cuál
  reprogramar. No modificar reservas reales automáticamente.

## Seguimiento técnico separado

La revisión también detectó un riesgo anterior a esta ronda: el autoborrado
desde Perfil no aplica la protección de último administrador que sí utiliza
Administración → Usuarios. No se amplió esta entrega para cambiar esa regla.
No usar el autoborrado del perfil de la última cuenta administrativa.
