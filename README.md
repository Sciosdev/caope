# CAOPE – Sistema de Seguimiento de Expedientes (monorepo)

## Estructura
- `backend/`: Aplicación Laravel 12 con SQLite para el MVP académico-clínico.
- `public-assets/`: assets NobleUI listos para publicarse en Laravel (`public/assets`).
- `_template/`: demos, documentación y SCSS originales del tema (solo referencia).
- `docs/`: documentación funcional, de planeación y operación (`docs/blueprint.md`, `docs/environments.md`).
- `preview.html`: mock estático para validar estilos sin correr Laravel.

## Estado actual
- Laravel 12 con autenticación base pendiente, pero con módulo de **Expedientes** leyendo datos ficticios desde SQLite.
- Layout NobleUI integrado en Blade y tablas con DataTables + filtros básicos.
- Seeder que genera 80 expedientes de ejemplo (`php artisan migrate --seed`).
- Entorno local listo con `php artisan serve`.

## Convenciones de ramas
- **main**: rama estable para releases y despliegues a producción.
- **develop**: base por defecto para Pull Requests y trabajo continuo.
- **feature/<tarea>**: ramas derivadas desde `develop` para nuevas funcionalidades o fixes concretos. Reemplaza `<tarea>` por el identificador corto de la actividad (por ejemplo, `feature/123-formulario-intake`).

Antes de abrir un PR, asegúrate de que apunte a `develop` salvo que se especifique lo contrario.

## Pasos rápidos para desarrollar
1. `cd backend`
2. `composer install && npm install`
3. Copiar `.env.example` → `.env` (hay uno de referencia en la raíz) y ajustar `DB_CONNECTION=sqlite`.
4. `php artisan key:generate`
5. `php artisan migrate --seed`
6. `php artisan serve`

> Opcional: `npm run dev` si deseas recompilar assets Vite propios. NobleUI ya está publicado en `public/assets`.

### Docker Compose (opcional)

También puedes levantar el entorno local con Docker usando SQLite por defecto:

```bash
touch backend/database/database.sqlite
cp backend/.env.example backend/.env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

El servicio queda disponible en <http://localhost:8000>. Consulta `docs/environments.md` para conocer los requisitos y la operativa de cada entorno (local, staging y producción).

## Próximos módulos (según blueprint)
- Roles/Permisos (Alumno, Docente, Coordinación, Admin).
- Flujo completo de expedientes (secciones, autosave, timeline y cierre).
- Consentimiento informado y control de revisiones.
- Registro de sesiones/atenciones con anexos por expediente.
- Reportes iniciales de estados, alertas y actividad.

Revisa `docs/blueprint.md` para el detalle funcional completo y próximos entregables.
