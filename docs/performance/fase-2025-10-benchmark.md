# Informe de fase – Benchmarks de octubre 2025

## Resumen de ejecución local
- `composer install`
- `npm ci`
- `php artisan test`
- `npm run build`
- `php artisan migrate:fresh --seed`
- Servidor local con `php artisan serve --host=0.0.0.0 --port=8000`
- Autenticación como `admin@demo.local`

## Resultados de medición
| Endpoint | Herramienta | Peticiones | Concurrencia | Promedio | P95 | Notas |
| --- | --- | --- | --- | --- | --- | --- |
| `/expedientes` | `ab -n 50 -c 5` | 50 | 5 | 874.816 ms | 907 ms | Oscilaciones entre 205–953 ms. 【F:docs/performance/2025-10-22-expedientes-ab.txt†L1-L37】 |
| `/dashboard` | `ab -n 50 -c 5` | 50 | 5 | 831.651 ms | 923 ms | Variaciones 166–948 ms. 【F:docs/performance/2025-10-22-dashboard-ab.txt†L1-L37】 |

## Observaciones
- Ambas rutas superan el objetivo de <400 ms en promedio bajo carga ligera.
- Los tiempos muestran bimodalidad (~5 ms vs ~505 ms), lo que sugiere consultas pesadas o cache frío en cada request.
- Las semillas generan 40 expedientes con relaciones y podrían detonar N+1 en las vistas iniciales.

## Issues abiertos
- [Expedientes index tarda ~0.8s en responder](../issues/2025-10-22-expedientes-performance.md)
- [Dashboard inicial responde en ~0.83s](../issues/2025-10-22-dashboard-performance.md)
