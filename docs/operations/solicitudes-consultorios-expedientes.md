# Identificación del expediente en solicitudes de consultorios

La lista de solicitudes muestra por separado quién hizo la solicitud, el número
del expediente y el nombre de la persona atendida. El nombre no se obtiene del
usuario de la reserva: esa cuenta puede ser un facilitador con varios casos.

## Vínculo explícito

La migración `2026_11_04_000001_add_expediente_id_to_consultorio_reservas_table`
agrega `consultorio_reservas.expediente_id`, nullable, indexado y con FK a
`expedientes`. Crear un expediente con reserva guarda automáticamente el ID real
del expediente recién creado, dentro de la misma transacción. No se acepta como
fuente de verdad un ID anidado enviado por el cliente.

No hay backfill por nombre, facilitador, fecha, estrategia ni coincidencias
numéricas. Las reservas antiguas sin una relación comprobada permanecen sin
vincular y la lista lo indica.

Después de desplegar y ejecutar la migración, administración puede abrir
**Editar reserva → Expediente vinculado**, buscar por número y persona y guardar
la asociación correcta. PAPS aprobado puede proponerla, pero solo administración
la aplica al aprobar. La lista distingue un vínculo propuesto del vigente.
Omitir el campo conserva la relación; elegir la opción vacía la retira.

Los permisos del expediente siguen controlando su enlace y el nombre visible.
PAPS continúa viendo solo sus propias solicitudes; no se amplía el acceso de
facilitadores, docentes o coordinadores a esta pantalla.

Eliminar un expediente deja nulo el vínculo de la reserva y no elimina la cita
ni sus solicitudes. Esto no cambia la protección de consentimientos ni decide
el alcance del borrado clínico.

Verificación: `tests/Feature/Consultorios/ConsultorioSolicitudExpedienteTest.php`
cubre identidad, autorización, propuestas, asociación manual, datos antiguos,
escape HTML y conservación de registros al eliminar el expediente.
