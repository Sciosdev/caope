# Dashboard inicial responde en ~0.83s

- **Detectado por:** `ab -n 50 -c 5` tras poblar datos con `php artisan migrate:fresh --seed`.
- **Contexto:** SQLite, `php artisan serve`, sesión `admin@demo.local`.
- **Resultado actual:** tiempo medio `~832 ms` y P95 `~923 ms` para `/dashboard`. 【F:docs/performance/2025-10-22-dashboard-ab.txt†L1-L37】
- **Esperado:** métricas iniciales <350 ms para vista inicial.

## Pasos para reproducir
1. `php artisan migrate:fresh --seed`
2. `php artisan serve`
3. Autenticarse como `admin@demo.local`.
4. Ejecutar `ab -n 50 -c 5 http://127.0.0.1:8000/dashboard` con la cookie de sesión activa.

## Sospechas iniciales
- Consultas agregadas para tarjetas del dashboard podrían estar recalculándose sin cache.
- Checar servicios `DashboardInsightsService`/`DashboardPendingsService` por N+1.
- Evaluar precálculo (jobs/colas) o cache per-user.

## Acciones sugeridas
- Activar Laravel Telescope + Query Log temporalmente para identificar queries lentas.
- Instrumentar métricas (Prometheus/New Relic) para rastrear P95 y tasa de errores.
- Considerar endpoints JSON cacheables consumidos por frontend asincrónico.
