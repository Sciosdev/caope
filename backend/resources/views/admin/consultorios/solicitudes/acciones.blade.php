@php
    $reserva = $solicitud->reserva;
    $editing = (string) $solicitud->id === old('_fila') && old('_accion') === 'corregir';
    $rejecting = (string) $solicitud->id === old('_fila') && old('_accion') === 'rechazar';
    $field = static function (string $name, $fallback) use ($editing, $payload) {
        $propuesto = $payload[$name] ?? null;
        $value = $editing ? old($name) : (is_scalar($propuesto) ? $propuesto : $fallback);
        return is_scalar($value) ? (string) $value : '';
    };
@endphp
<div class="d-grid gap-2">
    <small>Solicitud #{{ $solicitud->id }}</small>
    @if ($reserva && ($solicitud->tipo === 'baja' || $payload !== []))
        <form method="POST" action="{{ route('admin.consultorios.solicitudes.approve', $solicitud) }}" onsubmit="return confirm('¿Aprobar esta solicitud tal como está?');">
            @csrf
            <input type="hidden" name="_fila" value="{{ $solicitud->id }}">
            <button class="btn btn-sm btn-success">{{ $solicitud->tipo === 'baja' ? 'Aprobar baja' : 'Aprobar tal cual' }}</button>
        </form>
    @endif
    @if ($reserva && $solicitud->tipo === 'edicion')
        <details @if ($editing) open @endif>
            <summary>Corregir y aprobar</summary>
            <form method="POST" action="{{ route('admin.consultorios.solicitudes.correct', $solicitud) }}" class="d-grid gap-2 py-2">
                @csrf
                <input type="hidden" name="_fila" value="{{ $solicitud->id }}">
                <input type="hidden" name="_accion" value="corregir">
                <small>Cambia solo el horario o el cubículo. Se aplicarán los demás datos de la propuesta original.</small>
                <label>Fecha<input class="form-control form-control-sm" name="fecha" type="date" required value="{{ $field('fecha', $reserva->fecha?->toDateString()) }}"></label>
                <label>Desde<input class="form-control form-control-sm" name="hora_inicio" type="time" min="07:00" max="22:00" required value="{{ substr($field('hora_inicio', $reserva->hora_inicio), 0, 5) }}"></label>
                <label>Hasta<input class="form-control form-control-sm" name="hora_fin" type="time" min="07:00" max="22:00" required value="{{ substr($field('hora_fin', $reserva->hora_fin), 0, 5) }}"></label>
                <label>Cubículo
                    <select name="consultorio_numero" class="form-select form-select-sm" required>
                        <option value="">Elige un cubículo activo</option>
                        @foreach ($cubiculosActivos as $room)
                            <option value="{{ $room->numero }}" @selected((string) $room->numero === $field('consultorio_numero', $reserva->consultorio_numero))>{{ $room->nombre }} · núm. {{ $room->numero }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Motivo (sin datos personales)<input name="motivo" class="form-control form-control-sm" required maxlength="300" value="{{ $editing && is_string(old('motivo')) ? old('motivo') : '' }}"></label>
                <button class="btn btn-sm btn-primary">Guardar corrección y aprobar</button>
            </form>
        </details>
    @endif
    <details @if ($rejecting) open @endif>
        <summary>Rechazar solicitud</summary>
        <form method="POST" action="{{ route('admin.consultorios.solicitudes.reject', $solicitud) }}" class="d-grid gap-2 py-2">
            @csrf
            <input type="hidden" name="_fila" value="{{ $solicitud->id }}">
            <input type="hidden" name="_accion" value="rechazar">
            <small>Solo se cierra la solicitud. La reserva permanece igual.</small>
            <label>Motivo (sin datos personales)<input name="motivo" required maxlength="300" class="form-control form-control-sm" value="{{ $rejecting && is_string(old('motivo')) ? old('motivo') : '' }}"></label>
            <button class="btn btn-sm btn-outline-danger">Confirmar rechazo</button>
        </form>
    </details>
</div>
