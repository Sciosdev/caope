<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
        <li class="breadcrumb-item active" aria-current="page">Herramientas · Solicitudes de consultorios</li>
    @endsection

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Solicitudes pendientes de consultorios</h4>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            @if ($solicitudesPendientes instanceof \Illuminate\Pagination\LengthAwarePaginator && $solicitudesPendientes->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Tipo</th>
                                <th>Reserva</th>
                                <th>Solicitado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($solicitudesPendientes as $solicitud)
                                <tr>
                                    <td>{{ $solicitud->requestedBy?->name ?? 'Usuario' }}</td>
                                    <td>
                                        <span class="badge {{ $solicitud->tipo === 'baja' ? 'bg-danger-subtle text-danger-emphasis border border-danger-subtle' : 'bg-primary-subtle text-primary-emphasis border border-primary-subtle' }}">
                                            {{ $solicitud->tipo === 'baja' ? 'Baja' : 'Edición' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($solicitud->reserva)
                                            #{{ $solicitud->consultorio_reserva_id }}
                                            · {{ $solicitud->reserva->fecha?->format('Y-m-d') }}
                                            · {{ substr((string) $solicitud->reserva->hora_inicio, 0, 5) }}-{{ substr((string) $solicitud->reserva->hora_fin, 0, 5) }}
                                            · Cons. {{ $solicitud->reserva->consultorio_numero }}
                                        @else
                                            #{{ $solicitud->consultorio_reserva_id }} (registro no disponible)
                                        @endif
                                    </td>
                                    <td>{{ $solicitud->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <div class="d-flex justify-content-end gap-2">
                                            @if ($solicitud->tipo === 'edicion' && $solicitud->reserva)
                                                <a href="{{ route('consultorios.edit', $solicitud->reserva) }}" class="btn btn-sm btn-outline-primary">Editar reserva</a>
                                            @endif
                                            <form method="POST" action="{{ route('admin.consultorios.solicitudes.approve', $solicitud) }}" onsubmit="return confirm('¿Aprobar esta solicitud?');">
                                                @csrf
                                                <button class="btn btn-sm btn-success">Aprobar</button>
                                            </form>
                                        </div>
                                    </td>
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
</x-app-layout>
