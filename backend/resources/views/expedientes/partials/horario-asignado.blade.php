<div class="fw-semibold">
    <time datetime="{{ $reserva->fecha->toDateString() }}">
        {{ ucfirst($reserva->fecha->locale('es')->translatedFormat('l d/m/Y')) }}
    </time>
    · {{ substr($reserva->hora_inicio, 0, 5) }}–{{ substr($reserva->hora_fin, 0, 5) }}
</div>
@if (($mostrarCubiculo ?? false) || ! $reserva->cubiculo || (int) $reserva->cubiculo->id !== (int) $expediente->cubiculo_id)
    <div class="small text-muted">{{ $reserva->cubiculo?->nombre ?? 'Cubículo '.$reserva->consultorio_numero }}</div>
@endif
@if ($reserva->solicitud_pendiente ?? false)
    <span class="badge bg-warning">Solicitud pendiente</span>
@elseif (! $expediente->trashed() && auth()->user()?->hasRole('admin'))
    <a class="btn btn-sm btn-outline-primary mt-1" href="{{ route('consultorios.edit', ['reserva' => $reserva, 'origen' => 'expediente']) }}">
        Cambiar horario o cubículo
    </a>
@endif
