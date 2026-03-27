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
            <button type="button" class="btn btn-outline-secondary" data-download-format="csv">
                <i class="mdi mdi-download"></i>
                {{ __('Descargar CSV') }}
            </button>
            <button type="button" class="btn btn-primary" data-download-format="xlsx">
                <i class="mdi mdi-file-excel"></i>
                {{ __('Descargar XLSX') }}
            </button>
        </div>
    </div>

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
            <label class="form-label text-muted small" for="filtro-tutor">{{ __('Estratega') }}</label>
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
                    <th>{{ __('Consultante') }}</th>
                    <th>{{ __('Estado') }}</th>
                    <th>{{ __('Apertura') }}</th>
                    <th>{{ __('Estratega') }}</th>
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
            if (typeof flatpickr === 'function') {
                flatpickr('.flatpickr', { dateFormat: 'Y-m-d' });
            }

            const downloadButtons = document.querySelectorAll('[data-download-format]');
            const form = document.getElementById('report-filters-form');
            const downloadUrl = @json(route('reportes.expedientes.download-direct', [], false));

            downloadButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();

                    const format = button.dataset.downloadFormat;
                    const params = new URLSearchParams(new FormData(form));
                    params.set('format', format);

                    window.location.href = `${downloadUrl}?${params.toString()}`;
                });
            });
        });
    </script>
@endpush
