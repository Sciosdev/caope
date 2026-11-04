# Prevención de empalmes en consultorios

## Regla

Cada número de `catalogo_consultorios` identifica un espacio físico reservable.
Una reserva bloquea ese consultorio en su fecha y horario, aunque otra petición
envíe un `cubiculo_numero`, estrategia o persona diferentes. Los cubículos
históricos se conservan; no se renumeran ni se convierten automáticamente.

Hay empalme cuando `inicio_existente < fin_nuevo` y
`fin_existente > inicio_nuevo`. Las citas contiguas, por ejemplo 09:00–10:00 y
10:00–11:00, son válidas. Otro día u otro consultorio tampoco se bloquean.

## Protección de escritura

`ConsultorioReservationGuard` centraliza la comprobación sin limitarla a las
reservas visibles para quien captura. También cuentan reservas pendientes de
aprobación que ya existen en la base de datos. Los mensajes de conflicto no
revelan datos de otras personas.

Antes de guardar, cada transacción bloquea la fila del consultorio y vuelve a
comprobar los horarios mediante una lectura bloqueante. Esto cubre alta desde
bitácora, edición administrativa, aprobación de solicitudes y alta vinculada a
un expediente. Las series semanales de bitácora se guardan completas o no se
guarda ninguna fecha. Un conflicto al dar de alta el expediente revierte también
el expediente y su hoja de urgencia.

No se borran ni se reprograman empalmes anteriores: administración debe decidir
qué reserva conservar o mover. Esta corrección no unifica todavía los dos
catálogos ni activa la recurrencia semanal del formulario de expedientes.

## Verificación local

- `tests/Feature/Consultorios/ConsultorioOverlapProtectionTest.php`: empalmes
  parciales, completos y contenidos, cubículos diferentes, fechas, límites,
  ediciones, series semanales, conflictos posteriores a la validación,
  aprobación pendiente y reversión del expediente.
- `node --test tests/Frontend/consultorio-overlap.test.mjs`: comprobación visual
  previa por consultorio y fecha; un fallo de red se muestra como disponibilidad
  no verificada. La decisión definitiva siempre se toma en el servidor.

Se verificó además la concurrencia real en una base desechable de MariaDB
10.11.18, con dos conexiones independientes en `REPEATABLE READ`. La primera
mantuvo el bloqueo del consultorio y una reserva sin confirmar; la segunda
estableció una instantánea anterior y quedó esperando ese mismo bloqueo
(confirmado mediante `INNODB_LOCK_WAITS`). Tras confirmar la primera, la lectura
ordinaria de la segunda todavía no veía la reserva, pero la lectura bloqueante
del guard detectó el empalme entre cubículos distintos y devolvió el error de
validación. Solo se guardó una reserva. Una reserva posterior contigua sí se
aceptó. No se utilizaron bases de datos ni expedientes de producción.
## Terminología posterior: cubículos

La interfaz utiliza ahora un único catálogo de **Cubículos**. Los identificadores
internos `consultorio_numero` de este documento se mantienen por compatibilidad
y representan ese único espacio físico. El antiguo subcubículo independiente
ya no se selecciona. Ver [unificación de cubículos](unificacion-cubiculos.md).
