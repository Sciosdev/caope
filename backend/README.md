# Backend (Laravel 11)

Aplicación Laravel que servirá como base del sistema académico-clínico de expedientes CAOPE.

## Requisitos
- PHP 8.2+
- Composer
- Node 18+

## Configuración inicial
```bash
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

> El proyecto está pensado para SQLite en desarrollo. Si prefieres MySQL/PostgreSQL ajusta el `.env` y crea la base correspondiente.

## Datos de ejemplo
`php artisan migrate --seed` ejecuta `ExpedienteSeeder` que llena la tabla `expedientes` con 80 registros (faker) y un usuario de muestra (`test@example.com`).

## UI integrada
- Layout NobleUI ya publicado en `public/assets`.
- Vista `expedientes.index` muestra filtros con Flatpickr y DataTable en modo responsivo.

## Próximos pasos sugeridos
1. Implementar autenticación/roles (Laravel Breeze/Jetstream + Spatie Permissions).
2. Definir modelos/tablas reales según `docs/blueprint.md`.
3. Crear módulos por sección (Ficha, AFH, APP, sesiones, consentimiento, anexos).
4. Agregar pruebas feature para flujos clave.

Con esto el backend queda listo para iterar sobre el MVP funcional.
