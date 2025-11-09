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

### Actualizar expediente (PUT /expedientes/{id})

La respuesta JSON comparte el mismo esquema que la creación. Los anexos incluyen URLs firmadas vigentes y el mensaje `student_error_message` puede reutilizarse en el frontend para mostrar una guía clara a estudiantes cuando una acción falle.

### Recomendaciones de frontend

* Refrescar el estado local con el nodo `expediente` devuelto en la respuesta JSON.
* Mostrar `message` como notificación de éxito.
* Ante cualquier error de red, se puede reutilizar `student_error_message` para brindar una orientación consistente a los alumnos.
