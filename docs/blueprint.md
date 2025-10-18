# Objetivo
Construir un sistema moderno para **dar seguimiento a expedientes clínicos** creados por alumnos en CAOPE: rápido de usar, claro, con estatus, notas de evolución, anexos y reportes básicos.

---
# Flujo actual (resumen)
1) Login → 2) Home (consultar expediente / cambiar contraseña) → 3) Lista de expedientes (No. + Paciente) → 4) Menú de secciones del expediente:
- Ficha de identificación
- Antecedentes familiares hereditarios (AFH)
- Antecedentes personales patológicos (APP)
- Consentimiento informado
- Notas de evolución
- Anexar documento
- Resumen clínico

---
# Flujo propuesto (MVP rápido)
**Post-login → Tablero**
- Tarjetas: "Mis expedientes activos", "Crear expediente", "Pendientes / próximos".

**Crear expediente (wizard de pasos cortos)**
1. Identificación (paciente)  
2. AFH  
3. APP (Antecedentes personales patológicos)  
4. Antecedentes y Padecimientos Actuales  
5. Plan de atención  
6. Diagnóstico  
7. Consentimiento informado  
8. Anexos (opcional)  
9. Resumen y **Abrir expediente**

**Vista Expediente**
- Encabezado: No. expediente, Paciente, Estado (Abierto / En curso / En revisión / Pausado / Cerrado), Progreso, Responsable (alumno).  
- Sidebar con secciones (FI, AFH, **APP**, **Antecedentes y Padecimientos Actuales**, **Plan de atención**, **Diagnóstico**, Consentimiento, **Registro de sesiones**, Anexos, Resumen).  
- Panel principal con formularios **autosave** y botón Guardar/Continuar.
- **Timeline** de seguimiento (alta, notas, cambios de estado, anexos).  
- Botón **Cerrar expediente** (con validación de secciones mínimas).

---
# Roles y permisos
- **Alumno**: crea/edita sus expedientes; sube anexos; escribe notas.  
- **Docente/Tutor**: lee todos los expedientes del grupo; puede comentar y marcar revisiones.  
- **Coordinación**: reportes, estados, reasignaciones.  
- **Admin**: catálogos y usuarios.

---
# Campos confirmados / ajustes
## Ficha de identificación
- Clínica: **CAOPE (por defecto)**
- No. de expediente (autogenerado)
- **Fecha de apertura** (antes: Fecha inicio)
- Paciente: nombre(s), apellidos, ocupación, estado civil, lugar de nacimiento, fecha de nacimiento, domicilio (calle, no, col, municipio/delegación, entidad), teléfono(s), email, institución de derechohabiencia, contacto de emergencia (nombre, teléfono, horario), médico de referencia, motivo de consulta (texto), **alerta** (texto corto), observaciones.
- **Género**: Masculino / Femenino / Persona no binaria / Prefiero no decirlo.  
- **Responsable (Alumno)**: **Carrera**, **Turno**, **No. de cuenta** (vinculado al usuario logueado).

## AFH
- Mantener matriz por **padecimiento × parentesco** (checkbox) y Observaciones.

## APP (Antecedentes personales patológicos)
- Catálogo de padecimientos con **presencia (sí/no)** y **fecha de diagnóstico** por ítem, más **Observaciones** generales de la sección.

## Antecedentes y Padecimientos Actuales
- Textos largos: **Historia Psicosocial y del Desarrollo**, **Evaluación Psicológica (Estado Mental Actual)** y **Evaluación Psicológica – Observaciones clínicas relevantes**.

## Plan de atención
- Texto largo único para el **Plan de atención**.

## Diagnóstico
- Textos largos: **Diagnósticos**, **DSM y TR** y **Observaciones relevantes**.

## Registro de sesiones (antes “Notas de evolución”)
- Lista lateral por **fecha** de sesión.  
- Formulario por sesión: **Fecha**, **Hora de asistencia**, **Estrategias** (texto o catálogo), **Descripción de la sesión** (hallazgos, técnicas aplicadas, acuerdos, avances, retrocesos).  
- **Interconsulta/Referencia** dentro de cada sesión: **Interconsulta o Referencia**, **Especialidad a la que se refiere**, **Motivo de referencia** (textos).  
- **Facilitador** (texto), **Clínica donde se realizó el tratamiento** (selector), **Autorización del responsable académico** (checkbox/fecha/usuario).  
- **Revisado** (solo visible a Docente/Tutor; guarda usuario y fecha).
- **Archivos por sesión** (subidos por alumno).  
- **Visor** del último **PDF de “Consentimiento informado y plan de tratamiento”** (generado o firmado) embebido en la parte inferior.
- Acciones: **Guardar** / **Agregar nueva sesión** / **Duplicar**.


## Consentimiento informado
- **Tabla de tratamientos** (filas dinámicas): `tipo` (ej. Evaluación Psicológica, Interconsulta médica/psiquiátrica, Reportes solicitados), campos opcionales para **órgano**, **tratamiento/código**, **pronóstico** y **costo** (para impresión).  
- **Alumno**: puede agregar filas, **guardar**, **imprimir** el formato "Consentimiento informado y plan de tratamiento" y **subir documento firmado** (archivo).  
- **Docente/Tutor**: ve un botón **“Revisado”** únicamente si tiene ese rol; al marcarlo se registra **usuario** y **fecha** de revisión.  
- Campos adicionales: **Profesor** (selector), **Testigo** (texto), **Observaciones del expediente**.

---
# Modelo de datos (provisional)
**usuarios**(id, nombre, correo, hash, rol_id, no_cuenta, carrera_id, turno_id, activo, creado_en)  
**roles**(id, nombre) — Alumno, Docente, Coordinación, Admin  
**carreras**(id, nombre)  
**turnos**(id, nombre)  
**generos**(id, nombre) — catálogo ampliado  
**clinicas**(id, nombre) — semilla: CAOPE

**pacientes**(id, nombre, apellidos, genero_id, fecha_nacimiento, ocupacion, estado_civil, lugar_nacimiento, curp?, telefono, email, domicilio_json)

**expedientes**(id, no_expediente, paciente_id, responsable_id, clinica_id, fecha_apertura, estado_id, alerta, observaciones, creado_en, actualizado_en)

**estados_expediente**(id, nombre, color_hex) — Abierto, En curso, En revisión, Pausado, Cerrado

**consentimientos**(id, expediente_id, alumno_id, profesor_id, testigo_text, observaciones, revisado_bool, revisado_por_id, revisado_fecha, firmado_bool, fecha_firma, archivo_firmado_url, pdf_generado_url, creado_en, actualizado_en)

**consentimiento_tratamientos**(id, consentimiento_id, tipo, organo, tratamiento_codigo, pronostico, costo, detalle, orden)

**notas_evolucion**(id, expediente_id, autor_id, fecha, nota, tiene_alerta_bool)

**documentos**(id, expediente_id, tipo, titulo, archivo_url, subido_por_id, creado_en)

**afh_padecimientos**(id, nombre) — Diabetes, HTA, Cardiopatías, etc.  
**afh_parentescos**(id, nombre) — Madre, Padre, Abuela, Abuelo, Hermanos, Otros

**afh_respuestas**(id, expediente_id, padecimiento_id, parentesco_id, valor_bool)

**app_items**(id, nombre, descripcion?)  
**app_respuestas**(id, expediente_id, item_id, presente_bool, fecha_diagnostico, comentario?)  
**app_observaciones**(id, expediente_id, texto)

**antecedentes_padecimientos_actuales**(id, expediente_id, historia_psicosocial, evaluacion_psicologica_estado, evaluacion_psicologica_observaciones, creado_en, actualizado_en)

**planes_atencion**(id, expediente_id, contenido, creado_en, actualizado_en)

**diagnosticos_clinicos**(id, expediente_id, diagnosticos_text, dsm_tr_text, observaciones, creado_en, actualizado_en)

**sesiones**(id, expediente_id, fecha, hora_asistencia, estrategias_text, descripcion, facilitador_nombre, clinica_id, autorizacion_responsable_bool, autorizacion_responsable_usuario_id, autorizacion_responsable_fecha, revisado_bool, revisado_por_id, revisado_fecha, visor_consentimiento_url, creado_en, actualizado_en)
**sesion_referencias**(id, sesion_id, interconsulta_o_referencia, especialidad, motivo, creado_en)
**sesion_archivos**(id, sesion_id, titulo, archivo_url, subido_por_id, creado_en)  

> **Claves**:  
> - FK de todas las tablas hacia `expedientes/usuarios/catalogos` con índices.  
> - `no_expediente` único con prefijo y año (p.ej. CA-2025-000123).

---
# Seguimiento (lo que hace “seguimiento”)
- **Estados** del expediente + **Timeline** de eventos (creación, edición, anexos, firma de consentimiento, nota con alerta, cambio de estado).  
- **Indicadores** por usuario/carrera/turno: abiertos, en revisión, cerrados; tiempo a cierre; expedientes con alerta.

---
# UI/UX (línea visual)
- Tema claro, tipografía legible, **cards** y **badges** de estado.  
- Dashboard con 3 tarjetas: *Crear expediente*, *Mis activos*, *Pendientes hoy*. Incluye **atajo a Consentimiento** para imprimir rápidamente y **panel de pendientes de revisión** (solo docentes).  
- Wizard con pasos y barra de progreso; formularios en 2 columnas; **autosave**.  
- Lista de expedientes con búsqueda, filtros (estado, carrera, turno, fecha) y paginación.  
- Vista móvil responsive.

---
# Validaciones / Reglas de negocio (MVP)
- No se puede **Cerrar** si: falta consentimiento o Ficha incompleta mínima (paciente + género + fecha apertura).  
- AFH/APP opcionales para abrir; **Diagnóstico** y **Plan de atención** recomendados antes de cerrar. Para marcar **Revisado** en Consentimiento se requiere rol Docente/Tutor (se registra usuario y fecha).  
- Notas de evolución requieren fecha y autor.  
- Documentos con tipo predefinido (Consentimiento, Identificación, Otros).

---
# Reportes iniciales
- Expedientes por estado/carrera/turno/mes.  
- Tiempo promedio a cierre.  
- **Sesiones** registradas por expediente / por mes.  
- Alertas activas.

---
# Próximos pasos con tu insumo
1) Completar campos de APNP y formatos de Consentimiento.  
2) Definir catálogo de padecimientos/parentescos final.  
3) Aterrizar nomenclatura de `no_expediente`.  
4) Ajustar modelo según nuevas pantallas que compartas.

> Cuando cierres requisitos, propondré **BD final** (DDL) y **stack de desarrollo** optimizado para velocidad de entrega, coherente con tus preferencias (PHP/MariaDB vs. alternativas).
