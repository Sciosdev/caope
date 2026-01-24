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
                    <span class="text-muted small d-block">Hora de atención</span>
                    @php
                        $horaAtencion = $sesion->hora_atencion
                            ? \Illuminate\Support\Carbon::parse($sesion->hora_atencion)
                            : null;
                    @endphp
                    <span class="fw-semibold">{{ optional($horaAtencion)->format('H:i') ?? '—' }}</span>
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

            <div class="mt-4">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="text-muted text-uppercase small mb-2">Estrategia acordada</h6>
                            @if ($sesion->estrategia)
                                <p class="mb-0 small">{!! nl2br(e($sesion->estrategia)) !!}</p>
                            @else
                                <p class="mb-0 text-muted small">Sin estrategia registrada.</p>
                            @endif
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="text-muted text-uppercase small mb-2">Datos de referencia</h6>
                            <dl class="row mb-0 small">
                                <dt class="col-sm-5">Interconsulta</dt>
                                <dd class="col-sm-7">{{ $sesion->interconsulta ?? '—' }}</dd>

                                <dt class="col-sm-5">Especialidad referida</dt>
                                <dd class="col-sm-7">{{ $sesion->especialidad_referida ?? '—' }}</dd>

                                <dt class="col-sm-5">Nombre del facilitador</dt>
                                <dd class="col-sm-7">{{ $sesion->nombre_facilitador ?? '—' }}</dd>

                                <dt class="col-sm-5">Autorización responsable académico (Estratega)</dt>
                                <dd class="col-sm-7">{{ $sesion->autorizacion_estratega ?? '—' }}</dd>

                                <dt class="col-sm-5">Clínica del tratamiento</dt>
                                <dd class="col-sm-7">{{ $sesion->clinica ?? '—' }}</dd>
                            </dl>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded p-3">
                            <h6 class="text-muted text-uppercase small mb-2">Motivo de referencia</h6>
                            @if ($sesion->motivo_referencia)
                                <p class="mb-0 small">{!! nl2br(e($sesion->motivo_referencia)) !!}</p>
                            @else
                                <p class="mb-0 text-muted small">Sin motivo registrado.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @if ($sesion->status_revision !== 'validada')
                <div class="mt-4">
                    <h6 class="mb-3">Gestión de revisión</h6>
                    <div class="row g-3">
                        @can('observe', $sesion)
                            <div class="col-md-6">
                                <div class="card h-100 border-warning">
                                    <div class="card-body">
                                        <h6 class="card-title text-warning">Marcar como observada</h6>
                                        <p class="card-text small text-muted">Registra observaciones para que el alumno atienda los cambios necesarios.</p>
                                        <form action="{{ route('expedientes.sesiones.observe', [$expediente, $sesion]) }}" method="post" class="d-flex flex-column gap-2">
                                            @csrf
                                            <input type="hidden" name="form_action" value="observe">
                                            <textarea name="observaciones" rows="3" class="form-control @if (old('form_action') === 'observe') @error('observaciones') is-invalid @enderror @endif" placeholder="Describe las observaciones" required>{{ old('form_action') === 'observe' ? old('observaciones') : '' }}</textarea>
                                            @if (old('form_action') === 'observe')
                                                @error('observaciones')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            @endif
                                            <div class="text-end">
                                                <button type="submit" class="btn btn-warning text-dark">Guardar observaciones</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endcan
                        @can('validate', $sesion)
                            <div class="col-md-6">
                                <div class="card h-100 border-success">
                                    <div class="card-body">
                                        <h6 class="card-title text-success">Validar sesión</h6>
                                        <p class="card-text small text-muted">Confirma que la sesión cumple con los requisitos establecidos.</p>
                                        <form action="{{ route('expedientes.sesiones.validate', [$expediente, $sesion]) }}" method="post" class="d-flex flex-column gap-2">
                                            @csrf
                                            <input type="hidden" name="form_action" value="validate">
                                            <textarea name="observaciones" rows="3" class="form-control @if (old('form_action') === 'validate') @error('observaciones') is-invalid @enderror @endif" placeholder="Notas opcionales para la validación">{{ old('form_action') === 'validate' ? old('observaciones') : '' }}</textarea>
                                            @if (old('form_action') === 'validate')
                                                @error('observaciones')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            @endif
                                            <div class="text-end">
                                                <button type="submit" class="btn btn-success">Validar sesión</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endcan
                    </div>
                </div>
            @endif

            @if ($sesion->adjuntos->isNotEmpty())
                <div class="mt-4">
                    <h6 class="mb-2">Adjuntos</h6>
                    <ul class="list-group list-group-flush">
                        @foreach ($sesion->adjuntos as $adjunto)
                            <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <a href="{{ $adjunto->url }}" target="_blank" rel="noopener" class="fw-semibold">{{ $adjunto->nombre_original }}</a>
                                    <div class="text-muted small">
                                        {{ number_format($adjunto->tamano / 1024, 1) }} KB ·
                                        {{ $adjunto->subidoPor?->name ?? 'Desconocido' }} ·
                                        {{ optional($adjunto->created_at)->format('Y-m-d H:i') }}
                                    </div>
                                </div>
                                <a href="{{ $adjunto->url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Descargar</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-4">
                <h6 class="mb-2">Historial de revisión</h6>
                @if ($historialRevision->isEmpty())
                    <p class="text-muted small mb-0">Sin eventos registrados para esta sesión.</p>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($historialRevision as $evento)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="fw-semibold">{{ $evento->actor?->name ?? 'Sistema' }}</span>
                                        <span class="text-muted small">→ {{ ucfirst(str_replace('sesion.', '', $evento->evento)) }}</span>
                                        <div class="mt-2 small">
                                            <span class="text-muted">Estado:</span>
                                            @php
                                                $estadoAnterior = $evento->payload['estado_anterior'] ?? null;
                                                $estadoNuevo = $evento->payload['estado_nuevo'] ?? null;
                                                $badgeClasses = [
                                                    'pendiente' => 'badge bg-secondary',
                                                    'observada' => 'badge bg-warning text-dark',
                                                    'validada' => 'badge bg-success',
                                                ];
                                            @endphp
                                            @if ($estadoAnterior)
                                                <span class="me-1">{{ ucfirst($estadoAnterior) }}</span>
                                                <span class="text-muted">→</span>
                                            @endif
                        
                                            @if ($estadoNuevo)
                                                <span class="ms-1 {{ $badgeClasses[$estadoNuevo] ?? 'badge bg-light text-dark' }}">{{ ucfirst($estadoNuevo) }}</span>
                                            @endif
                                        </div>
                                        @if (! empty($evento->payload['observaciones']))
                                            <div class="mt-2 small">
                                                <span class="text-muted">Observaciones:</span>
                                                <span>{{ $evento->payload['observaciones'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <small class="text-muted">{{ optional($evento->created_at)->format('d/m/Y H:i') }}</small>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
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
