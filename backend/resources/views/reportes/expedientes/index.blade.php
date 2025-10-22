@extends('layouts.noble')

@section('title', __('Reportes de expedientes'))

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-column flex-md-row gap-3 mb-4">
        <div>
            <h4 class="mb-1">{{ __('Reportes de expedientes') }}</h4>
            <p class="text-muted small mb-0">
                {{ __('Filtra los expedientes por estado, fechas y responsables para generar reportes descargables.') }}
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" data-export-format="csv">
                <i class="mdi mdi-download"></i>
                {{ __('Exportar CSV') }}
            </button>
            <button type="button" class="btn btn-primary" data-export-format="xlsx">
                <i class="mdi mdi-file-excel"></i>
                {{ __('Exportar XLSX') }}
            </button>
        </div>
    </div>

    <div id="export-feedback" class="alert d-none" role="alert"></div>

    <form id="report-filters-form" class="row g-3 mb-4" method="get">
        <div class="col-md-3">
            <label class="form-label text-muted small" for="filtro-estado">{{ __('Estado') }}</label>
            <select class="form-select" id="filtro-estado" name="estado">
                <option value="">{{ __('Todos') }}</option>
                <option value="abierto" @selected(($filters['estado'] ?? '') === 'abierto')>{{ __('Abierto') }}</option>
                <option value="revision" @selected(($filters['estado'] ?? '') === 'revision')>{{ __('En revisión') }}</option>
                <option value="cerrado" @selected(($filters['estado'] ?? '') === 'cerrado')>{{ __('Cerrado') }}</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-muted small" for="filtro-desde">{{ __('Desde') }}</label>
            <input type="text" class="form-control flatpickr" id="filtro-desde" name="desde" value="{{ $filters['desde'] ?? '' }}" placeholder="AAAA-MM-DD">
        </div>
        <div class="col-md-2">
            <label class="form-label text-muted small" for="filtro-hasta">{{ __('Hasta') }}</label>
            <input type="text" class="form-control flatpickr" id="filtro-hasta" name="hasta" value="{{ $filters['hasta'] ?? '' }}" placeholder="AAAA-MM-DD">
        </div>
        <div class="col-md-2">
            <label class="form-label text-muted small" for="filtro-tutor">{{ __('Tutor') }}</label>
            <select class="form-select" id="filtro-tutor" name="tutor_id">
                <option value="">{{ __('Todos') }}</option>
                @foreach ($tutores as $tutor)
                    <option value="{{ $tutor->id }}" @selected(($filters['tutor_id'] ?? null) === $tutor->id)>{{ $tutor->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-muted small" for="filtro-coordinador">{{ __('Coordinador') }}</label>
            <select class="form-select" id="filtro-coordinador" name="coordinador_id">
                <option value="">{{ __('Todos') }}</option>
                @foreach ($coordinadores as $coordinador)
                    <option value="{{ $coordinador->id }}" @selected(($filters['coordinador_id'] ?? null) === $coordinador->id)>{{ $coordinador->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label text-muted small" for="filtro-creador">{{ __('Capturado por') }}</label>
            <select class="form-select" id="filtro-creador" name="creado_por">
                <option value="">{{ __('Todos') }}</option>
                @foreach ($creadores as $creador)
                    <option value="{{ $creador->id }}" @selected(($filters['creado_por'] ?? null) === $creador->id)>{{ $creador->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1 d-grid align-content-end">
            <button class="btn btn-outline-primary" type="submit">{{ __('Filtrar') }}</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>{{ __('No. de control') }}</th>
                    <th>{{ __('Paciente') }}</th>
                    <th>{{ __('Estado') }}</th>
                    <th>{{ __('Apertura') }}</th>
                    <th>{{ __('Tutor') }}</th>
                    <th>{{ __('Coordinador') }}</th>
                    <th>{{ __('Capturado por') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expedientes as $expediente)
                    <tr>
                        <td class="fw-semibold">{{ $expediente->no_control }}</td>
                        <td>{{ $expediente->paciente }}</td>
                        <td>
                            @switch($expediente->estado)
                                @case('abierto')
                                    <span class="badge bg-secondary">{{ __('Abierto') }}</span>
                                    @break

                                @case('revision')
                                    <span class="badge bg-warning text-dark">{{ __('En revisión') }}</span>
                                    @break

                                @case('cerrado')
                                    <span class="badge bg-success">{{ __('Cerrado') }}</span>
                                    @break

                                @default
                                    <span class="badge bg-light text-dark">{{ __('Sin estado') }}</span>
                            @endswitch
                        </td>
                        <td>{{ optional($expediente->apertura)->format('Y-m-d') }}</td>
                        <td>{{ optional($expediente->tutor)->name ?? '—' }}</td>
                        <td>{{ optional($expediente->coordinador)->name ?? '—' }}</td>
                        <td>{{ optional($expediente->creadoPor)->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">{{ __('No se encontraron expedientes con los filtros seleccionados.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $expedientes->links('pagination::bootstrap-5') }}
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            flatpickr('.flatpickr', { dateFormat: 'Y-m-d' });

            const exportButtons = document.querySelectorAll('[data-export-format]');
            const form = document.getElementById('report-filters-form');
            const feedback = document.getElementById('export-feedback');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const exportUrl = @json(route('reportes.expedientes.export'));
            let pollTimer = null;

            const showMessage = (type, message) => {
                feedback.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
                feedback.classList.add(`alert-${type}`);
                feedback.textContent = message;
            };

            const setButtonsDisabled = (disabled) => {
                exportButtons.forEach((button) => {
                    button.disabled = disabled;
                });
            };

            const startPolling = (statusUrl) => {
                if (pollTimer) {
                    clearInterval(pollTimer);
                }

                pollTimer = setInterval(() => {
                    fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                        .then((response) => {
                            if (!response.ok) {
                                throw new Error('status-error');
                            }

                            return response.json();
                        })
                        .then((payload) => {
                            if (payload.status === 'ready' && payload.download_url) {
                                clearInterval(pollTimer);
                                pollTimer = null;
                                showMessage('success', @json(__('El archivo está listo, iniciando descarga...')));
                                window.location.href = payload.download_url;
                                setButtonsDisabled(false);
                            }
                        })
                        .catch(() => {
                            clearInterval(pollTimer);
                            pollTimer = null;
                            showMessage('danger', @json(__('No se pudo verificar el estado de la exportación. Intenta nuevamente.')));
                            setButtonsDisabled(false);
                        });
                }, 4000);
            };

            exportButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();

                    const format = button.dataset.exportFormat;
                    const formData = new FormData(form);
                    formData.append('format', format);

                    setButtonsDisabled(true);
                    showMessage('warning', @json(__('Generando reporte, espera un momento...')));

                    fetch(exportUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: formData,
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
                                showMessage('success', payload.message ?? @json(__('El archivo está listo.')));
                                window.location.href = payload.download_url;
                                setButtonsDisabled(false);
                            } else if (payload.status === 'pending' && payload.status_url) {
                                showMessage('info', payload.message ?? @json(__('El reporte se está procesando, te avisaremos cuando esté listo.')));
                                startPolling(payload.status_url);
                            } else {
                                throw new Error('invalid-payload');
                            }
                        })
                        .catch((error) => {
                            if (error?.errors) {
                                const firstError = Object.values(error.errors)[0]?.[0] ?? @json(__('No fue posible generar el reporte.'));
                                showMessage('danger', firstError);
                            } else {
                                showMessage('danger', @json(__('No fue posible generar el reporte.')));
                            }

                            setButtonsDisabled(false);
                        });
                });
            });
        });
    </script>
@endpush
