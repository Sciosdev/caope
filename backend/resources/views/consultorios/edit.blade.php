<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
        <li class="breadcrumb-item"><a href="{{ route('consultorios.index') }}">Cubículos</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar reserva</li>
    @endsection

    <div class="card">
        <div class="card-header">{{ ($editContext ?? null) === 'expediente' ? 'Cambiar horario o cubículo del expediente' : 'Modificar asignación' }}</div>
        <div class="card-body">
            @php
                $isAdmin = auth()->user()?->hasRole('admin') ?? false;
                $fromExpediente = ($editContext ?? null) === 'expediente';
                $cubiculoNumeroSeleccionado = (int) old('consultorio_numero', $reserva->consultorio_numero);
                $cubiculoSeleccionadoDisponible = $consultoriosActivos->contains(
                    fn ($cubiculo) => (int) $cubiculo->numero === $cubiculoNumeroSeleccionado
                );
                $cubiculoActualNombre = trim((string) ($reserva->cubiculo?->nombre ?? ''));
                $cubiculoActualNombre = $cubiculoActualNombre !== '' ? $cubiculoActualNombre : 'Cubículo sin catálogo';
            @endphp
            @unless($isAdmin)
                <div class="alert alert-warning">
                    Esta edición se enviará como solicitud. El administrador general será quien aplique el cambio.
                </div>
            @endunless
            @if ($fromExpediente)
                <div class="alert alert-info">
                    <strong>Expediente {{ $reserva->expediente?->no_control }}</strong>
                    <div class="small mt-1">
                        Esta acción reprograma únicamente la reserva #{{ $reserva->id }}. El vínculo con el expediente,
                        la estrategia y las personas asignadas se conservarán sin cambios.
                    </div>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ $isAdmin ? route('consultorios.update', $fromExpediente ? ['reserva' => $reserva, 'origen' => 'expediente'] : $reserva) : route('consultorios.request-update', $reserva) }}" class="row g-3">
                @csrf @method('PUT')
                @if ($fromExpediente)
                    <input type="hidden" name="edit_context" value="expediente">
                @endif
                <div class="col-md-2"><label class="form-label">Día</label><input type="date" name="fecha" class="form-control" value="{{ old('fecha', $reserva->fecha->format('Y-m-d')) }}" required></div>
                <div class="col-md-2"><label class="form-label">Inicio</label><input type="time" name="hora_inicio" class="form-control" value="{{ old('hora_inicio', substr($reserva->hora_inicio,0,5)) }}" required></div>
                <div class="col-md-2"><label class="form-label">Fin</label><input type="time" name="hora_fin" class="form-control" value="{{ old('hora_fin', substr($reserva->hora_fin,0,5)) }}" required></div>
                <div class="col-md-4">
                    <label class="form-label" for="consultorio_numero">Cubículo</label>
                    <select name="consultorio_numero" id="consultorio_numero" class="form-select" required>
                        @unless ($cubiculoSeleccionadoDisponible)
                            <option value="" selected disabled>
                                {{ $cubiculoActualNombre }} · Núm. {{ $reserva->consultorio_numero }} (no disponible; selecciona otro)
                            </option>
                        @endunless
                        @foreach($consultoriosActivos as $cubiculo)
                            <option value="{{ $cubiculo->numero }}" @selected($cubiculoSeleccionadoDisponible && $cubiculoNumeroSeleccionado === (int) $cubiculo->numero)>
                                {{ trim((string) $cubiculo->nombre) !== '' ? $cubiculo->nombre : 'Cubículo sin nombre' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if ($fromExpediente)
                    <div class="col-12">
                        <dl class="row small mb-0 border rounded p-3 bg-light">
                            <dt class="col-md-2">Estrategia</dt>
                            <dd class="col-md-10">{{ $reserva->estrategia }}</dd>
                            <dt class="col-md-2">Facilitador</dt>
                            <dd class="col-md-4">{{ $reserva->usuarioAtendido?->name ?? 'Sin asignar' }}</dd>
                            <dt class="col-md-2">Estratega</dt>
                            <dd class="col-md-4">{{ $reserva->estratega?->name ?? 'Sin asignar' }}</dd>
                        </dl>
                    </div>
                @else
                    <div class="col-md-8"><label class="form-label">Estrategia</label><select name="estrategia" class="form-select" required><option value="">Selecciona una estrategia</option>@foreach($estrategiasActivas as $estrategia)<option value="{{ $estrategia->nombre }}" @selected(old('estrategia', $reserva->estrategiaCatalogo?->nombre ?? $reserva->estrategia)===$estrategia->nombre)>{{ $estrategia->nombre }}</option>@endforeach</select></div>
                    <div class="col-md-12">
                        <label for="expediente_id" class="form-label">Expediente vinculado</label>
                        <select name="expediente_id" id="expediente_id" class="form-select @error('expediente_id') is-invalid @enderror" aria-describedby="expediente_id_help">
                            <option value="" @selected(old('expediente_id', $reserva->expediente_id) === null || old('expediente_id', $reserva->expediente_id) === '')>Sin expediente vinculado</option>
                            @foreach ($expedientes as $expediente)
                                <option value="{{ $expediente->id }}" @selected((string) old('expediente_id', $reserva->expediente_id) === (string) $expediente->id)>
                                    {{ $expediente->no_control }} — {{ $expediente->paciente }}
                                </option>
                            @endforeach
                        </select>
                        <div id="expediente_id_help" class="form-text">Selecciona el expediente real al que corresponde la reserva. No se vincula automáticamente por facilitador, estrategia u horario.</div>
                        @error('expediente_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4"><label class="form-label">Usuario</label><select name="usuario_atendido_id" class="form-select"><option value="">--</option>@foreach($usuarios as $u)<option value="{{ $u->id }}" @selected((int) old('usuario_atendido_id', $reserva->usuario_atendido_id)===$u->id)>{{ $u->name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">Estratega</label><select name="estratega_id" class="form-select"><option value="">--</option>@foreach($docentes as $u)<option value="{{ $u->id }}" @selected((int) old('estratega_id', $reserva->estratega_id)===$u->id)>{{ $u->name }}</option>@endforeach</select></div>
                @endif
                
                <div class="col-12 d-flex gap-2">
                    @if($isAdmin)
                        <button class="btn btn-primary">Guardar cambios</button>
                    @else
                        <button class="btn btn-primary" onclick="return confirm('Se enviará la solicitud de edición y el administrador general revisará este cambio.');">
                            Enviar solicitud de edición
                        </button>
                    @endif
                    <a class="btn btn-outline-secondary" href="{{ $fromExpediente ? route('expedientes.show', $reserva->expediente_id) : route('consultorios.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
