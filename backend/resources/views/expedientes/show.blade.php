@extends('layouts.noble')

@section('title', 'Detalle de expediente')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Expediente {{ $expediente->no_control }}</h4>
            <p class="text-muted small mb-0">{{ $expediente->paciente }}</p>
        </div>
        <div class="btn-group">
            <a href="{{ route('expedientes.index') }}" class="btn btn-outline-secondary">Volver</a>
            @can('update', $expediente)
                <a href="{{ route('expedientes.edit', $expediente) }}" class="btn btn-primary">Editar</a>
            @endcan
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    <div class="card mb-4 shadow-sm">
        <div class="card-body row g-3">
            <div class="col-md-3">
                <span class="text-muted small d-block">Estado</span>
                @php
                    $estadoBadge = [
                        'abierto' => 'bg-secondary',
                        'revision' => 'bg-warning text-dark',
                        'cerrado' => 'bg-success',
                    ][$expediente->estado] ?? 'bg-light text-dark';
                @endphp
                <span class="badge {{ $estadoBadge }}">{{ ucfirst($expediente->estado) }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-muted small d-block">Apertura</span>
                <span class="fw-semibold">{{ optional($expediente->apertura)->format('Y-m-d') }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-muted small d-block">Carrera</span>
                <span class="fw-semibold">{{ $expediente->carrera }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-muted small d-block">Turno</span>
                <span class="fw-semibold">{{ $expediente->turno }}</span>
            </div>
            <div class="col-md-4">
                <span class="text-muted small d-block">Tutor asignado</span>
                <span class="fw-semibold">{{ $expediente->tutor?->name ?? 'Sin asignar' }}</span>
            </div>
            <div class="col-md-4">
                <span class="text-muted small d-block">Coordinador</span>
                <span class="fw-semibold">{{ $expediente->coordinador?->name ?? 'Sin asignar' }}</span>
            </div>
            <div class="col-md-4">
                <span class="text-muted small d-block">Registrado por</span>
                <span class="fw-semibold">{{ $expediente->creadoPor?->name ?? 'Desconocido' }}</span>
            </div>
        </div>
        @can('changeState', $expediente)
            <div class="card-footer">
                <form action="{{ route('expedientes.change-state', $expediente) }}" method="post" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label for="estado" class="form-label">Cambiar estado</label>
                        <select name="estado" id="estado" class="form-select">
                            @foreach ($availableStates as $value => $label)
                                <option value="{{ $value }}" @selected($expediente->estado === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary">Actualizar estado</button>
                    </div>
                </form>
            </div>
        @endcan
    </div>

    <ul class="nav nav-tabs mb-3" id="expedienteTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="resumen-tab" data-bs-toggle="tab" data-bs-target="#resumen" type="button" role="tab">Resumen</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="sesiones-tab" data-bs-toggle="tab" data-bs-target="#sesiones" type="button" role="tab">Sesiones ({{ $sesiones->count() }})</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="consentimientos-tab" data-bs-toggle="tab" data-bs-target="#consentimientos" type="button" role="tab">Consentimientos ({{ $consentimientos->count() }})</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="anexos-tab" data-bs-toggle="tab" data-bs-target="#anexos" type="button" role="tab">Anexos ({{ $anexos->count() }})</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline" type="button" role="tab">Timeline</button>
        </li>
    </ul>

    <div class="tab-content" id="expedienteTabsContent">
        <div class="tab-pane fade show active" id="resumen" role="tabpanel" aria-labelledby="resumen-tab">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3">Información general</h6>
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Número de control</dt>
                        <dd class="col-sm-9">{{ $expediente->no_control }}</dd>

                        <dt class="col-sm-3">Paciente</dt>
                        <dd class="col-sm-9">{{ $expediente->paciente }}</dd>

                        <dt class="col-sm-3">Tutor</dt>
                        <dd class="col-sm-9">{{ $expediente->tutor?->name ?? 'Sin asignar' }}</dd>

                        <dt class="col-sm-3">Coordinador</dt>
                        <dd class="col-sm-9">{{ $expediente->coordinador?->name ?? 'Sin asignar' }}</dd>

                        <dt class="col-sm-3">Última actualización</dt>
                        <dd class="col-sm-9">{{ optional($expediente->updated_at)->diffForHumans() }}</dd>
                    </dl>

                    <div class="mt-4">
                        <h6 class="mb-3">Últimos eventos</h6>
                        @if ($timelineEventosRecientes->isEmpty())
                            <p class="text-muted mb-0">Todavía no hay actividad registrada.</p>
                        @else
                            <ul class="list-unstyled mb-0">
                                @foreach ($timelineEventosRecientes as $evento)
                                    <li class="mb-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <span class="fw-semibold">{{ $evento->actor?->name ?? 'Sistema' }}</span>
                                                <span class="text-muted">→ {{ $evento->evento }}</span>
                                            </div>
                                            <small class="text-muted">{{ optional($evento->created_at)->diffForHumans() }}</small>
                                        </div>
                                        @if (! empty($evento->payload))
                                            <pre class="bg-light border rounded small mt-2 mb-0 p-2">{{ json_encode($evento->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="sesiones" role="tabpanel" aria-labelledby="sesiones-tab">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3">Sesiones registradas</h6>
                    @if ($sesiones->isEmpty())
                        <p class="text-muted mb-0">Aún no hay sesiones registradas.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Estado revisión</th>
                                        <th>Realizada por</th>
                                        <th>Validada por</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sesiones as $sesion)
                                        <tr>
                                            <td>{{ optional($sesion->fecha)->format('Y-m-d') }}</td>
                                            <td>{{ $sesion->tipo }}</td>
                                            <td>
                                                @php
                                                    $badge = [
                                                        'pendiente' => 'bg-secondary',
                                                        'observada' => 'bg-warning text-dark',
                                                        'validada' => 'bg-success',
                                                    ][$sesion->status_revision] ?? 'bg-light text-dark';
                                                @endphp
                                                <span class="badge {{ $badge }}">{{ ucfirst($sesion->status_revision) }}</span>
                                            </td>
                                            <td>{{ $sesion->realizadaPor?->name }}</td>
                                            <td>{{ $sesion->validadaPor?->name ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="consentimientos" role="tabpanel" aria-labelledby="consentimientos-tab">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3">Consentimientos</h6>
                    @if ($consentimientos->isEmpty())
                        <p class="text-muted mb-0">No hay consentimientos registrados.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Tratamiento</th>
                                        <th>Requerido</th>
                                        <th>Aceptado</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($consentimientos as $consentimiento)
                                        <tr>
                                            <td>{{ $consentimiento->tratamiento }}</td>
                                            <td>
                                                <span class="badge {{ $consentimiento->requerido ? 'bg-danger' : 'bg-secondary' }}">
                                                    {{ $consentimiento->requerido ? 'Sí' : 'No' }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge {{ $consentimiento->aceptado ? 'bg-success' : 'bg-warning text-dark' }}">
                                                    {{ $consentimiento->aceptado ? 'Aceptado' : 'Pendiente' }}
                                                </span>
                                            </td>
                                            <td>{{ optional($consentimiento->fecha)->format('Y-m-d') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="anexos" role="tabpanel" aria-labelledby="anexos-tab">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3">Anexos</h6>
                    @if ($anexos->isEmpty())
                        <p class="text-muted mb-0">Sin anexos por el momento.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Título</th>
                                        <th>Tipo</th>
                                        <th>Tamaño</th>
                                        <th>Subido por</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($anexos as $anexo)
                                        <tr>
                                            <td>{{ $anexo->titulo }}</td>
                                            <td>{{ $anexo->tipo }}</td>
                                            <td>{{ number_format($anexo->tamano / 1024, 1) }} KB</td>
                                            <td>{{ $anexo->subidoPor?->name }}</td>
                                            <td>{{ optional($anexo->created_at)->format('Y-m-d H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="timeline" role="tabpanel" aria-labelledby="timeline-tab">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3">Actividad reciente</h6>
                    @if ($timelineEventos->isEmpty())
                        <p class="text-muted mb-0">Aún no hay eventos registrados en el timeline.</p>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($timelineEventos as $evento)
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="fw-semibold">{{ $evento->actor?->name ?? 'Sistema' }}</span>
                                            <span class="text-muted">→ {{ $evento->evento }}</span>
                                            @if (! empty($evento->payload))
                                                <pre class="bg-light border rounded small mt-2 mb-0 p-2">{{ json_encode($evento->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            @endif
                                        </div>
                                        <small class="text-muted">{{ optional($evento->created_at)->format('Y-m-d H:i') }}</small>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.bootstrap || !window.bootstrap.Tab) {
                return;
            }

            const triggerTabList = [].slice.call(document.querySelectorAll('#expedienteTabs button[data-bs-toggle="tab"]'));

            const activateTab = function (selector) {
                const tabTrigger = document.querySelector(selector);
                if (tabTrigger) {
                    const tab = new window.bootstrap.Tab(tabTrigger);
                    tab.show();
                }
            };

            if (window.location.hash) {
                activateTab(`#expedienteTabs button[data-bs-target="${window.location.hash}"]`);
            }

            triggerTabList.forEach(function (triggerEl) {
                triggerEl.addEventListener('shown.bs.tab', function (event) {
                    const target = event.target.getAttribute('data-bs-target');
                    if (target) {
                        const newUrl = `${window.location.pathname}${window.location.search}${target}`;
                        history.replaceState(null, '', newUrl);
                    }
                });
            });
        });
    </script>
@endpush
