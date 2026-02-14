# Backend (Laravel 12)

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

### Migraciones de ficha clínica y backfill

- Ejecuta `php artisan migrate` antes de desplegar para asegurarte de que los campos de ficha clínica (migración `2025_10_19_090600_add_ficha_fields_to_expedientes_table.php`) y las secciones psicológicas/antecedentes estén presentes en la tabla `expedientes`.
- Si ya existían expedientes, corre la migración de sincronización de defaults JSON para evitar errores de constraints y rellenar valores por omisión:

  ```bash
  php artisan migrate --path=database/migrations/2025_11_06_000000_sync_expediente_json_defaults.php
  ```

- En entornos con datos en producción, respalda la base antes de ejecutar las migraciones y valida que los nuevos campos queden en `NULL` o con los defaults generados para evitar fallos al capturar.

### Colas y caché

- Inicia el worker de colas cuando requieras procesar trabajos en segundo plano:

  ```bash
  php artisan queue:work
  ```

- Limpia la caché de la aplicación si necesitas regenerar la información almacenada:

  ```bash
  php artisan cache:clear
  ```

- Ajusta el tiempo de cacheo de métricas del dashboard con `DASHBOARD_CACHE_TTL`
  (segundos). Usa `0` para desactivar la caché si necesitas datos al instante.

## Datos de ejemplo
`php artisan migrate --seed` registra los catálogos base (carreras, turnos, padecimientos y tratamientos), crea 80 expedientes de ejemplo con vínculos a esos catálogos y genera un usuario de muestra (`test@example.com`).

En ambientes limpios puedes restablecer todo ejecutando:

```bash
php artisan migrate:fresh --seed
```

Si solo necesitas refrescar un catálogo específico:

```bash
php artisan db:seed --class=CatalogoCarreraSeeder
php artisan db:seed --class=CatalogoTurnoSeeder
php artisan db:seed --class=CatalogoPadecimientoSeeder
php artisan db:seed --class=CatalogoTratamientoSeeder
```

## UI integrada
- Layout NobleUI ya publicado en `public/assets`.
- Vista `expedientes.index` muestra filtros con Flatpickr y DataTable en modo responsivo.

## Próximos pasos sugeridos
1. Implementar autenticación/roles (Laravel Breeze/Jetstream + Spatie Permissions).
2. Definir modelos/tablas reales según `docs/blueprint.md`.
3. Crear módulos por sección (Ficha, AFH, APP, sesiones, consentimiento, anexos).
4. Agregar pruebas feature para flujos clave.

Con esto el backend queda listo para iterar sobre el MVP funcional.
