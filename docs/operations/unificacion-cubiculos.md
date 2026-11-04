# Catálogo único de cubículos

## Concepto y uso

Los espacios que antes se administraban bajo **Consultorios** pasan a
presentarse como **Cubículos**. Administración, expedientes, reservas,
calendarios, solicitudes y exportación usan el mismo catálogo y sus nombres
reales. No hay un consultorio conteniendo otro cubículo seleccionable.

El catálogo canónico conserva la tabla `catalogo_consultorios`, sus IDs,
números, nombres y estados. Así se conservan los espacios existentes —incluidos
los 18 configurados— sin reemplazarlos por una lista genérica de 14. El seeder
solo crea filas faltantes: no renombra ni reactiva las existentes.

Los nombres internos, rutas `consultorios.*` y columna
`consultorio_reservas.consultorio_numero` se conservan por compatibilidad.
Representan el único espacio físico seleccionado. Las reservas nuevas
normalizan el antiguo `cubiculo_numero` al mismo número, en el servidor;
enviar otro valor no permite seleccionar un segundo espacio ni eludir el
bloqueo de empalmes.

## Historial sin equivalencias inventadas

- La tabla anterior `catalogo_cubiculos` permanece intacta como archivo
  histórico, pero ya no es una fuente de selección ni un catálogo editable.
- Los valores anteriores `resumen_clinico.cubiculo` y los pares numéricos de
  reservas existentes no se reescriben durante la migración.
- El expediente tiene un nuevo vínculo nullable `cubiculo_id` al catálogo
  canónico. No se rellena por coincidencia de número: un antiguo “Cubículo 4”
  no demuestra ser el espacio hoy registrado con el número 4.
- Una asignación anterior sin confirmar se muestra como histórica y permite
  que una cuenta autorizada seleccione expresamente el espacio correcto. No
  se presenta como una asignación nueva ya confirmada.
- La asignación del expediente no equivale a reservar un horario. Las reservas
  continúan con sus validaciones de fechas, permisos, aprobación y empalmes.
- Las solicitudes anteriores conservan su payload como evidencia. Al
  aprobarlas se aplica su identidad física `consultorio_numero`, no una
  combinación de consultorio y subcubículo.

## Formularios antiguos y retiros

`/admin/catalogos/cubiculos` redirige al catálogo único. Un enlace antiguo de
edición lleva al listado para volver a seleccionar el espacio por su nombre;
no se interpreta un ID de la tabla anterior como un ID de la canónica.
Los formularios de modificación del catálogo retirado devuelven 409 sin
guardar nada. Debe abrirse el formulario nuevo desde Administración → Cubículos.

Un cubículo con reservas, solicitudes históricas o pendientes, o expedientes
vinculados no se elimina ni cambia de número. Se puede renombrar o desactivar
para dejar de ofrecerlo sin perder su historial. Los espacios sin referencias
sí pueden eliminarse. Desactivar no cancela reservas existentes.

## Publicación y comprobación

Antes del despliegue se debe generar el respaldo habitual. La migración es
aditiva: no borra catálogos, reservas ni documentos y no hace asignaciones
automáticas. Tras publicar, comprobar con Administración que los nombres y el
total de espacios coinciden, que se selecciona un único cubículo, que el mismo
nombre aparece en expediente y reserva, y que un horario ocupado es rechazado
sin modificar el expediente o crear una reserva parcial.

Las pruebas locales usan datos sintéticos, IDs distintos a los números de
espacio y colisiones deliberadas entre los dos catálogos antiguos para evitar
que reaparezcan equivalencias falsas.

Cobertura específica: `CubiculoUnificationTest`,
`ExpedienteCubiculoAssignmentTest`, `CubiculoAssignmentMigrationRecoveryTest`
y `tests/Frontend/cubiculo-unification.test.mjs`, además de los tests de
alcance, empalmes, solicitudes y administración de espacios.

La clase general `ConsultorioReservaTest` mantiene tres fallos previos a esta
unificación: `test_coordinador_solo_ve_reservas_asignadas_en_calendario`
(fixture de coordinación sin membresía de estrategia),
`test_index_filtra_bitacora_por_semana_desde_fecha_base` y
`test_index_filtra_bitacora_por_mes_desde_fecha_base`. No se han suprimido ni
alterado sus expectativas para hacer pasar esta entrega. Por ello no se
presenta la suite completa del repositorio como verificada en verde.
