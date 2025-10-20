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

### Colas y caché

- Inicia el worker de colas cuando requieras procesar trabajos en segundo plano:

  ```bash
  php artisan queue:work
  ```

- Limpia la caché de la aplicación si necesitas regenerar la información almacenada:

  ```bash
  php artisan cache:clear
  ```

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
