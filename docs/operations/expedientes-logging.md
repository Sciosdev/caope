# Monitoreo de errores en la creación de expedientes

Este documento describe cómo revisar los logs relacionados con el endpoint `POST /expedientes`.

## Dónde se almacenan los logs

La aplicación Laravel escribe los eventos en el archivo rotativo configurado en `storage/logs/laravel.log`. En ambientes de producción puede variar el canal (`daily`, `stack`, etc.), pero el archivo dentro de `storage/logs/` siempre estará disponible en la instancia de la aplicación.

## Consultar los eventos más recientes

```bash
cd backend
tail -f storage/logs/laravel.log
```

Los mensajes agregados para el flujo de creación de expedientes incluyen:

- `Received request to create expediente`: llegada de la petición con los metadatos básicos.
- `Validated expediente data for creation`: resultado de la validación.
- `Attempting to create expediente`: previo al intento de guardado.
- `Expediente created successfully`: confirmación de un alta correcta.
- `Expediente creation aborted due to missing columns` o `Failed to create expediente`: contexto adicional cuando ocurre un error.

Cada mensaje anexa el identificador del usuario autenticado y, si está disponible, la información enviada desde el frontend (`client_context`).

## Reproducción y pruebas automatizadas

Para ejecutar las pruebas que validan el manejo de errores y el logging asociado al endpoint:

```bash
cd backend
php artisan test --filter=ExpedienteCreateTest
```

Estas pruebas cubren escenarios exitosos y fallidos (por ejemplo, columnas faltantes), verificando tanto la respuesta como la información que se registra en los logs.

## Buenas prácticas

- Revisa los logs inmediatamente después de reproducir un error para obtener el identificador del expediente o el código de SQLSTATE asociado.
- Incluye la información mostrada en el formulario (código de error y columnas afectadas) cuando levantes un ticket con el equipo de infraestructura.
