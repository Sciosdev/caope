@extends('layouts.noble')

@section('title', 'Expedientes')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4 class="mb-0">Expedientes</h4>

        @can('create', App\Models\Expediente::class)
            <a href="{{ route('expedientes.create') }}" class="btn btn-primary">
                <i class="mdi mdi-plus"></i>
                Nuevo expediente
            </a>
        @endcan
    </div>

    <form class="row g-3 mb-4" method="get">
        <div class="col-md-3">
            <label class="form-label text-muted small" for="filtro-q">Buscar</label>
            <input id="filtro-q" name="q" value="{{ $q }}" class="form-control" placeholder="Número de control o paciente">
        </div>
        <div class="col-md-2">
            <label class="form-label text-muted small" for="filtro-estado">Estado</label>
            <select id="filtro-estado" name="estado" class="form-select">
                <option value="">Todos</option>
                <option value="abierto" @selected($estado === 'abierto')>Abierto</option>
                <option value="revision" @selected($estado === 'revision')>En revisión</option>
                <option value="cerrado" @selected($estado === 'cerrado')>Cerrado</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-muted small" for="filtro-carrera">Carrera</label>
            <select id="filtro-carrera" name="carrera" class="form-select">
                <option value="">Todas</option>
                @foreach ($carreras as $carreraNombre)
                    <option value="{{ $carreraNombre }}" @selected($carrera === $carreraNombre)>{{ $carreraNombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-muted small" for="filtro-turno">Turno</label>
            <select id="filtro-turno" name="turno" class="form-select">
                <option value="">Todos</option>
                @foreach ($turnos as $turnoNombre)
                    <option value="{{ $turnoNombre }}" @selected($turno === $turnoNombre)>{{ $turnoNombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label text-muted small" for="filtro-desde">Desde</label>
            <input id="filtro-desde" name="desde" value="{{ optional($desde)->format('Y-m-d') }}" class="form-control flatpickr" placeholder="AAAA-MM-DD">
        </div>
        <div class="col-md-1">
            <label class="form-label text-muted small" for="filtro-hasta">Hasta</label>
            <input id="filtro-hasta" name="hasta" value="{{ optional($hasta)->format('Y-m-d') }}" class="form-control flatpickr" placeholder="AAAA-MM-DD">
        </div>
        <div class="col-md-1 d-grid align-content-end">
            <button class="btn btn-outline-primary" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="table-responsive">
        <table id="expedientes-table" class="table table-striped">
            <thead>
                <tr>
                    <th>No. de Control</th>
                    <th>Paciente</th>
                    <th>Estado</th>
                    <th>Apertura</th>
                    <th>Carrera</th>
                    <th>Turno</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($expedientes as $expediente)
                    <tr>
                        <td class="fw-semibold">{{ $expediente->no_control }}</td>
                        <td>
                            @can('viewFullName', $expediente)
                                {{ $expediente->paciente }}
                            @else
                                {{ $expediente->paciente_masked }}
                            @endcan
                        </td>
                        <td>
                            @switch($expediente->estado)
                                @case('abierto')
                                    <span class="badge bg-secondary">Abierto</span>
                                    @break

                                @case('revision')
                                    <span class="badge bg-warning">En revisión</span>
                                    @break

                                @case('cerrado')
                                    <span class="badge bg-success">Cerrado</span>
                                    @break

                                @default
                                    <span class="badge bg-light text-dark">Sin estado</span>
                                    @break
                            @endSwitch
                        </td>
                        <td>{{ optional($expediente->apertura)->format('Y-m-d') }}</td>
                        <td>{{ $expediente->carrera }}</td>
                        <td>{{ $expediente->turno }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="{{ route('expedientes.show', $expediente) }}" class="btn btn-outline-secondary">Ver</a>
                                @can('update', $expediente)
                                    <a href="{{ route('expedientes.edit', $expediente) }}" class="btn btn-outline-secondary">Editar</a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">No se encontraron expedientes con los filtros seleccionados.</td>
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
        flatpickr('.flatpickr', { dateFormat: 'Y-m-d' });

        new DataTable('#expedientes-table', {
            responsive: true,
            searching: false,
            paging: false,
            info: false,
        });
    </script>
@endpush
