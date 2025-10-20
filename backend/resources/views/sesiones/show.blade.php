@extends('layouts.noble')

@section('title', 'Detalle de sesión')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Sesión del {{ optional($sesion->fecha)->format('d/m/Y') ?? '—' }}</h4>
            <p class="text-muted small mb-0">Expediente {{ $expediente->no_control }} · {{ $expediente->paciente }}</p>
        </div>
        <div class="btn-group">
            <a href="{{ route('expedientes.sesiones.index', $expediente) }}" class="btn btn-outline-secondary">Ver sesiones</a>
            <a href="{{ route('expedientes.show', $expediente) }}" class="btn btn-outline-secondary">Volver al expediente</a>
            @can('update', $sesion)
                <a href="{{ route('expedientes.sesiones.edit', [$expediente, $sesion]) }}" class="btn btn-primary">Editar</a>
            @endcan
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <span class="text-muted small d-block">Fecha</span>
                    <span class="fw-semibold">{{ optional($sesion->fecha)->format('Y-m-d') ?? '—' }}</span>
                </div>
                <div class="col-md-3">
                    <span class="text-muted small d-block">Tipo</span>
                    <span class="fw-semibold">{{ $sesion->tipo }}</span>
                </div>
                <div class="col-md-3">
                    <span class="text-muted small d-block">Referencia externa</span>
                    <span class="fw-semibold">{{ $sesion->referencia_externa ?? '—' }}</span>
                </div>
                <div class="col-md-3">
                    <span class="text-muted small d-block">Estado de revisión</span>
                    @php
                        $badge = [
                            'pendiente' => 'bg-secondary',
                            'observada' => 'bg-warning text-dark',
                            'validada' => 'bg-success',
                        ][$sesion->status_revision] ?? 'bg-light text-dark';
                    @endphp
                    <span class="badge {{ $badge }}">{{ ucfirst($sesion->status_revision) }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Registrada por</span>
                    <span class="fw-semibold">{{ $sesion->realizadaPor?->name ?? '—' }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Validada por</span>
                    <span class="fw-semibold">{{ $sesion->validadaPor?->name ?? '—' }}</span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Última actualización</span>
                    <span class="fw-semibold">{{ optional($sesion->updated_at)->diffForHumans() ?? '—' }}</span>
                </div>
            </div>

            <div class="mt-4">
                <h6 class="mb-2">Notas de la sesión</h6>
                <div class="bg-light border rounded p-3 small trix-content">
                    {!! $sesion->nota !!}
                </div>
            </div>
            @if ($sesion->adjuntos->isNotEmpty())
                <div class="mt-4">
                    <h6 class="mb-2">Adjuntos</h6>
                    <ul class="list-group list-group-flush">
                        @foreach ($sesion->adjuntos as $adjunto)
                            <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <a href="{{ $adjunto->url }}" target="_blank" rel="noopener"
                                        class="fw-semibold">{{ $adjunto->nombre_original }}</a>
                                    <div class="text-muted small">
                                        {{ number_format($adjunto->tamano / 1024, 1) }} KB ·
                                        {{ $adjunto->subidoPor?->name ?? 'Desconocido' }} ·
                                        {{ optional($adjunto->created_at)->format('Y-m-d H:i') }}
                                    </div>
                                </div>
                                <a href="{{ $adjunto->url }}" target="_blank" rel="noopener"
                                    class="btn btn-sm btn-outline-secondary">Descargar</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @can('delete', $sesion)
            <div class="card-footer text-end">
                <form action="{{ route('expedientes.sesiones.destroy', [$expediente, $sesion]) }}" method="post"
                    onsubmit="return confirm('¿Deseas eliminar esta sesión? Esta acción no se puede deshacer.');">
                    @csrf
                    @method('delete')
                    <button type="submit" class="btn btn-outline-danger">Eliminar sesión</button>
                </form>
            </div>
        @endcan
    </div>
@endsection

