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

    @php
        $permiteGestionarSesiones = $expediente->estado === 'abierto';
    @endphp

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
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h6 class="mb-0">Sesiones registradas</h6>
                        <div class="btn-group">
                            <a href="{{ route('expedientes.sesiones.index', $expediente) }}" class="btn btn-outline-secondary btn-sm">Ver todas</a>
                            @if ($permiteGestionarSesiones)
                                @can('create', [App\Models\Sesion::class, $expediente])
                                    <a href="{{ route('expedientes.sesiones.create', $expediente) }}" class="btn btn-primary btn-sm">Nueva sesión</a>
                                @endcan
                            @endif
                        </div>
                    </div>
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
                                        <th class="text-end">Acciones</th>
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
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    @can('view', $sesion)
                                                        <a href="{{ route('expedientes.sesiones.show', [$expediente, $sesion]) }}" class="btn btn-outline-secondary">Ver</a>
                                                    @endcan
                                                    @if ($permiteGestionarSesiones && $sesion->status_revision !== 'validada')
                                                        @can('update', $sesion)
                                                            <a href="{{ route('expedientes.sesiones.edit', [$expediente, $sesion]) }}" class="btn btn-outline-secondary">Editar</a>
                                                        @endcan
                                                    @endif
                                                    @can('delete', $sesion)
                                                        <form action="{{ route('expedientes.sesiones.destroy', [$expediente, $sesion]) }}" method="post" class="d-inline"
                                                            onsubmit="return confirm('¿Deseas eliminar esta sesión? Esta acción no se puede deshacer.');">
                                                            @csrf
                                                            @method('delete')
                                                            <button type="submit" class="btn btn-outline-danger">Eliminar</button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </td>
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
                        @php
                            $usuarioActual = auth()->user();
                            $puedeGestionarConsentimientos = $usuarioActual
                                ? $consentimientos->contains(fn ($consentimiento) => $usuarioActual->can('upload', $consentimiento))
                                : false;
                        @endphp
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Tratamiento</th>
                                        <th>Requerido</th>
                                        <th>Aceptado</th>
                                        <th>Archivo</th>
                                        <th>Fecha</th>
                                        <th>Subido por</th>
                                        @if ($puedeGestionarConsentimientos)
                                            <th class="text-end">Acciones</th>
                                        @endif
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
                                            <td>
                                                @if ($consentimiento->archivo_path)
                                                    <a href="{{ route('consentimientos.archivo', $consentimiento) }}" target="_blank" rel="noopener">
                                                        Ver archivo
                                                    </a>
                                                    <div class="small text-muted">{{ basename($consentimiento->archivo_path) }}</div>
                                                @else
                                                    <span class="badge bg-secondary">Sin archivo</span>
                                                @endif
                                            </td>
                                            <td>{{ optional($consentimiento->fecha)->format('Y-m-d') ?? '—' }}</td>
                                            <td>{{ $consentimiento->subidoPor?->name ?? '—' }}</td>
                                            @if ($puedeGestionarConsentimientos)
                                                <td class="text-end">
                                                    @can('upload', $consentimiento)
                                                        @include('expedientes.partials.consentimiento-upload-form', [
                                                            'consentimiento' => $consentimiento,
                                                            'uploadMimes' => $consentimientosUploadMimes,
                                                            'uploadMax' => $consentimientosUploadMax,
                                                        ])
                                                    @else
                                                        <span class="text-muted small">Sin permisos</span>
                                                    @endcan
                                                </td>
                                            @endif
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
                    @php
                        $usuarioActual = auth()->user();
                        $puedeSubirAnexos = $usuarioActual?->can('create', [App\Models\Anexo::class, $expediente]);
                        $puedeEliminarAlguno = $anexos->contains(fn ($anexo) => $usuarioActual?->can('delete', $anexo));
                        $mostrarDescarga = $anexos->contains(fn ($anexo) => ! empty($anexo->download_url));
                        $mostrarAcciones = $mostrarDescarga || $puedeEliminarAlguno;
                        $formatosAceptados = collect(explode(',', (string) $anexosUploadMimes))
                            ->map(fn ($valor) => trim($valor))
                            ->filter()
                            ->map(fn ($valor) => str_contains($valor, '/') ? $valor : '.'.$valor)
                            ->implode(',');
                    @endphp
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <h6 class="mb-0">Anexos</h6>
                            <span class="badge bg-light text-muted" data-anexos-counter>{{ $anexos->count() }} registros</span>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" data-anexos-view-switch>
                            <button type="button" class="btn btn-outline-secondary active" data-anexos-view-toggle="list">Lista</button>
                            <button type="button" class="btn btn-outline-secondary" data-anexos-view-toggle="gallery">Galería</button>
                        </div>
                    </div>
                    @can('create', [App\Models\Anexo::class, $expediente])
                        <div class="mb-4">
                            <label for="anexos-uploader" class="form-label small text-uppercase text-muted mb-2">Agregar archivos</label>
                            <input
                                type="file"
                                id="anexos-uploader"
                                data-anexos-uploader
                                data-upload-url="{{ route('expedientes.anexos.store', $expediente) }}"
                                data-csrf-token="{{ csrf_token() }}"
                                data-table-target="#anexos-table-body"
                                data-empty-target="#anexos-empty-state"
                                data-table-wrapper="#anexos-table-wrapper"
                                data-gallery-wrapper="#anexos-gallery-wrapper"
                                data-gallery-target="#anexos-gallery-grid"
                                data-accepted-types="{{ $anexosUploadMimes }}"
                                data-max-size="{{ $anexosUploadMax }}"
                                data-can-delete="true"
                                multiple
                                class="form-control"
                                accept="{{ $formatosAceptados }}"
                            >
                            <p class="text-muted small mt-2 mb-0">
                                Formatos permitidos: {{ str_replace(',', ', ', $anexosUploadMimes) }}.
                                Tamaño máximo: {{ number_format($anexosUploadMax / 1024, 1) }} MB por archivo.
                            </p>
                        </div>
                    @endcan
                    <form method="GET" action="{{ route('expedientes.show', $expediente) }}" class="row g-3 align-items-end mb-4">
                        <input type="hidden" name="tab" value="anexos">
                        <div class="col-md-4 col-lg-3">
                            <label for="filtro-anexo-titulo" class="form-label">Título</label>
                            <input
                                type="search"
                                class="form-control"
                                id="filtro-anexo-titulo"
                                name="titulo"
                                placeholder="Buscar por título"
                                value="{{ $anexosFilters['titulo'] ?? '' }}"
                            >
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label for="filtro-anexo-tipo" class="form-label">Tipo</label>
                            <select id="filtro-anexo-tipo" name="tipo" class="form-select">
                                <option value="">Todos</option>
                                @foreach ($anexosTipos as $tipo)
                                    <option value="{{ $tipo }}" @selected(($anexosFilters['tipo'] ?? '') === $tipo)>{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 col-lg-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Buscar</button>
                            <a href="{{ route('expedientes.show', ['expediente' => $expediente, 'tab' => 'anexos']) }}" class="btn btn-link text-decoration-none">Limpiar</a>
                        </div>
                    </form>
                    <div id="anexos-empty-state" class="{{ $anexos->isEmpty() ? '' : 'd-none' }}">
                        <p class="text-muted mb-0">Sin anexos por el momento.</p>
                    </div>
                    <div id="anexos-table-wrapper" data-anexos-view="list" class="table-responsive {{ $anexos->isEmpty() ? 'd-none' : '' }}">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Tipo</th>
                                    <th>Tamaño</th>
                                    <th>Subido por</th>
                                    <th>Fecha</th>
                                    @if ($mostrarAcciones)
                                        <th class="text-end">Acciones</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody id="anexos-table-body" data-has-actions="{{ $mostrarAcciones ? 'true' : 'false' }}">
                                @foreach ($anexos as $anexo)
                                    <tr data-anexo-id="{{ $anexo->id }}">
                                        <td>
                                            @if (! empty($anexo->download_url))
                                                <a href="{{ $anexo->download_url }}" class="text-decoration-none">
                                                    {{ $anexo->titulo }}
                                                </a>
                                            @else
                                                {{ $anexo->titulo }}
                                            @endif
                                        </td>
                                        <td>{{ $anexo->tipo }}</td>
                                        <td>{{ number_format(($anexo->tamano ?? 0) / 1024, 1) }} KB</td>
                                        <td>{{ $anexo->subidoPor?->name }}</td>
                                        <td>{{ optional($anexo->created_at)->format('Y-m-d H:i') }}</td>
                                        @if ($mostrarAcciones)
                                            <td class="text-end">
                                                @if ($mostrarDescarga && ! empty($anexo->download_url))
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="{{ $anexo->download_url }}" class="btn btn-outline-secondary">Descargar</a>
                                                        @can('delete', $anexo)
                                                            <button
                                                                type="button"
                                                                class="btn btn-outline-danger"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#anexoDeleteModal"
                                                                data-delete-url="{{ route('expedientes.anexos.destroy', [$expediente, $anexo]) }}"
                                                                data-anexo-title="{{ $anexo->titulo }}"
                                                            >
                                                                Eliminar
                                                            </button>
                                                        @endcan
                                                    </div>
                                                @elseif ($puedeEliminarAlguno)
                                                    @can('delete', $anexo)
                                                        <button
                                                            type="button"
                                                            class="btn btn-outline-danger btn-sm"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#anexoDeleteModal"
                                                            data-delete-url="{{ route('expedientes.anexos.destroy', [$expediente, $anexo]) }}"
                                                            data-anexo-title="{{ $anexo->titulo }}"
                                                        >
                                                            Eliminar
                                                        </button>
                                                    @else
                                                        <span class="text-muted small">—</span>
                                                    @endcan
                                                @else
                                                    <span class="text-muted small">—</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div id="anexos-gallery-wrapper" data-anexos-view="gallery" class="{{ $anexos->isEmpty() ? 'd-none' : '' }}">
                        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3" id="anexos-gallery-grid" data-anexos-gallery>
                            @foreach ($anexos as $anexo)
                                @php
                                    $tipo = strtolower((string) $anexo->tipo);
                                    $esImagen = str_starts_with($tipo, 'image/') || in_array($tipo, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'], true);
                                    $tamanoLegible = number_format(($anexo->tamano ?? 0) / 1024, 1).' KB';
                                @endphp
                                <div class="col" data-anexo-id="{{ $anexo->id }}">
                                    <div class="card h-100 shadow-sm">
                                        <div class="ratio ratio-4x3 bg-light border-bottom">
                                            @if ($esImagen && ! empty($anexo->preview_url))
                                                <img src="{{ $anexo->preview_url }}" alt="Vista previa de {{ $anexo->titulo }}" class="img-fluid w-100 h-100 object-fit-cover rounded-top">
                                            @else
                                                <div class="d-flex h-100 align-items-center justify-content-center text-muted flex-column">
                                                    <span class="fw-semibold">Sin vista previa</span>
                                                    <small class="text-muted">{{ strtoupper((string) $anexo->tipo) }}</small>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="card-body d-flex flex-column">
                                            <h6 class="card-title text-truncate" title="{{ $anexo->titulo }}">{{ $anexo->titulo }}</h6>
                                            <ul class="list-unstyled small text-muted mb-3">
                                                <li><span class="text-dark">Tipo:</span> {{ $anexo->tipo }}</li>
                                                <li><span class="text-dark">Tamaño:</span> {{ $tamanoLegible }}</li>
                                                <li><span class="text-dark">Subido por:</span> {{ $anexo->subidoPor?->name ?? '—' }}</li>
                                                <li><span class="text-dark">Fecha:</span> {{ optional($anexo->created_at)->format('Y-m-d H:i') ?? '—' }}</li>
                                            </ul>
                                            <div class="mt-auto d-flex flex-wrap gap-2">
                                                @if (! empty($anexo->download_url))
                                                    <a href="{{ $anexo->download_url }}" class="btn btn-outline-secondary btn-sm">Descargar</a>
                                                @endif
                                                @can('delete', $anexo)
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-danger btn-sm"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#anexoDeleteModal"
                                                        data-delete-url="{{ route('expedientes.anexos.destroy', [$expediente, $anexo]) }}"
                                                        data-anexo-title="{{ $anexo->titulo }}"
                                                    >
                                                        Eliminar
                                                    </button>
                                                @endcan
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal fade" id="anexoDeleteModal" tabindex="-1" aria-labelledby="anexoDeleteModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <form method="POST" class="modal-content" id="anexoDeleteForm">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="titulo" value="{{ $anexosFilters['titulo'] ?? '' }}">
                                <input type="hidden" name="tipo" value="{{ $anexosFilters['tipo'] ?? '' }}">
                                <input type="hidden" name="tab" value="anexos">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="anexoDeleteModalLabel">Eliminar anexo</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="mb-0">¿Seguro que deseas eliminar <span class="fw-semibold" data-anexo-delete-title>este anexo</span>? Esta acción no se puede deshacer.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-danger">Eliminar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="timeline" role="tabpanel" aria-labelledby="timeline-tab">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                        <h6 class="mb-0">Actividad reciente</h6>
                        <button
                            type="button"
                            class="btn btn-outline-primary btn-sm ms-md-auto"
                            data-timeline-export-url="{{ route('expedientes.timeline.export', $expediente) }}"
                        >
                            <i class="mdi mdi-download"></i>
                            {{ __('Exportar historial') }}
                        </button>
                    </div>
                    <div id="timeline-export-feedback" class="alert d-none" role="alert"></div>
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

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/build/css/anexos.css') }}">
@endpush

@push('scripts')
    <script>
        window.translations = window.translations || {};
        window.translations.expedientes = {
            ...(window.translations.expedientes || {}),
            anexos: {{ Js::from([
                'counter_label' => __('expedientes.anexos.counter_label'),
                'placeholder' => __('expedientes.anexos.placeholder'),
                'untitled' => __('expedientes.anexos.untitled'),
                'generic_item' => __('expedientes.anexos.generic_item'),
                'preview_alt' => __('expedientes.anexos.preview_alt'),
                'no_preview' => __('expedientes.anexos.no_preview'),
                'metadata' => [
                    'type' => __('expedientes.anexos.metadata.type'),
                    'size' => __('expedientes.anexos.metadata.size'),
                    'size_value' => __('expedientes.anexos.metadata.size_value'),
                    'uploaded_by' => __('expedientes.anexos.metadata.uploaded_by'),
                    'date' => __('expedientes.anexos.metadata.date'),
                ],
                'actions' => [
                    'download' => __('expedientes.anexos.actions.download'),
                    'delete' => __('expedientes.anexos.actions.delete'),
                ],
                'delete_placeholder' => __('expedientes.anexos.delete_placeholder'),
                'pond' => [
                    'idle' => __('expedientes.anexos.pond.idle'),
                ],
                'errors' => [
                    'generic_title' => __('expedientes.anexos.errors.generic_title'),
                    'upload_failed' => __('expedientes.anexos.errors.upload_failed'),
                    'upload_unexpected' => __('expedientes.anexos.errors.upload_unexpected'),
                    'revert_failed' => __('expedientes.anexos.errors.revert_failed'),
                ],
            ]) }},
        };
    </script>
    <script src="{{ asset('assets/build/js/anexos.js') }}" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const bootstrapLib = window.bootstrap;
            const tabButtons = [].slice.call(document.querySelectorAll('#expedienteTabs button[data-bs-toggle="tab"]'));
            const hasTabSupport = Boolean(bootstrapLib?.Tab);

            const sanitizeTab = (value) => (value || '').toString().replace(/[^a-z0-9_-]/gi, '');

            const activateTab = (selector) => {
                if (!hasTabSupport) {
                    return;
                }

                const tabTrigger = document.querySelector(selector);
                if (tabTrigger) {
                    const tab = new bootstrapLib.Tab(tabTrigger);
                    tab.show();
                }
            };

            const updateUrlForTab = (target) => {
                const url = new URL(window.location.href);

                if (target && target.startsWith('#')) {
                    const value = sanitizeTab(target.substring(1));
                    if (value && value !== 'resumen') {
                        url.searchParams.set('tab', value);
                    } else {
                        url.searchParams.delete('tab');
                    }
                } else {
                    url.searchParams.delete('tab');
                }

                url.hash = '';
                history.replaceState(null, '', url);
            };

            if (hasTabSupport) {
                const params = new URLSearchParams(window.location.search);
                const requestedTab = sanitizeTab(params.get('tab'));

                if (requestedTab) {
                    activateTab(`#expedienteTabs button[data-bs-target="#${requestedTab}"]`);
                } else if (window.location.hash) {
                    const hash = sanitizeTab(window.location.hash.replace('#', ''));
                    if (hash) {
                        activateTab(`#expedienteTabs button[data-bs-target="#${hash}"]`);
                    }
                }

                tabButtons.forEach((triggerEl) => {
                    triggerEl.addEventListener('shown.bs.tab', (event) => {
                        const target = event.target.getAttribute('data-bs-target');
                        if (target) {
                            updateUrlForTab(target);
                        }
                    });
                });
            }

            const viewButtons = document.querySelectorAll('[data-anexos-view-toggle]');
            const viewContainers = document.querySelectorAll('[data-anexos-view]');
            const viewStorageKey = 'expedientes.anexos.view';

            const setViewMode = (mode) => {
                const normalized = mode === 'gallery' ? 'gallery' : 'list';

                viewContainers.forEach((container) => {
                    const isActive = container.dataset.anexosView === normalized;
                    container.classList.toggle('d-none', !isActive);
                });

                viewButtons.forEach((button) => {
                    const isActive = button.dataset.anexosViewToggle === normalized;
                    button.classList.toggle('active', isActive);
                    if (isActive) {
                        button.classList.remove('btn-outline-secondary');
                        button.classList.add('btn-primary');
                    } else {
                        button.classList.remove('btn-primary');
                        button.classList.add('btn-outline-secondary');
                    }
                });

                try {
                    window.localStorage.setItem(viewStorageKey, normalized);
                } catch (error) {
                    console.debug('No se pudo guardar la preferencia de vista de anexos.', error);
                }
            };

            if (viewButtons.length > 0 && viewContainers.length > 0) {
                let storedView = null;
                try {
                    storedView = window.localStorage.getItem(viewStorageKey);
                } catch (error) {
                    storedView = null;
                }

                if (storedView !== 'list' && storedView !== 'gallery') {
                    storedView = 'list';
                }

                setViewMode(storedView);

                viewButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        setViewMode(button.dataset.anexosViewToggle);
                    });
                });
            }

            const deleteModalEl = document.getElementById('anexoDeleteModal');

            if (deleteModalEl && bootstrapLib?.Modal) {
                deleteModalEl.addEventListener('show.bs.modal', (event) => {
                    const trigger = event.relatedTarget;
                    if (!trigger) {
                        return;
                    }

                    const deleteUrl = trigger.getAttribute('data-delete-url') || '';
                    const anexoTitle = trigger.getAttribute('data-anexo-title') || 'este anexo';
                    const form = deleteModalEl.querySelector('form');
                    const titleTarget = deleteModalEl.querySelector('[data-anexo-delete-title]');
                    const submitButton = deleteModalEl.querySelector('button[type="submit"]');

                    if (form) {
                        if (deleteUrl) {
                            form.setAttribute('action', deleteUrl);
                        } else {
                            form.removeAttribute('action');
                        }
                    }

                    if (titleTarget) {
                        titleTarget.textContent = anexoTitle;
                    }

                    if (submitButton) {
                        submitButton.disabled = deleteUrl === '';
                    }
                });

                deleteModalEl.addEventListener('hidden.bs.modal', () => {
                    const form = deleteModalEl.querySelector('form');
                    const submitButton = deleteModalEl.querySelector('button[type="submit"]');

                    if (form) {
                        form.removeAttribute('action');
                    }

                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                });
            }

            const timelineExportButton = document.querySelector('[data-timeline-export-url]');
            const timelineFeedback = document.getElementById('timeline-export-feedback');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            let timelinePollTimer = null;

            const showTimelineMessage = (type, message) => {
                if (!timelineFeedback) {
                    return;
                }

                timelineFeedback.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
                timelineFeedback.classList.add(`alert-${type}`);
                timelineFeedback.textContent = message;
            };

            const setTimelineButtonDisabled = (disabled) => {
                if (timelineExportButton) {
                    timelineExportButton.disabled = disabled;
                }
            };

            const stopTimelinePolling = () => {
                if (timelinePollTimer) {
                    clearInterval(timelinePollTimer);
                    timelinePollTimer = null;
                }
            };

            const startTimelinePolling = (statusUrl) => {
                stopTimelinePolling();

                timelinePollTimer = setInterval(() => {
                    fetch(statusUrl, { headers: { Accept: 'application/json' } })
                        .then((response) => {
                            if (!response.ok) {
                                throw new Error('status-error');
                            }

                            return response.json();
                        })
                        .then((payload) => {
                            if (payload.status === 'ready' && payload.download_url) {
                                stopTimelinePolling();
                                showTimelineMessage('success', @json(__('El archivo está listo, iniciando descarga...')));
                                window.location.href = payload.download_url;
                                setTimelineButtonDisabled(false);
                            }
                        })
                        .catch(() => {
                            stopTimelinePolling();
                            showTimelineMessage('danger', @json(__('No se pudo verificar el estado de la exportación. Intenta nuevamente.')));
                            setTimelineButtonDisabled(false);
                        });
                }, 4000);
            };

            if (timelineExportButton && timelineFeedback) {
                timelineExportButton.addEventListener('click', (event) => {
                    event.preventDefault();

                    const exportUrl = timelineExportButton.getAttribute('data-timeline-export-url');
                    if (!exportUrl) {
                        return;
                    }

                    setTimelineButtonDisabled(true);
                    showTimelineMessage('warning', @json(__('Generando exportación, espera un momento...')));

                    fetch(exportUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ format: 'xlsx' }),
                    })
                        .then((response) => {
                            if (!response.ok) {
                                return response.json().then((payload) => {
                                    throw payload;
                                });
                            }

                            return response.json();
                        })
                        .then((payload) => {
                            if (payload.status === 'ready' && payload.download_url) {
                                showTimelineMessage('success', payload.message ?? @json(__('El archivo está listo.')));
                                window.location.href = payload.download_url;
                                setTimelineButtonDisabled(false);
                            } else if (payload.status === 'pending' && payload.status_url) {
                                showTimelineMessage('info', payload.message ?? @json(__('La exportación se está procesando. Te avisaremos cuando esté lista.')));
                                startTimelinePolling(payload.status_url);
                            } else {
                                throw new Error('invalid-payload');
                            }
                        })
                        .catch((error) => {
                            if (error?.errors) {
                                const firstError = Object.values(error.errors)[0]?.[0] ?? @json(__('No fue posible generar la exportación.'));
                                showTimelineMessage('danger', firstError);
                            } else {
                                showTimelineMessage('danger', @json(__('No fue posible generar la exportación.')));
                            }

                            setTimelineButtonDisabled(false);
                        });
                });
            }
        });
    </script>
@endpush
