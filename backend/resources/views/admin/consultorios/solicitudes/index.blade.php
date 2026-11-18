<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
        <li class="breadcrumb-item active" aria-current="page">Herramientas · Solicitudes de cubículos</li>
    @endsection

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Solicitudes pendientes de cubículos</h4>
    </div>

    @unless ($accionesDisponibles)
        <div class="alert alert-warning">Las decisiones están temporalmente en pausa hasta que termine la actualización. Puedes consultar las solicitudes.</div>
    @endunless
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>No se completó la acción{{ is_numeric(old('_fila')) ? ' sobre la solicitud #'.(int) old('_fila') : '' }}.</strong>
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            @if ($solicitudesPendientes instanceof \Illuminate\Pagination\LengthAwarePaginator && $solicitudesPendientes->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                @if ($accionesDisponibles && auth()->user()?->hasRole('admin'))<th>Acciones</th>@endif
                                <th>Solicitado por</th>
                                <th>Tipo</th>
                                <th>Expediente</th>
                                <th>Persona del expediente</th>
                                <th>Reserva actual</th>
                                <th>Cambio solicitado</th>
                                <th>Fecha de solicitud</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($solicitudesPendientes as $solicitud)
                                @php
                                    $expedienteActual = $solicitud->reserva?->expediente;
                                    $payload = is_array($solicitud->payload) ? $solicitud->payload : [];
                                    $rawExpedienteId = $payload['expediente_id'] ?? null;
                                    $expedienteIdPropuesto = (is_int($rawExpedienteId) || is_string($rawExpedienteId))
                                        && filter_var($rawExpedienteId, FILTER_VALIDATE_INT) !== false && (int) $rawExpedienteId > 0
                                        ? (int) $rawExpedienteId : null;
                                    $expedienteIdActual = $solicitud->reserva?->expediente_id ? (int) $solicitud->reserva->expediente_id : null;
                                    $proponeQuitarVinculo = array_key_exists('expediente_id', $payload) && ($rawExpedienteId === null || $rawExpedienteId === '');
                                    $proponeVinculo = array_key_exists('expediente_id', $payload)
                                        && ($expedienteIdPropuesto !== $expedienteIdActual || (! $proponeQuitarVinculo && $expedienteIdPropuesto === null));
                                    $expedienteVinculado = $proponeVinculo && ! $proponeQuitarVinculo
                                        ? ($expedienteIdPropuesto === null ? null : $expedientesPropuestos->get($expedienteIdPropuesto))
                                        : $expedienteActual;
                                    $puedeVerExpediente = $expedienteVinculado && auth()->user()?->can('view', $expedienteVinculado);
                                    $numeroPropuesto = isset($payload['consultorio_numero']) && is_numeric($payload['consultorio_numero'])
                                        ? (int) $payload['consultorio_numero'] : null;
                                    $cubiculoPropuesto = $numeroPropuesto === null ? null : $cubiculosPropuestos->get($numeroPropuesto);
                                @endphp
                                <tr>
                                    @if ($accionesDisponibles && auth()->user()?->hasRole('admin'))
                                        <td>@include('admin.consultorios.solicitudes.acciones')</td>
                                    @endif
                                    <td>{{ $solicitud->requestedBy?->name ?? 'Usuario' }}</td>
                                    <td>
                                        <span class="badge {{ $solicitud->tipo === 'baja' ? 'bg-danger-subtle text-danger-emphasis border border-danger-subtle' : 'bg-primary-subtle text-primary-emphasis border border-primary-subtle' }}">
                                            {{ $solicitud->tipo === 'baja' ? 'Baja' : 'Edición' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($puedeVerExpediente)
                                            <a href="{{ route('expedientes.show', $expedienteVinculado) }}">{{ $expedienteVinculado->no_control }}</a>
                                        @else
                                            <span class="text-muted">{{ $expedienteVinculado || $proponeVinculo ? 'Expediente no disponible' : 'Sin expediente vinculado' }}</span>
                                        @endif
                                        @if ($proponeVinculo)
                                            <div class="small text-muted">{{ $proponeQuitarVinculo ? 'Se propone quitar el vínculo' : 'Vínculo propuesto; pendiente de aprobación' }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($puedeVerExpediente)
                                            @can('viewFullName', $expedienteVinculado)
                                                {{ $expedienteVinculado->paciente }}
                                            @else
                                                {{ $expedienteVinculado->paciente_masked }}
                                            @endcan
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($solicitud->reserva)
                                            #{{ $solicitud->consultorio_reserva_id }}
                                            · {{ $solicitud->reserva->fecha?->format('Y-m-d') }}
                                            · {{ substr((string) $solicitud->reserva->hora_inicio, 0, 5) }}-{{ substr((string) $solicitud->reserva->hora_fin, 0, 5) }}
                                            · {{ trim((string) ($solicitud->reserva->cubiculo?->nombre ?? '')) !== '' ? $solicitud->reserva->cubiculo->nombre : 'Cubículo sin catálogo' }}
                                            · Núm. {{ $solicitud->reserva->consultorio_numero }}
                                        @else
                                            #{{ $solicitud->consultorio_reserva_id }} (registro no disponible)
                                        @endif
                                    </td>
                                    <td>
                                        @if ($solicitud->tipo === 'edicion' && $payload !== [])
                                            <strong>{{ is_string($payload['fecha'] ?? null) ? $payload['fecha'] : 'Fecha no disponible' }}</strong>
                                            · {{ is_string($payload['hora_inicio'] ?? null) ? substr($payload['hora_inicio'], 0, 5) : '—' }}-{{ is_string($payload['hora_fin'] ?? null) ? substr($payload['hora_fin'], 0, 5) : '—' }}
                                            <div class="small text-muted">
                                                {{ trim((string) ($cubiculoPropuesto?->nombre ?? '')) !== '' ? $cubiculoPropuesto->nombre : ($numeroPropuesto ? 'Cubículo núm. '.$numeroPropuesto : 'Cubículo no disponible') }}
                                            </div>
                                        @elseif ($solicitud->tipo === 'baja')
                                            <span class="text-danger">Dar de baja la reserva completa</span>
                                        @else
                                            <span class="text-muted">Sin propuesta de horario registrada</span>
                                        @endif
                                    </td>
                                    <td>{{ $solicitud->created_at?->format('Y-m-d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $solicitudesPendientes->links('pagination::bootstrap-5') }}
            @else
                <p class="text-muted mb-0">No hay solicitudes pendientes.</p>
            @endif
        </div>
    </div>
    @if ($resueltas->isNotEmpty())
        <details class="mt-3">
            <summary>Decisiones recientes (últimas 20)</summary>
            <ul class="mt-2">
                @foreach ($resueltas as $resuelta)
                    <li>Solicitud #{{ $resuelta->id }} · {{ $resuelta->tipo === 'baja' ? 'Baja' : 'Edición' }}
                        · {{ $resuelta->updated_at?->format('d/m/Y H:i') }} · {{ ucfirst($resuelta->resolucion['decision']) }}
                        @if ($resuelta->resolucion['reserva_id']) · Reserva #{{ $resuelta->resolucion['reserva_id'] }} @endif
                        @if ($resuelta->resolucion['motivo']) · {{ $resuelta->resolucion['motivo'] }} @endif
                        @if ($resuelta->resolucion['decision'] === 'corregida')
                            · Horario aplicado: {{ $resuelta->resolucion['despues']['fecha'] }} · {{ $resuelta->resolucion['despues']['hora_inicio'] }}–{{ $resuelta->resolucion['despues']['hora_fin'] }} · Cubículo núm. {{ $resuelta->resolucion['despues']['consultorio_numero'] }}
                        @endif
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</x-app-layout>
