# Expedientes index tarda ~0.8s en responder

- **Detectado por:** prueba de carga local usando `ab -n 50 -c 5` tras ejecutar `php artisan migrate:fresh --seed`.
- **Contexto:** SQLite local, Laravel `php artisan serve`, usuario `admin@demo.local` autenticado.
- **Resultado actual:** tiempo medio de respuesta `~875 ms` y P95 `~907 ms` para `/expedientes`. 【F:docs/performance/2025-10-22-expedientes-ab.txt†L1-L37】
- **Esperado:** listado principal cargando en <400 ms bajo concurrencia ligera.

## Pasos para reproducir
1. `php artisan migrate:fresh --seed`
2. `php artisan serve`
3. Autenticarse como `admin@demo.local`.
4. Ejecutar `ab -n 50 -c 5 http://127.0.0.1:8000/expedientes` con la cookie de sesión activa.

## Sospechas iniciales
- La vista carga paginación + filtros y podría estar ejecutando múltiples consultas N+1 para anexos, consentimientos o sesiones.
- El caché de catálogos se recalienta en cada petición bajo SQLite y suma latencia.
- Revisar índices y `withCount`/`select` en `ExpedienteController@index` para reducir trabajo por request.

## Acciones sugeridas
- Capturar perfiles con Laravel Telescope o `Clockwork` para confirmar consultas costosas.
- Agregar métricas en producción/staging para monitorear P95 de este endpoint.
- Considerar cachear el resumen por usuario/rol o paginar vía API + frontend asincrónico.
