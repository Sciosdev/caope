# API de expedientes

## Crear expediente (POST /expedientes)

Cuando la petición incluye cabeceras `Accept: application/json` o se envía mediante `fetch`/`axios`, la respuesta ahora retorna un JSON con la siguiente estructura:

```json
{
  "message": "Expediente creado correctamente.",
  "student_error_message": "No pudimos guardar tu expediente. Revisa la información e inténtalo nuevamente o contacta a tu tutor si el problema persiste.",
  "expediente": {
    "id": 1,
    "no_control": "CA-2025-0001",
    "alumno": { "id": 42, "name": "Alumno Demo" },
    "anexos": []
  }
}
```

El campo `alumno` expone al creador del expediente y `anexos` se entrega con los enlaces firmados (`download_url`, `preview_url`) cuando existan registros.

La asignación de acceso usa `facilitador_id`, separado de `creado_por` (autoría).
Administración y PAPS aprobado pueden elegir una cuenta activa con rol `alumno`
al crear el expediente. Un facilitador que captura su propio expediente queda
asignado a él mismo. El campo `resumen_clinico.facilitador` es descriptivo:
escribir un nombre no concede acceso. La respuesta incluye la relación
`facilitador` con la cuenta asignada.

### Actualizar expediente (PUT /expedientes/{id})

La respuesta JSON comparte el mismo esquema que la creación. Los anexos incluyen URLs firmadas vigentes y el mensaje `student_error_message` puede reutilizarse en el frontend para mostrar una guía clara a estudiantes cuando una acción falle.

Administración, PAPS aprobado y el coordinador asignado pueden actualizar
`facilitador_id`; los demás perfiles no pueden reasignarlo. Una reasignación
retira el acceso del facilitador anterior sin alterar la autoría. Un valor
`null` explícito deja el expediente sin facilitador. Los registros existentes
conservan inicialmente su asignación anterior mediante la migración; las
asignaciones históricas escritas únicamente como nombres deben corregirse
seleccionando la cuenta correspondiente en **Editar expediente**.

Los coordinadores se limitan a las estrategias configuradas en **Usuarios →
Estrategias que coordina**. Cada expediente se clasifica con `estrategia_id`;
`coordinador_id` conserva el contacto de seguimiento y no concede acceso a
otras estrategias. Coordinación puede consultar los expedientes de todas sus
estrategias, aunque el contacto de seguimiento sea otra persona. Una cuenta de
coordinación sin estrategias no puede consultar expedientes; un expediente sin
clasificar tampoco es visible para coordinación. Los roles adicionales de
facilitador, docente o estratega no amplían ese alcance. Administración y PAPS
aprobado conservan el alcance global.

La creación acepta una estrategia activa. Para clientes anteriores, si se
omite `estrategia_id` pero se envía `consultorio_reserva.estrategia`, se utiliza
la coincidencia exacta del catálogo; si ambos se envían, deben coincidir. Una
cuenta con roles coordinador y facilitador solo puede crear en las estrategias
que coordina. Al editar, únicamente los perfiles con alcance global pueden
cambiar o limpiar `estrategia_id`; el resto conserva la clasificación vigente.
Quitar una estrategia al coordinador retira inmediatamente el acceso a sus
expedientes, sesiones, anexos, reportes y exportaciones pendientes.

Los ajustes de horario y cubículo se tramitan desde la bitácora de
consultorios. PAPS aprobado puede abrir **Solicitar edición**, proponer los
valores nuevos y enviarlos a revisión; solo administración aplica la
aprobación. Esto incluye las reservas creadas desde un expediente.

### Recomendaciones de frontend

* Refrescar el estado local con el nodo `expediente` devuelto en la respuesta JSON.
* Mostrar `message` como notificación de éxito.
* Ante cualquier error de red, se puede reutilizar `student_error_message` para brindar una orientación consistente a los alumnos.
