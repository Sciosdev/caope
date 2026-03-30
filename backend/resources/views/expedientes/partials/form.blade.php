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
@php($isPaps = auth()->user()?->hasRole('paps'))

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
                <label for="paciente" class="form-label">Consultante</label>
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
                <label for="tutor_id" class="form-label">Estratega</label>
                <select
                    name="tutor_id"
                    id="tutor_id"
                    class="form-select js-select2 @error('tutor_id') is-invalid @enderror"
                    data-placeholder="Sin asignar"
                    @disabled($isPaps)
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
            </div>

            <div class="col-md-4">
                <label for="coordinador_id" class="form-label">Estratega</label>
                <select
                    name="coordinador_id"
                    id="coordinador_id"
                    class="form-select js-select2 @error('coordinador_id') is-invalid @enderror"
                    data-placeholder="Sin asignar"
                    @disabled($isPaps)
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

            @if (! $isPaps)
                <div class="col-md-4">
                    <label for="resumen_clinico_facilitador" class="form-label">Facilitador (Alumno responsable)</label>
                    <input
                        type="text"
                        name="resumen_clinico[facilitador]"
                        id="resumen_clinico_facilitador"
                        value="{{ old('resumen_clinico.facilitador', data_get($expediente->resumen_clinico ?? [], 'facilitador', auth()->user()?->name)) }}"
                        class="form-control @error('resumen_clinico.facilitador') is-invalid @enderror"
                        maxlength="150"
                        readonly
                    >
                    @error('resumen_clinico.facilitador')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            @endif

            <div class="col-md-4">
                <label for="resumen_clinico_cubiculo" class="form-label">Cubículo asignado</label>
                <select
                    name="resumen_clinico[cubiculo]"
                    id="resumen_clinico_cubiculo"
                    class="form-select @error('resumen_clinico.cubiculo') is-invalid @enderror"
                    @disabled(! $isCreating)
                >
                    <option value="">Sin asignar</option>
                    @foreach ($cubiculos as $cubiculo)
                        <option value="{{ $cubiculo->numero }}" @selected((string) old('resumen_clinico.cubiculo', data_get($expediente->resumen_clinico ?? [], 'cubiculo')) === (string) $cubiculo->numero)>
                            Cubículo {{ $cubiculo->numero }}
                        </option>
                    @endforeach
                </select>
                @if (! $isCreating)
                    <div class="form-text">El cubículo solo puede asignarse durante la creación del expediente.</div>
                @endif
                @error('resumen_clinico.cubiculo')
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
                <h6 class="mb-0">Datos del paciente</h6>
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

@if ($isPaps && request()->routeIs('expedientes.create'))
    <div class="card border shadow-none mb-4">
        <div class="card-body">
            <div class="mb-4">
                <h6 class="mb-0">Nueva asignación</h6>
            </div>

            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Día</label>
                    <input type="date" class="form-control" id="expediente-asignacion-fecha" value="{{ now()->toDateString() }}" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label d-block">Tipo de registro</label>
                    <div class="d-flex gap-3 mt-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="expediente_modo_repeticion" id="expediente-modo-repeticion-unica" value="unica" checked>
                            <label class="form-check-label" for="expediente-modo-repeticion-unica">Fecha única</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="expediente_modo_repeticion" id="expediente-modo-repeticion-semanal" value="semanal">
                            <label class="form-check-label" for="expediente-modo-repeticion-semanal">Repetición semanal</label>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 d-none" id="expediente-repeticion-config">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Desde</label>
                            <input type="date" id="expediente-repeticion-fecha-inicio" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Hasta</label>
                            <input type="date" id="expediente-repeticion-fecha-fin" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Día hábil</label>
                            <select class="form-select" id="expediente-repeticion-dia-semana">
                                <option value="">Selecciona un día</option>
                                @foreach ([1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'] as $dayNumber => $dayLabel)
                                    <option value="{{ $dayNumber }}">{{ $dayLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Inicio</label>
                    <input type="time" class="form-control" id="expediente-asignacion-hora-inicio" min="07:00" max="22:00" value="07:00" required>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Fin</label>
                    <input type="time" class="form-control" id="expediente-asignacion-hora-fin" min="07:00" max="22:00" value="08:00" required>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Consultorio</label>
                    <select class="form-select" id="expediente-asignacion-consultorio" required>
                        @foreach ($consultoriosActivos as $consultorio)
                            <option value="{{ $consultorio->numero }}">{{ $consultorio->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Cubículo</label>
                    <select class="form-select" id="expediente-asignacion-cubiculo" required>
                        @foreach ($cubiculosActivos as $cubiculo)
                            <option value="{{ $cubiculo->numero }}">{{ $cubiculo->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Estrategia</label>
                    <select class="form-select" id="expediente-asignacion-estrategia" required>
                        <option value="">Selecciona una estrategia</option>
                        @foreach ($estrategiasActivas as $estrategia)
                            <option value="{{ $estrategia->nombre }}">{{ $estrategia->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Usuario atendido</label>
                    <select class="form-select" id="expediente-asignacion-usuario-atendido">
                        <option value="">--</option>
                        @foreach ($usuariosActivos as $usuario)
                            <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Estratega</label>
                    <select class="form-select" id="expediente-asignacion-estratega">
                        <option value="">--</option>
                        @foreach ($docentesActivos as $usuario)
                            <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <div id="expediente-asignacion-alerta" class="alert d-none mb-3" role="alert"></div>
                    <button type="button" class="btn btn-primary" id="expediente-asignar-espacio">Asignar espacio</button>
                </div>
            </div>
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

                const assignmentButton = document.getElementById('expediente-asignar-espacio');
                const assignmentAlert = document.getElementById('expediente-asignacion-alerta');

                if (!assignmentButton || !assignmentAlert) {
                    return;
                }

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const availabilityEndpoint = @json(route('consultorios.availability'));
                const storeEndpoint = @json(route('consultorios.store'));
                const dateInput = document.getElementById('expediente-asignacion-fecha');
                const startInput = document.getElementById('expediente-asignacion-hora-inicio');
                const endInput = document.getElementById('expediente-asignacion-hora-fin');
                const consultorioInput = document.getElementById('expediente-asignacion-consultorio');
                const cubiculoInput = document.getElementById('expediente-asignacion-cubiculo');
                const estrategiaInput = document.getElementById('expediente-asignacion-estrategia');
                const usuarioAtendidoInput = document.getElementById('expediente-asignacion-usuario-atendido');
                const estrategaInput = document.getElementById('expediente-asignacion-estratega');
                const repeatConfig = document.getElementById('expediente-repeticion-config');
                const repeatStartInput = document.getElementById('expediente-repeticion-fecha-inicio');
                const repeatEndInput = document.getElementById('expediente-repeticion-fecha-fin');
                const repeatDayInput = document.getElementById('expediente-repeticion-dia-semana');
                const modeInputs = Array.from(document.querySelectorAll('input[name="expediente_modo_repeticion"]'));

                const getMode = () => modeInputs.find((input) => input.checked)?.value ?? 'unica';
                const toMinutes = (time) => {
                    if (!time || !time.includes(':')) {
                        return 0;
                    }

                    const [hours, minutes] = time.split(':').map(Number);
                    return (hours * 60) + minutes;
                };

                const showAssignmentAlert = (message, type = 'warning') => {
                    assignmentAlert.className = `alert alert-${type} mb-3`;
                    assignmentAlert.textContent = message;
                };

                const hideAssignmentAlert = () => {
                    assignmentAlert.className = 'alert d-none mb-3';
                    assignmentAlert.textContent = '';
                };

                const toggleRepeatConfig = () => {
                    if (!repeatConfig || !repeatStartInput || !repeatEndInput || !repeatDayInput) {
                        return;
                    }

                    const isWeekly = getMode() === 'semanal';
                    repeatConfig.classList.toggle('d-none', !isWeekly);
                    repeatStartInput.required = isWeekly;
                    repeatEndInput.required = isWeekly;
                    repeatDayInput.required = isWeekly;
                };

                const findOverlap = (items, start, end, cubiculo) => {
                    const startMinutes = toMinutes(start);
                    const endMinutes = toMinutes(end);

                    return items.find((item) => {
                        if (Number(item.cubiculo_numero) !== Number(cubiculo)) {
                            return false;
                        }

                        const itemStartMinutes = toMinutes(String(item.hora_inicio).slice(0, 5));
                        const itemEndMinutes = toMinutes(String(item.hora_fin).slice(0, 5));
                        return itemStartMinutes < endMinutes && itemEndMinutes > startMinutes;
                    });
                };

                const checkAvailability = async () => {
                    hideAssignmentAlert();

                    if (!dateInput?.value || !consultorioInput?.value || !cubiculoInput?.value || !startInput?.value || !endInput?.value) {
                        return true;
                    }

                    if (startInput.value >= endInput.value) {
                        showAssignmentAlert('La hora de inicio debe ser menor a la hora de fin.');
                        return false;
                    }

                    if (startInput.value < '07:00' || endInput.value > '22:00') {
                        showAssignmentAlert('El horario permitido es de 07:00 a 22:00.');
                        return false;
                    }

                    try {
                        const params = new URLSearchParams({
                            fecha: dateInput.value,
                            consultorio_numero: consultorioInput.value,
                        });
                        const response = await fetch(`${availabilityEndpoint}?${params.toString()}`, {
                            headers: {
                                'Accept': 'application/json',
                            },
                        });

                        if (!response.ok) {
                            return true;
                        }

                        const data = await response.json();
                        const overlap = findOverlap(data.reservas ?? [], startInput.value, endInput.value, cubiculoInput.value);

                        if (overlap) {
                            showAssignmentAlert(`⚠️ El Consultorio ${consultorioInput.value}, Cubículo ${cubiculoInput.value} ya está ocupado de ${String(overlap.hora_inicio).slice(0, 5)} a ${String(overlap.hora_fin).slice(0, 5)}.`);
                            return false;
                        }
                    } catch (error) {
                        console.error(error);
                    }

                    return true;
                };

                const parseServerError = async (response) => {
                    const data = await response.json().catch(() => null);
                    const errors = data?.errors ?? {};
                    const firstError = Object.values(errors).flat()[0];

                    if (firstError) {
                        return firstError;
                    }

                    return data?.message || 'No se pudo registrar la asignación.';
                };

                modeInputs.forEach((input) => input.addEventListener('change', toggleRepeatConfig));
                dateInput?.addEventListener('change', checkAvailability);
                startInput?.addEventListener('change', checkAvailability);
                endInput?.addEventListener('change', checkAvailability);
                consultorioInput?.addEventListener('change', checkAvailability);
                cubiculoInput?.addEventListener('change', checkAvailability);
                toggleRepeatConfig();

                assignmentButton.addEventListener('click', async () => {
                    hideAssignmentAlert();

                    if (!estrategiaInput?.value) {
                        showAssignmentAlert('Selecciona una estrategia para registrar la asignación.');
                        return;
                    }

                    const isAvailable = await checkAvailability();
                    if (!isAvailable) {
                        return;
                    }

                    assignmentButton.disabled = true;

                    const payload = {
                        modo_repeticion: getMode(),
                        fecha: dateInput?.value,
                        fecha_inicio_repeticion: repeatStartInput?.value,
                        fecha_fin_repeticion: repeatEndInput?.value,
                        dias_semana: repeatDayInput?.value ? [repeatDayInput.value] : [],
                        hora_inicio: startInput?.value,
                        hora_fin: endInput?.value,
                        consultorio_numero: consultorioInput?.value,
                        cubiculo_numero: cubiculoInput?.value,
                        estrategia: estrategiaInput?.value,
                        usuario_atendido_id: usuarioAtendidoInput?.value,
                        estratega_id: estrategaInput?.value,
                    };

                    try {
                        const response = await fetch(storeEndpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify(payload),
                        });

                        if (!response.ok) {
                            const message = await parseServerError(response);
                            showAssignmentAlert(message, 'danger');
                            return;
                        }

                        showAssignmentAlert('Asignación registrada correctamente.', 'success');
                    } catch (error) {
                        console.error(error);
                        showAssignmentAlert('Ocurrió un error inesperado al registrar la asignación.', 'danger');
                    } finally {
                        assignmentButton.disabled = false;
                    }
                });
            });
        </script>
    @endpush
@endonce
