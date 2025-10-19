# CAOPE – Sistema de Seguimiento de Expedientes (monorepo)

## Estructura
- `backend/`: Aplicación Laravel 12 con SQLite para el MVP académico-clínico.
- `public-assets/`: assets NobleUI listos para publicarse en Laravel (`public/assets`).
- `_template/`: demos, documentación y SCSS originales del tema (solo referencia).
- `docs/`: documentación funcional, planeación y guías de colaboración
  (`docs/blueprint.md`, `docs/branching.md`, `docs/contributing.md`).
- `preview.html`: mock estático para validar estilos sin correr Laravel.

## Estado actual
- Laravel 12 con autenticación base pendiente, pero con módulo de **Expedientes** leyendo datos ficticios desde SQLite.
- Layout NobleUI integrado en Blade y tablas con DataTables + filtros básicos.
- Seeder que genera 80 expedientes de ejemplo (`php artisan migrate --seed`).
- Entorno local listo con `php artisan serve`.

## Pasos rápidos para desarrollar
1. `cd backend`
2. `composer install && npm install`
3. Copiar `.env.example` → `.env` (hay uno de referencia en la raíz) y ajustar `DB_CONNECTION=sqlite`.
4. `php artisan key:generate`
5. `php artisan migrate --seed`
6. `php artisan serve`

> Opcional: `npm run dev` si deseas recompilar assets Vite propios. NobleUI ya está publicado en `public/assets`.

## Próximos módulos (según blueprint)
- Roles/Permisos (Alumno, Docente, Coordinación, Admin).
- Flujo completo de expedientes (secciones, autosave, timeline y cierre).
- Consentimiento informado y control de revisiones.
- Registro de sesiones/atenciones con anexos por expediente.
- Reportes iniciales de estados, alertas y actividad.

Revisa la documentación en `docs/` para más detalles:

- [Blueprint funcional](docs/blueprint.md)
- [Flujo de ramas y estrategias de merge](docs/branching.md)
- [Guía de commits y Pull Requests](docs/contributing.md)
