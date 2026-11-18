@error('expediente')
    <div class="alert alert-danger" role="alert">
        {{ $message }}
    </div>
@enderror

@php($errorContext = session('expediente_error_context', []))

@if (! empty($errorContext))
    <div class="alert alert-warning" role="status">
        <h6 class="alert-heading mb-2">Hubo un problema técnico al guardar el expediente.</h6>
        @if (($errorContext['reason'] ?? null) === 'missing_columns')
            <p class="mb-2">Faltan columnas en la base de datos. Informa al equipo de soporte con la siguiente lista:</p>
            <ul class="mb-0 small">
                @foreach ($errorContext['columns'] ?? [] as $column)
                    <li><code>{{ $column }}</code></li>
                @endforeach
            </ul>
        @elseif (($errorContext['reason'] ?? null) === 'database_error')
            <p class="mb-0">Ocurrió un error al comunicarse con la base de datos. Código: <strong>{{ $errorContext['code'] ?? 'desconocido' }}</strong>. Intenta nuevamente y, si el problema persiste, contacta al equipo técnico.</p>
        @else
            <p class="mb-0">El sistema registró el incidente y el equipo técnico ha sido notificado.</p>
        @endif
    </div>
@endif

@php($alertaActiva = filled(old('alerta_ingreso', $expediente->alerta_ingreso ?? null)))
@php($isCreating = ! isset($expediente) || ! $expediente->exists)
@php($isPaps = auth()->user()?->isApprovedPaps())
@php($canChangeAssignments = $isCreating || auth()->user()?->hasGlobalExpedienteAccess() || auth()->user()?->isCoordinatorOf($expediente))
@php($showNuevaAsignacion = auth()->user()?->hasAnyRole(['admin', 'alumno']) || auth()->user()?->isApprovedPaps())
@php($canChangeCubiculo = $isCreating || auth()->user()?->hasRole('admin') || $isPaps)
@php($hasLinkedReservations = $hasLinkedReservations ?? false)
@php($legacyCubiculo = data_get($expediente->resumen_clinico ?? [], 'cubiculo'))

<div class="card border shadow-none mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <p class="text-muted text-uppercase small mb-1">Ficha institucional</p>
                <h6 class="mb-0">Datos institucionales</h6>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <label for="no_control" class="form-label">Número de control</label>
                <input
                    type="text"
                    name="no_control"
                    id="no_control"
                    value="{{ old('no_control', $expediente->no_control ?? '') }}"
                    class="form-control @error('no_control') is-invalid @enderror"
                    maxlength="30"
                    readonly
                    required
                >
                @error('no_control')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="paciente" class="form-label">Alumno</label>
                <input
                    type="text"
                    name="paciente"
                    id="paciente"
                    value="{{ old('paciente', $expediente->paciente ?? '') }}"
                    class="form-control @error('paciente') is-invalid @enderror"
                    maxlength="140"
                    required
                >
                @error('paciente')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="apertura" class="form-label">Fecha de apertura</label>
                <input
                    type="date"
                    name="apertura"
                    id="apertura"
                    value="{{ old('apertura', optional($expediente->apertura ?? null)->format('Y-m-d')) }}"
                    class="form-control js-flatpickr @error('apertura') is-invalid @enderror"
                    data-max-date="today"
                    required
                >
                @error('apertura')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="clinica" class="form-label">Clínica</label>
                <input
                    type="text"
                    name="clinica"
                    id="clinica"
                    value="{{ old('clinica', $expediente->clinica ?? 'Caope') }}"
                    class="form-control @error('clinica') is-invalid @enderror"
                    maxlength="120"
                    @if ($isCreating) readonly @endif
                >
                @error('clinica')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="fecha_inicio_real" class="form-label">Fecha de Primera Consulta</label>
                <input
                    type="date"
                    name="fecha_inicio_real"
                    id="fecha_inicio_real"
                    value="{{ old('fecha_inicio_real', optional($expediente->fecha_inicio_real ?? null)->format('Y-m-d')) }}"
                    class="form-control js-flatpickr @error('fecha_inicio_real') is-invalid @enderror"
                    data-max-date="today"
                >
                @error('fecha_inicio_real')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="carrera" class="form-label">Carrera</label>
                <select
                    name="carrera"
                    id="carrera"
                    class="form-select js-select2 @error('carrera') is-invalid @enderror"
                    data-placeholder="Seleccione una carrera"
                    required
                >
                    <option value="">Seleccione</option>
                    @foreach ($carreras as $carreraNombre)
                        <option value="{{ $carreraNombre }}" @selected(old('carrera', $expediente->carrera ?? '') === $carreraNombre)>
                            {{ $carreraNombre }}
                        </option>
                    @endforeach
                </select>
                @error('carrera')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="turno" class="form-label">Turno</label>
                <select
                    name="turno"
                    id="turno"
                    class="form-select js-select2 @error('turno') is-invalid @enderror"
                    data-placeholder="Seleccione un turno"
                    required
                >
                    <option value="">Seleccione</option>
                    @foreach ($turnos as $turnoNombre)
                        <option value="{{ $turnoNombre }}" @selected(old('turno', $expediente->turno ?? '') === $turnoNombre)>
                            {{ $turnoNombre }}
                        </option>
                    @endforeach
                </select>
                @error('turno')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="estrategia_id" class="form-label">Estrategia del expediente</label>
                <select
                    name="estrategia_id"
                    id="estrategia_id"
                    class="form-select @error('estrategia_id') is-invalid @enderror"
                    @disabled(! $isCreating && ! auth()->user()?->hasGlobalExpedienteAccess())
                >
                    <option value="">Sin clasificar</option>
                    @foreach ($estrategiasExpediente as $estrategia)
                        <option value="{{ $estrategia->id }}" data-nombre="{{ $estrategia->nombre }}" @selected((int) old('estrategia_id', $expediente->estrategia_id ?? 0) === $estrategia->id)>{{ $estrategia->nombre }}</option>
                    @endforeach
                </select>
                <div class="form-text">La estrategia determina qué coordinadores pueden consultar el expediente. Sin clasificar, solo los demás perfiles autorizados tendrán acceso.</div>
                @error('estrategia_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="tutor_id" class="form-label">Estratega responsable del expediente</label>
                <select
                    name="tutor_id"
                    id="tutor_id"
                    @if ($isCreating && $canChangeAssignments) aria-describedby="tutor_id_help" @endif
                    class="form-select js-select2 @error('tutor_id') is-invalid @enderror"
                    data-placeholder="Sin asignar"
                    @disabled(! $canChangeAssignments)
                >
                    <option value="">Sin asignar</option>
                    @foreach ($tutores as $tutor)
                        <option value="{{ $tutor->id }}" @selected((int) old('tutor_id', $expediente->tutor_id ?? 0) === $tutor->id)>
                            {{ $tutor->name }}
                        </option>
                    @endforeach
                </select>
                @error('tutor_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                @if ($isCreating && $canChangeAssignments)
                    <div id="tutor_id_help" class="form-text">Aquí aparecen los perfiles con rol Docente o Estratega. Una estrategia asignada o el rol Coordinador, por sí solos, no agregan a la persona a esta lista. Si el formulario ya estaba abierto cuando cambiaste un rol, vuelve a abrirlo antes de capturar datos.</div>
                @endif
            </div>

            <div class="col-md-4">
                <label for="coordinador_id" class="form-label">Coordinador</label>
                <select
                    name="coordinador_id"
                    id="coordinador_id"
                    class="form-select js-select2 @error('coordinador_id') is-invalid @enderror"
                    data-placeholder="Sin asignar"
                    @disabled(! $canChangeAssignments)
                >
                    <option value="">Sin asignar</option>
                    @foreach ($coordinadores as $coordinador)
                        <option value="{{ $coordinador->id }}" @selected((int) old('coordinador_id', $expediente->coordinador_id ?? 0) === $coordinador->id)>
                            {{ $coordinador->name }}
                        </option>
                    @endforeach
                </select>
                @error('coordinador_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="facilitador_id" class="form-label">Facilitador responsable</label>
                <select
                    name="facilitador_id"
                    id="facilitador_id"
                    class="form-select js-select2 @error('facilitador_id') is-invalid @enderror"
                    @disabled(! $canChangeAssignments || ($isCreating && ! auth()->user()?->hasGlobalExpedienteAccess()))
                >
                    <option value="">Sin asignar</option>
                    @foreach ($facilitadores as $facilitador)
                        <option value="{{ $facilitador->id }}" @selected((int) old('facilitador_id', $isCreating && auth()->user()?->hasRole('alumno') ? auth()->id() : ($expediente->facilitador_id ?? 0)) === $facilitador->id)>
                            {{ $facilitador->name }}
                        </option>
                    @endforeach
                </select>
                @error('facilitador_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="cubiculo_id" class="form-label">Cubículo asignado</label>
                <select
                    name="cubiculo_id"
                    id="cubiculo_id"
                    class="form-select @error('cubiculo_id') is-invalid @enderror"
                    @disabled(! $canChangeCubiculo || $hasLinkedReservations)
                    @required($showNuevaAsignacion && request()->routeIs('expedientes.create') && old('consultorio_reserva') !== null)
                >
                    <option value="">Sin asignar</option>
                    @foreach ($cubiculos as $cubiculo)
                        <option
                            value="{{ $cubiculo->id }}"
                            data-numero="{{ $cubiculo->numero }}"
                            @selected((int) old('cubiculo_id', $expediente->cubiculo_id ?? 0) === $cubiculo->id)
                        >
                            {{ $cubiculo->nombre }}
                        </option>
                    @endforeach
                </select>
                @if ($hasLinkedReservations)
                    <div class="form-text">
                        El cubículo se actualiza al cambiar el horario de su reserva.
                        @role('admin')
                            <a href="{{ route('expedientes.show', $expediente) }}#horarios-asignados">Ir a los horarios asignados</a>
                        @endrole
                    </div>
                @elseif (! $isCreating && blank($expediente->cubiculo_id) && filled($legacyCubiculo))
                    <div class="form-text text-warning">
                        Asignación anterior: Cubículo {{ $legacyCubiculo }}. Pendiente de confirmar en el catálogo unificado.
                    </div>
                @elseif (! $isCreating && ! $canChangeCubiculo)
                    <div class="form-text">Solo Administración o PAPS puede cambiar el cubículo confirmado.</div>
                @endif
                @error('cubiculo_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>

@if ($isPaps)
    <div class="card border shadow-none mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <p class="text-muted text-uppercase small mb-1">Registro vinculado</p>
                    <h6 class="mb-0">Hoja de urgencia</h6>
                </div>
            </div>

            @php($urgencia = old('registro_urgencia', $expediente->registroUrgencia?->toArray() ?? []))

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="registro_urgencia_nivel_riesgo" class="form-label">Nivel de riesgo</label>
                    <select name="registro_urgencia[nivel_riesgo]" id="registro_urgencia_nivel_riesgo" class="form-select @error('registro_urgencia.nivel_riesgo') is-invalid @enderror">
                        <option value="">Sin clasificar</option>
                        <option value="bajo" @selected(data_get($urgencia, 'nivel_riesgo') === 'bajo')>Bajo</option>
                        <option value="medio" @selected(data_get($urgencia, 'nivel_riesgo') === 'medio')>Medio</option>
                        <option value="alto" @selected(data_get($urgencia, 'nivel_riesgo') === 'alto')>Alto</option>
                    </select>
                    @error('registro_urgencia.nivel_riesgo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label for="registro_urgencia_canalizacion" class="form-label d-block">Canalización inmediata</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="registro_urgencia_canalizacion" name="registro_urgencia[canalizacion_inmediata]" value="1" @checked((bool) data_get($urgencia, 'canalizacion_inmediata'))>
                        <label class="form-check-label" for="registro_urgencia_canalizacion">Sí</label>
                    </div>
                    @error('registro_urgencia.canalizacion_inmediata')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label for="registro_urgencia_motivo" class="form-label">Motivo de urgencia</label>
                    <textarea name="registro_urgencia[motivo]" id="registro_urgencia_motivo" rows="3" class="form-control @error('registro_urgencia.motivo') is-invalid @enderror">{{ data_get($urgencia, 'motivo') }}</textarea>
                    @error('registro_urgencia.motivo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label for="registro_urgencia_observaciones" class="form-label">Observaciones</label>
                    <textarea name="registro_urgencia[observaciones]" id="registro_urgencia_observaciones" rows="3" class="form-control @error('registro_urgencia.observaciones') is-invalid @enderror">{{ data_get($urgencia, 'observaciones') }}</textarea>
                    @error('registro_urgencia.observaciones')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>
@endif

<div class="card border shadow-none mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <p class="text-muted text-uppercase small mb-1">Identidad</p>
                <h6 class="mb-0">Datos del Alumno</h6>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <label for="genero" class="form-label">Género</label>
                <select
                    name="genero"
                    id="genero"
                    class="form-select js-select2 @error('genero') is-invalid @enderror"
                    data-placeholder="Seleccione"
                >
                    <option value="">Seleccione</option>
                    @foreach ($generos as $value => $label)
                        <option value="{{ $value }}" @selected(old('genero', $expediente->genero ?? '') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('genero')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="estado_civil" class="form-label">Estado civil</label>
                <select
                    name="estado_civil"
                    id="estado_civil"
                    class="form-select js-select2 @error('estado_civil') is-invalid @enderror"
                    data-placeholder="Seleccione"
                >
                    <option value="">Seleccione</option>
                    @foreach ($estadosCiviles as $value => $label)
                        <option value="{{ $value }}" @selected(old('estado_civil', $expediente->estado_civil ?? '') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('estado_civil')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="fecha_nacimiento" class="form-label">Fecha de nacimiento</label>
                <input
                    type="date"
                    name="fecha_nacimiento"
                    id="fecha_nacimiento"
                    value="{{ old('fecha_nacimiento', optional($expediente->fecha_nacimiento ?? null)->format('Y-m-d')) }}"
                    class="form-control js-flatpickr @error('fecha_nacimiento') is-invalid @enderror"
                    data-max-date="today"
                >
                @error('fecha_nacimiento')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="lugar_nacimiento" class="form-label">Lugar de nacimiento</label>
                <input
                    type="text"
                    name="lugar_nacimiento"
                    id="lugar_nacimiento"
                    value="{{ old('lugar_nacimiento', $expediente->lugar_nacimiento ?? '') }}"
                    class="form-control @error('lugar_nacimiento') is-invalid @enderror"
                    maxlength="120"
                >
                @error('lugar_nacimiento')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="ocupacion" class="form-label">Ocupación</label>
                <input
                    type="text"
                    name="ocupacion"
                    id="ocupacion"
                    value="{{ old('ocupacion', $expediente->ocupacion ?? '') }}"
                    class="form-control @error('ocupacion') is-invalid @enderror"
                    maxlength="120"
                >
                @error('ocupacion')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="escolaridad" class="form-label">Escolaridad</label>
                <input
                    type="text"
                    name="escolaridad"
                    id="escolaridad"
                    value="{{ old('escolaridad', $expediente->escolaridad ?? '') }}"
                    class="form-control @error('escolaridad') is-invalid @enderror"
                    maxlength="120"
                >
                @error('escolaridad')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>

<div class="card border shadow-none mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <p class="text-muted text-uppercase small mb-1">Domicilio</p>
                <h6 class="mb-0">Ubicación y contacto</h6>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label for="domicilio_calle" class="form-label">Calle y número</label>
                <input
                    type="text"
                    name="domicilio_calle"
                    id="domicilio_calle"
                    value="{{ old('domicilio_calle', $expediente->domicilio_calle ?? '') }}"
                    class="form-control @error('domicilio_calle') is-invalid @enderror"
                    maxlength="1000"
                >
                <div class="form-text">Incluye el número exterior e interior si aplica.</div>
                @error('domicilio_calle')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="colonia" class="form-label">Colonia</label>
                <input
                    type="text"
                    name="colonia"
                    id="colonia"
                    value="{{ old('colonia', $expediente->colonia ?? '') }}"
                    class="form-control @error('colonia') is-invalid @enderror"
                    maxlength="120"
                >
                @error('colonia')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="delegacion_municipio" class="form-label">Municipio / Delegación</label>
                <input
                    type="text"
                    name="delegacion_municipio"
                    id="delegacion_municipio"
                    value="{{ old('delegacion_municipio', $expediente->delegacion_municipio ?? '') }}"
                    class="form-control @error('delegacion_municipio') is-invalid @enderror"
                    maxlength="120"
                >
                @error('delegacion_municipio')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="entidad" class="form-label">Entidad federativa</label>
                <input
                    type="text"
                    name="entidad"
                    id="entidad"
                    value="{{ old('entidad', $expediente->entidad ?? '') }}"
                    class="form-control @error('entidad') is-invalid @enderror"
                    maxlength="120"
                >
                @error('entidad')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="telefono_principal" class="form-label">Teléfono principal</label>
                <input
                    type="tel"
                    name="telefono_principal"
                    id="telefono_principal"
                    value="{{ old('telefono_principal', $expediente->telefono_principal ?? '') }}"
                    class="form-control @error('telefono_principal') is-invalid @enderror"
                    maxlength="25"
                    inputmode="tel"
                >
                @error('telefono_principal')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>

<div class="card border shadow-none mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <p class="text-muted text-uppercase small mb-1">Red de apoyo</p>
                <h6 class="mb-0">Contacto de emergencia y médico de referencia</h6>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <h6 class="text-muted text-uppercase small mb-3">Contacto de emergencia</h6>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="contacto_emergencia_nombre" class="form-label">Nombre completo</label>
                        <input
                            type="text"
                            name="contacto_emergencia_nombre"
                            id="contacto_emergencia_nombre"
                            value="{{ old('contacto_emergencia_nombre', $expediente->contacto_emergencia_nombre ?? '') }}"
                            class="form-control @error('contacto_emergencia_nombre') is-invalid @enderror"
                            maxlength="150"
                        >
                        @error('contacto_emergencia_nombre')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="contacto_emergencia_parentesco" class="form-label">Parentesco</label>
                        <input
                            type="text"
                            name="contacto_emergencia_parentesco"
                            id="contacto_emergencia_parentesco"
                            value="{{ old('contacto_emergencia_parentesco', $expediente->contacto_emergencia_parentesco ?? '') }}"
                            class="form-control @error('contacto_emergencia_parentesco') is-invalid @enderror"
                            maxlength="120"
                        >
                        @error('contacto_emergencia_parentesco')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="contacto_emergencia_correo" class="form-label">Correo electrónico</label>
                        <input
                            type="email"
                            name="contacto_emergencia_correo"
                            id="contacto_emergencia_correo"
                            value="{{ old('contacto_emergencia_correo', $expediente->contacto_emergencia_correo ?? '') }}"
                            class="form-control @error('contacto_emergencia_correo') is-invalid @enderror"
                            maxlength="150"
                        >
                        @error('contacto_emergencia_correo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="contacto_emergencia_telefono" class="form-label">Teléfono</label>
                        <input
                            type="tel"
                            name="contacto_emergencia_telefono"
                            id="contacto_emergencia_telefono"
                            value="{{ old('contacto_emergencia_telefono', $expediente->contacto_emergencia_telefono ?? '') }}"
                            class="form-control @error('contacto_emergencia_telefono') is-invalid @enderror"
                            maxlength="25"
                            inputmode="tel"
                        >
                        @error('contacto_emergencia_telefono')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="contacto_emergencia_horario" class="form-label">Horario de localización</label>
                        <input
                            type="text"
                            name="contacto_emergencia_horario"
                            id="contacto_emergencia_horario"
                            value="{{ old('contacto_emergencia_horario', $expediente->contacto_emergencia_horario ?? '') }}"
                            class="form-control @error('contacto_emergencia_horario') is-invalid @enderror"
                            maxlength="120"
                        >
                        @error('contacto_emergencia_horario')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <h6 class="text-muted text-uppercase small mb-3">Médico de referencia</h6>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="medico_referencia_nombre" class="form-label">Nombre completo</label>
                        <input
                            type="text"
                            name="medico_referencia_nombre"
                            id="medico_referencia_nombre"
                            value="{{ old('medico_referencia_nombre', $expediente->medico_referencia_nombre ?? '') }}"
                            class="form-control @error('medico_referencia_nombre') is-invalid @enderror"
                            maxlength="150"
                        >
                        @error('medico_referencia_nombre')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="medico_referencia_correo" class="form-label">Correo electrónico</label>
                        <input
                            type="email"
                            name="medico_referencia_correo"
                            id="medico_referencia_correo"
                            value="{{ old('medico_referencia_correo', $expediente->medico_referencia_correo ?? '') }}"
                            class="form-control @error('medico_referencia_correo') is-invalid @enderror"
                            maxlength="150"
                        >
                        @error('medico_referencia_correo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="medico_referencia_telefono" class="form-label">Teléfono</label>
                        <input
                            type="tel"
                            name="medico_referencia_telefono"
                            id="medico_referencia_telefono"
                            value="{{ old('medico_referencia_telefono', $expediente->medico_referencia_telefono ?? '') }}"
                            class="form-control @error('medico_referencia_telefono') is-invalid @enderror"
                            maxlength="25"
                            inputmode="tel"
                        >
                        @error('medico_referencia_telefono')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border shadow-none mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <p class="text-muted text-uppercase small mb-1">Nota clínica</p>
                <h6 class="mb-0">Motivo de la consulta / Nota de ingreso</h6>
            </div>
            @if ($alertaActiva)
                <span class="badge bg-danger rounded-pill">Alerta activa</span>
            @endif
        </div>

        <div class="row g-3">
            <div class="col-12">
                <div class="motivo-alert-wrapper">
                    @if ($alertaActiva)
                        <span class="badge bg-danger motivo-alert-badge">Alerta al ingreso</span>
                    @endif
                    <textarea
                        name="motivo_consulta"
                        id="motivo_consulta"
                        class="form-control @error('motivo_consulta') is-invalid @enderror"
                        rows="5"
                        placeholder="Describe el motivo de consulta y hallazgos relevantes"
                    >{{ old('motivo_consulta', $expediente->motivo_consulta ?? '') }}</textarea>
                    @error('motivo_consulta')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-text">Esta nota se muestra en el resumen del expediente.</div>
            </div>

            <div class="col-12">
                <label for="alerta_ingreso" class="form-label">Alerta al ingreso (opcional)</label>
                <textarea
                    name="alerta_ingreso"
                    id="alerta_ingreso"
                    class="form-control @error('alerta_ingreso') is-invalid @enderror"
                    rows="2"
                    placeholder="Describe cualquier situación que deba resaltar el equipo clínico"
                >{{ old('alerta_ingreso', $expediente->alerta_ingreso ?? '') }}</textarea>
                @error('alerta_ingreso')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>

@if ($showNuevaAsignacion && request()->routeIs('expedientes.create'))
    <div class="card border shadow-none mb-4">
        <div class="card-body">
            <div class="mb-4">
                <h6 class="mb-2">Nueva asignación</h6>
                <label class="form-check">
                    <input type="checkbox" class="form-check-input" id="expediente-reservar-horario" @checked(old('consultorio_reserva') !== null)>
                    Reservar fechas y horario para este expediente
                </label>
                <div class="form-text">Déjalo sin marcar para guardar el expediente sin crear una reserva.</div>
            </div>

            <fieldset id="expediente-datos-reserva" @if(old('consultorio_reserva') === null) disabled hidden @endif>
                <legend class="visually-hidden">Datos de la reserva</legend>
                <div class="row g-3">
                    @include('consultorios.partials.fechas-asignacion', ['nested' => true])
                    <div class="col-md-2">
                        <label class="form-label">Inicio</label>
                        <input type="time" class="form-control" id="expediente-asignacion-hora-inicio" min="07:00" max="22:00" value="{{ old('consultorio_reserva.hora_inicio', '07:00') }}" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Fin</label>
                        <input type="time" class="form-control" id="expediente-asignacion-hora-fin" min="07:00" max="22:00" value="{{ old('consultorio_reserva.hora_fin', '08:00') }}" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Cubículo</label>
                        <p class="form-control-plaintext small mb-0">Se usará el cubículo seleccionado en Datos institucionales.</p>
                    </div>


                    <div class="col-md-4">
                        <label class="form-label">Estrategia</label>
                        <select class="form-select" id="expediente-asignacion-estrategia" required>
                            <option value="">Selecciona una estrategia</option>
                            @foreach ($estrategiasExpediente as $estrategia)
                                <option value="{{ $estrategia->nombre }}" data-estrategia-id="{{ $estrategia->id }}" @selected(old('consultorio_reserva.estrategia') === $estrategia->nombre)>{{ $estrategia->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Facilitador</label>
                        <select class="form-select" id="expediente-asignacion-usuario-atendido">
                            <option value="">--</option>
                            @foreach ($usuariosActivos as $usuario)
                                <option value="{{ $usuario->id }}" @selected((int) old('consultorio_reserva.usuario_atendido_id') === $usuario->id)>{{ $usuario->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="expediente-asignacion-estratega" class="form-label">Estratega de la reserva de cubículo</label>
                        <select class="form-select" id="expediente-asignacion-estratega">
                            <option value="">--</option>
                            @foreach ($docentesActivos as $usuario)
                                <option value="{{ $usuario->id }}" @selected((int) old('consultorio_reserva.estratega_id') === $usuario->id)>{{ $usuario->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        @if ($errors->has('consultorio_reserva.*'))
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0">
                                    @foreach (collect($errors->get('consultorio_reserva.*'))->flatten() as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <p class="text-muted small mb-0">La asignación se guardará automáticamente al guardar el expediente.</p>
                    </div>
                </div>

                <input type="hidden" name="consultorio_reserva[hora_inicio]" id="expediente-consultorio-hora-inicio" value="{{ old('consultorio_reserva.hora_inicio', '07:00') }}">
                <input type="hidden" name="consultorio_reserva[hora_fin]" id="expediente-consultorio-hora-fin" value="{{ old('consultorio_reserva.hora_fin', '08:00') }}">
                <input type="hidden" name="consultorio_reserva[estrategia]" id="expediente-consultorio-estrategia" value="{{ old('consultorio_reserva.estrategia') }}">
                <input type="hidden" name="consultorio_reserva[usuario_atendido_id]" id="expediente-consultorio-usuario" value="{{ old('consultorio_reserva.usuario_atendido_id') }}">
                <input type="hidden" name="consultorio_reserva[estratega_id]" id="expediente-consultorio-estratega" value="{{ old('consultorio_reserva.estratega_id') }}">
            </fieldset>
        </div>
    </div>
@endif

@include('expedientes.partials.alumno.family-history')

<input type="hidden" name="client_context[browser]" id="expediente_client_context_browser">
<input type="hidden" name="client_context[timezone]" id="expediente_client_context_timezone">

@once
    @push('styles')
        <style>
            .motivo-alert-wrapper {
                position: relative;
            }

            .motivo-alert-wrapper .motivo-alert-badge {
                position: absolute;
                top: 0.75rem;
                right: 0.75rem;
                z-index: 1;
            }
        </style>
        <link rel="stylesheet" href="{{ asset('assets/vendors/select2/select2.min.css') }}">
    @endpush

    @push('scripts')
        <script src="{{ asset('assets/vendors/select2/select2.min.js') }}"></script>
        <script src="{{ asset('assets/vendors/inputmask/jquery.inputmask.min.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.flatpickr) {
                    document.querySelectorAll('.js-flatpickr').forEach(function (element) {
                        const config = {
                            dateFormat: element.dataset.dateFormat || 'Y-m-d',
                        };

                        if (element.dataset.maxDate) {
                            config.maxDate = element.dataset.maxDate;
                        }

                        if (element.dataset.minDate) {
                            config.minDate = element.dataset.minDate;
                        }

                        window.flatpickr(element, config);
                    });
                }

                if (window.jQuery && typeof window.jQuery.fn.select2 === 'function') {
                    const $ = window.jQuery;
                    const $selects = $('.js-select2');

                    if ($selects.length) {
                        $selects.each(function () {
                            const $element = $(this);
                            $element.select2({
                                placeholder: $element.data('placeholder') || 'Seleccione una opción',
                                allowClear: !$element.prop('required'),
                                width: '100%'
                            });
                        });
                    }
                }

                if (window.jQuery && typeof window.jQuery.fn.inputmask === 'function') {
                    const $ = window.jQuery;
                    const maskOptions = {
                        regex: '\\+?(?:\\d{1,3}[\\s.\\-]?)?(?:\\(\\d{2,5}\\)|\\d{2,5})(?:[\\s.\\-]?\\d{2,5})+(?:\\s?(?:ext\\.?|x)\\s?\\d{1,5})?',
                        greedy: false,
                        showMaskOnHover: false,
                        jitMasking: true,
                    };

                    $('#telefono_principal, #contacto_emergencia_telefono, #medico_referencia_telefono').inputmask(maskOptions);
                }

                const browserField = document.getElementById('expediente_client_context_browser');
                const timezoneField = document.getElementById('expediente_client_context_timezone');

                if (browserField) {
                    browserField.value = window.navigator.userAgent;
                }

                if (timezoneField) {
                    try {
                        timezoneField.value = Intl.DateTimeFormat().resolvedOptions().timeZone;
                    } catch (error) {
                        timezoneField.value = 'unknown';
                    }
                }

                const reservationToggle = document.getElementById('expediente-reservar-horario');
                if (!reservationToggle) return;
                const reservationFields = document.getElementById('expediente-datos-reserva');
                const toggleReservation = () => {
                    const assignedRoom = document.getElementById('cubiculo_id');
                    if (assignedRoom) assignedRoom.required = reservationToggle.checked;
                    reservationFields.hidden = !reservationToggle.checked;
                    reservationFields.disabled = !reservationToggle.checked;
                    reservationFields.querySelector('[data-reservation-dates]').dispatchEvent(new Event('change'));
                };
                reservationToggle.addEventListener('change', toggleReservation);
                toggleReservation();

                const startInput = document.getElementById('expediente-asignacion-hora-inicio');
                const endInput = document.getElementById('expediente-asignacion-hora-fin');
                const estrategiaInput = document.getElementById('expediente-asignacion-estrategia');
                const estrategiaExpedienteInput = document.getElementById('estrategia_id');
                const usuarioAtendidoInput = document.getElementById('expediente-asignacion-usuario-atendido');
                const estrategaInput = document.getElementById('expediente-asignacion-estratega');
                const hiddenStartInput = document.getElementById('expediente-consultorio-hora-inicio');
                const hiddenEndInput = document.getElementById('expediente-consultorio-hora-fin');
                const hiddenEstrategiaInput = document.getElementById('expediente-consultorio-estrategia');
                const hiddenUsuarioInput = document.getElementById('expediente-consultorio-usuario');
                const hiddenEstrategaInput = document.getElementById('expediente-consultorio-estratega');

                const syncHiddenAssignmentFields = () => {
                    if (hiddenStartInput) hiddenStartInput.value = startInput?.value ?? '';
                    if (hiddenEndInput) hiddenEndInput.value = endInput?.value ?? '';
                    if (hiddenEstrategiaInput) hiddenEstrategiaInput.value = estrategiaInput?.value ?? '';
                    if (hiddenUsuarioInput) hiddenUsuarioInput.value = usuarioAtendidoInput?.value ?? '';
                    if (hiddenEstrategaInput) hiddenEstrategaInput.value = estrategaInput?.value ?? '';
                };

                startInput?.addEventListener('change', syncHiddenAssignmentFields);
                endInput?.addEventListener('change', syncHiddenAssignmentFields);
                estrategiaInput?.addEventListener('change', () => {
                    if (estrategiaExpedienteInput && !estrategiaExpedienteInput.disabled) {
                        estrategiaExpedienteInput.value = estrategiaInput.selectedOptions[0]?.dataset.estrategiaId ?? '';
                    }
                    syncHiddenAssignmentFields();
                });
                estrategiaExpedienteInput?.addEventListener('change', () => {
                    if (estrategiaInput) {
                        estrategiaInput.value = estrategiaExpedienteInput.selectedOptions[0]?.dataset.nombre ?? '';
                    }
                    syncHiddenAssignmentFields();
                });
                usuarioAtendidoInput?.addEventListener('change', syncHiddenAssignmentFields);
                estrategaInput?.addEventListener('change', syncHiddenAssignmentFields);
                if (estrategiaExpedienteInput?.value && estrategiaInput) {
                    estrategiaInput.value = estrategiaExpedienteInput.selectedOptions[0]?.dataset.nombre ?? '';
                }
                syncHiddenAssignmentFields();
            });
        </script>
    @endpush
@endonce
