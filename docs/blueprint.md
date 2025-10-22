# Ubícate en la raíz del repo
cd C:\Users\Zak\Dev\Iztacala\caope

# Asegura la carpeta docs y escribe blueprint.md (sobrescribe si existe)
New-Item -ItemType Directory -Path .\docs -Force | Out-Null
@"
# CAOPE — Blueprint funcional y técnico (MVP)

**Objetivo:** plataforma académico-clínica para gestionar expedientes estudiantiles con trazabilidad, revisiones docentes, consentimientos, anexos y reportes.

**Stack:** Laravel 12 (PHP 8.3+), MySQL/MariaDB (prod), SQLite (dev), Blade + NobleUI (demo2), DataTables, Select2, Flatpickr, SweetAlert2.

---

## 1) Decisiones rápidas (cerradas) — MVP

- **Monorepo**: `caope/`
  - `backend/` (Laravel app).
  - `public-assets/` (assets productivos NobleUI).
  - `_template/` (fuente SCSS/documentación original del tema) — **no se despliega**.
- **Tema/UI**: NobleUI **demo2** (layout horizontal).
- **Nomenclatura**: clave pública del expediente = **No. de Control** (`no_control` en BD).
- **Estados de expediente**: `abierto | revision | cerrado`.
- **DB local**: SQLite. **Prod**: MariaDB/MySQL.
- **Storage**: `/storage/app/public` (no sensible) y `/storage/app/private` (sensible). En prod, opción S3/MinIO futura.
- **Roles**: `Alumno`, `Docente`, `Coordinación`, `Admin` (Spatie Permission).
- **Autenticación**: Laravel Breeze (email+password) ⟶ 2FA opcional post-MVP.
- **Tabla**: listados con **paginación server-side de Laravel**, DataTables sin búsqueda global (filtros en servidor).
- **Timeline**: eventos de negocio persistidos (quién, qué, cuándo).

---

## 2) Arquitectura y estándares

- **PHP 8.3+**, **Laravel 12**.
- **PSR-12** + `laravel/pint`.
- Commits: **Conventional Commits**.
- Ramas: `main` (estable), `develop` (integración), `feature/*` (módulos).
- i18n base: `es` (strings en `lang/es/`).
- Formularios: **FormRequest** para validación.
- Acceso: **Policies** por modelo (Spatie roles/permissions).

---

## 3) Modelo de datos (MVP)

### 3.1 Tablas principales

**users** (core Laravel)  
`id, name, email (unique), password, carrera(nullable), turno(nullable), remember_token, timestamps`

**roles / permissions** (Spatie)  
Estructura estándar del paquete.

**expedientes**  
- `id` PK  
- `no_control` string(30) **unique**
- `paciente` string(150)
- `estado` string(20) limitado a `abierto|revision|cerrado` (index)
- `apertura` date (index compuesto con estado)
- `carrera` string(100) (index)
- `turno` string(20) (index)
- `creado_por` FK → users(id)  
- `tutor_id` FK → users(id) nullable  
- `coordinador_id` FK → users(id) nullable  
- `created_at/updated_at`

**sesiones** (atenciones/visitas)  
- `id` PK  
- `expediente_id` FK (index, cascade)  
- `fecha` date (index)  
- `tipo` string(60)  
- `nota` longtext (rich text permitido)  
- `realizada_por` FK → users(id)  
- `status_revision` enum: `pendiente|observada|validada` (index)  
- `validada_por` FK → users(id) nullable  
- timestamps

**consentimientos**  
- `id` PK  
- `expediente_id` FK (index, cascade)  
- `tratamiento` string(120)  
- `requerido` boolean (default true)  
- `aceptado` boolean (default false)  
- `fecha` date nullable  
- `archivo_path` string(255) nullable (private)  
- `subido_por` FK → users(id) nullable  
- timestamps

**anexos**  
- `id` PK  
- `expediente_id` FK (index, cascade)  
- `tipo` string(60) (index)  
- `titulo` string(160)  
- `ruta` string(255) (private/public según tipo)  
- `tamano` integer (bytes)  
- `subido_por` FK → users(id)  
- timestamps

**timeline_eventos**  
- `id` PK  
- `expediente_id` FK (index, cascade)  
- `evento` string(80)  *(p.ej. `expediente.creado`, `estado.cambio`, `sesion.validada`, `consentimiento.cargado`)*  
- `actor_id` FK → users(id)  
- `payload` json nullable  
- `created_at`

### 3.2 Catálogos

**catalogo_carreras(codigo, nombre)**, **catalogo_turnos(codigo, nombre)**, **catalogo_tratamientos(nombre, activo)**, **catalogo_padecimientos(nombre, activo)**

### 3.3 Reglas / constraints

- `expedientes.no_control` **único** + formato `CA-YYYY-####` configurable (prefijo/correlativo por año).
- Borrados:
  - `expedientes`: **restrict** si existen sesiones o consentimientos (no se borran expedientes reales).
  - `anexos/sesiones/consentimientos/timeline`: **cascade** al borrar expediente solo en entornos de prueba.

---

## 4) Permisos y policies (MVP)

| Acción / Recurso      | Alumno | Docente/Tutor       | Coordinación           | Admin |
|-----------------------|:------:|:-------------------:|:----------------------:|:-----:|
| Ver listado           |   ✔    |         ✔           |           ✔            |  ✔   |
| Crear expediente      |   ✔    |         ✖           |           ✖            |  ✔   |
| Editar expediente     |   ✔ (si autor y no “cerrado”) | ✔ (asignado)   |  ✔ (reasignar/meta)     |  ✔   |
| Cambiar estado        |   ✖    |  ✔ (a “revisión”)   | ✔ (abrir/cerrar)       |  ✔   |
| Sesiones CRUD         |   ✔ (las suyas) | ✔ (revisión/validar) |  ✔ (ver)            |  ✔   |
| Consentimientos       |   ✔ (cargar) | ✔ (revisar)     |  ✔ (ver)               |  ✔   |
| Anexos                |   ✔    |         ✔           |           ✔            |  ✔   |
| Usuarios/Catálogos    |   ✖    |         ✖           |      parcial (reportes) |  ✔   |

**Policies**: `ExpedientePolicy`, `SesionPolicy`, `ConsentimientoPolicy`, `AnexoPolicy`.

---

## 5) Validaciones clave

**Expediente (store/update)**  
- `no_control`: requerido, único, patrón configurable.  
- `paciente`: requerido, 2–140 chars.  
- `carrera`, `turno`: requeridos (de catálogo).  
- `apertura`: fecha válida, ≤ hoy.

**Sesión**  
- `fecha`: requerida, ≤ hoy.  
- `nota`: requerida (min 10 chars), HTML permitido (lista blanca).  
- `status_revision`: transiciones válidas:  
  - `pendiente → observada|validada`, `observada → validada`.  
  - No se puede volver a `pendiente` si ya está `validada`.

**Consentimiento**  
- Si `requerido = true` → **no puede cerrar** expediente sin `aceptado = true` y `archivo_path` adjunto.  
- Archivos: pdf/jpg/png, ≤ 50MB (configurable).

**Cierre de expediente**  
- Debe existir ≥ 1 sesión **validada**.  
- Sin consentimientos requeridos pendientes.  
- Sin observaciones abiertas en sesiones.

---

## 6) UX / Vistas

- **Layout base**: `layouts/app.blade.php` (NobleUI demo2; topbar, breadcrumbs, slots para CSS/JS).
- **Dashboard**: tarjetas KPI (Abiertos, Revisión, Cerrados, Alertas), últimas actividades (timeline).
- **Expedientes**
  - **Listado**: filtros (texto, estado, fechas, carrera, turno), tabla con: No. de Control, Paciente, Estado (badge), Apertura, Carrera, Turno, Acciones.
  - **Crear/Editar**: formulario simple (autosave **post-MVP**).
  - **Detalle**: tabs: Resumen | Sesiones | Consentimientos | Anexos | Timeline.
- **Sesiones**: editor rich text (TinyMCE/EasyMDE), botón “Enviar a revisión”, “Marcar observación”, “Validar”.
- **Consentimientos**: tabla de tratamientos, descarga/impresión de formato, upload firmado.
- **Anexos**: lista/galería + visor.
- **Reportes**: filtros + export CSV/XLSX.

---

## 7) Rutas (HTTP) — MVP

- GET /login
- POST /logout

- GET / → dashboard.index
- GET /expedientes → expedientes.index
- GET /expedientes/crear → expedientes.create
- POST /expedientes → expedientes.store
- GET /expedientes/{id} → expedientes.show
- GET /expedientes/{id}/editar→ expedientes.edit
- PUT /expedientes/{id} → expedientes.update
- POST /expedientes/{id}/estado → expedientes.cambiarEstado (abierto|revision|cerrado)

- GET /expedientes/{id}/sesiones → sesiones.index
- POST /expedientes/{id}/sesiones → sesiones.store
- PUT /sesiones/{sid} → sesiones.update
- POST /sesiones/{sid}/revisar → sesiones.marcarObservada
- POST /sesiones/{sid}/validar → sesiones.validar

- GET /expedientes/{id}/consentimientos → consentimientos.index
- POST /expedientes/{id}/consentimientos → consentimientos.store
- POST /consentimientos/{cid}/archivo → consentimientos.subirArchivo
- PUT /consentimientos/{cid} → consentimientos.update

- GET /expedientes/{id}/anexos → anexos.index
- POST /expedientes/{id}/anexos → anexos.store
- DELETE /anexos/{aid} → anexos.destroy

- GET /reportes/expedientes → reportes.expedientes
- GET /reportes/export → reportes.export


---

## 8) Eventos (timeline) — nomenclatura

- `expediente.creado`, `expediente.actualizado`, `expediente.estado_cambiado`
- `sesion.creada`, `sesion.observada`, `sesion.validada`
- `consentimiento.creado`, `consentimiento.cargado`, `consentimiento.actualizado`
- `anexo.subido`, `anexo.eliminado`

Payload típico: `{ "antes": "...", "despues": "...", "comentario": "...", "campo": "estado" }`.

---

## 9) Notificaciones (in-app / email)

- Asignación de tutor (a Docente).
- Sesión **observada** (a Alumno).
- Sesión **validada** (a Alumno).
- Intento de cierre bloqueado (a Alumno + Tutor).
- Cierre exitoso (a Alumno + Coordinación).

Driver: `mail=log` en dev, SMTP en prod.

---

## 10) Reportes (MVP)

- **Operativo**: conteos por estado, carrera, turno, rango fechas.
- **Rendimiento**: tiempo promedio `apertura → cierre` por carrera/turno.
- **Trazabilidad**: actividades por usuario/rol.

Export: CSV (simple) y XLSX (opcional con `maatwebsite/excel`).

---

## 11) Seguridad y privacidad

- CSRF, XSS, Rate limit en auth y carga de archivos.
- **Mínimo privilegio** por rol y policy.
- PII minimizada en listados; ver detalle solo si autorizado.
- Archivos sensibles en `private` (no servidos públicamente). Descarga por controlador con policy.
- Backups diarios (DB + storage). Prueba de restauración **mensual**.

---

## 12) Entornos y .env

**Desarrollo (SQLite)**

APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

**Producción (MySQL/MariaDB)**

APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=caope
DB_USERNAME=...
DB_PASSWORD=...
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public


---

## 13) Despliegue (cPanel)

1. **Repositorio** en el servidor (Git™ Version Control) apuntando a `backend/`.
2. **Document Root** del sitio apuntar a `backend/public`.
3. `php -d detect_unicode=0 artisan key:generate` (si no existe APP_KEY).
4. `php artisan storage:link`.
5. `php artisan migrate --force`.
6. **Cron**:
   - Queue (si se requiere): `* * * * * php /path/backend/artisan queue:work --stop-when-empty`
   - Scheduler: `* * * * * php /path/backend/artisan schedule:run`
7. Verificar permisos de `storage/` y `bootstrap/cache/`.

---

## 14) CI/CD (GitHub Actions) — resumen

- Job 1: **Lint** (`composer install --no-dev`, `php -v`, `./vendor/bin/pint --test`).
- Job 2: **Tests** (con `phpunit`, base SQLite en memoria).
- Job 3: **Deploy staging** (SSH/Git Pull) en `push` a `develop`.  
- Job 4: **Deploy prod** (manual dispatch o tag `v*`).

---

## 15) Pruebas (mínimo)

- **Feature**
  - Crear expediente (valido/duplicado `no_control`).
  - Filtros de listado (estado/fechas/carrera/turno).
  - Flujo sesión: crear → observar → validar (transiciones inválidas fallan).
  - Consentimiento requerido bloquea cierre si no está cargado/aceptado.
  - Cerrar expediente exitosamente (timeline + estado).
- **Unit**
  - Generación de `no_control`.
  - Policies de acceso.

---

## 16) Sprint 1 (en curso) — Objetivo: Expedientes CRUD + Detalle base

**Entregables del Sprint 1**
- Migraciones + modelos + factories de: `expedientes`, `sesiones`, `consentimientos`, `anexos`, `timeline_eventos`, catálogos mínimos.
- Listado de expedientes con filtros y paginación, vista detalle (tabs vacías).
- Cambios de estado con reglas mínimas (sin cierre duro).
- Timeline registrando crear/editar/estado.
- Seeders con ~40 expedientes demo.

**Tareas**
1. (DB) Migraciones y factories (expedientes/sesiones/consentimientos/anexos/timeline/catálogos).
2. (Modelos) Relaciones Eloquent y casts (dates, json).
3. (Policies) Expediente, Sesión, Consentimiento, Anexo.
4. (HTTP) Controladores + FormRequests (store/update + filtros index).
5. (Views) `layouts/app.blade.php` con NobleUI demo2 + `expedientes.index` + `expedientes.show` (tabs).
6. (Timeline) Helper `Timeline::push($evento, $expediente, $payload)` centralizado.
7. (Seed) Usuarios demo por rol + asignaciones.
8. (Tests) Feature: crear expediente y filtro; Unit: generador `no_control`.

**Criterios de aceptación Sprint 1**
- Se puede **crear**, **listar**, **ver detalle** y **cambiar estado** (sin cierre final).
- Filtros funcionan y paginan.
- Timeline registra eventos básicos.
- 0 errores en CI (lint + tests).

---

## 17) Backlog priorizado (post Sprint 1)

- **Sesiones**: UI completa, revisión/validación, editor rich text, adjuntos por sesión.
- **Consentimientos**: tabla por expediente, impresión PDF, carga firmada, estados.
- **Anexos**: multiupload, visor, tipos.
- **Cierre de expediente**: regla dura (consentimientos + sesiones validadas).
- **Notificaciones**: in-app + email.
- **Reportes**: KPIs dashboard + export CSV.
- **Admin**: usuarios/roles, catálogos.
- **Seguridad**: hardening, backups, restauración probada.
- **Rendimiento**: índices y EXPLAIN en consultas críticas.
- **2FA** (opcional).

---

## 18) Vendors/librerías (MVP)

- **spatie/laravel-permission** (roles/permisos).
- **laravel/breeze** (auth).
- **maatwebsite/excel** (export opcional).
- Front: DataTables, Select2, Flatpickr, SweetAlert2, TinyMCE/EasyMDE (uno).

---

## 19) Glosario

- **No. de Control**: identificador público y único del expediente (`no_control`).
- **Sesión**: atención/visita clínica; reemplaza “nota de evolución”.
- **Observada**: sesión con comentarios del Docente; debe resolverse antes de validar.
- **Validada**: sesión aprobada por Docente.
- **Cierre**: pasar expediente a `cerrado` cumpliendo reglas.
"@ | Set-Content -Path .\docs\blueprint.md -Encoding UTF8
