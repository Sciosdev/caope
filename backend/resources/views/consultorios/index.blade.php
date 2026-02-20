<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Consultorios</li>
    @endsection

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Reserva de consultorios</h4>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header">Nueva asignación</div>
        <div class="card-body">
            <form method="POST" action="{{ route('consultorios.store') }}" class="row g-3">
                @csrf
                <div class="col-md-2">
                    <label class="form-label">Día</label>
                    <input type="date" name="fecha" class="form-control" value="{{ old('fecha', now()->toDateString()) }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Inicio</label>
                    <input type="time" name="hora_inicio" class="form-control" min="07:00" max="22:00" value="{{ old('hora_inicio', '07:00') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Fin</label>
                    <input type="time" name="hora_fin" class="form-control" min="07:00" max="22:00" value="{{ old('hora_fin', '08:00') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Consultorio</label>
                    <select name="consultorio_numero" class="form-select" required>
                        @for ($i = 1; $i <= 14; $i++)
                            <option value="{{ $i }}" @selected((int) old('consultorio_numero', 1) === $i)>Consultorio {{ $i }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cubículo</label>
                    <select name="cubiculo_numero" class="form-select" required>
                        @for ($i = 1; $i <= 14; $i++)
                            <option value="{{ $i }}" @selected((int) old('cubiculo_numero', 1) === $i)>Cubículo {{ $i }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estrategia</label>
                    <input type="text" name="estrategia" class="form-control" value="{{ old('estrategia') }}" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Usuario atendido</label>
                    <select name="usuario_atendido_id" class="form-select">
                        <option value="">--</option>
                        @foreach ($usuarios as $usuario)
                            <option value="{{ $usuario->id }}" @selected((int) old('usuario_atendido_id') === $usuario->id)>{{ $usuario->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Estratega</label>
                    <select name="estratega_id" class="form-select">
                        <option value="">--</option>
                        @foreach ($docentes as $usuario)
                            <option value="{{ $usuario->id }}" @selected((int) old('estratega_id') === $usuario->id)>{{ $usuario->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Supervisor</label>
                    <select name="supervisor_id" class="form-select">
                        <option value="">--</option>
                        @foreach ($usuarios as $usuario)
                            <option value="{{ $usuario->id }}" @selected((int) old('supervisor_id') === $usuario->id)>{{ $usuario->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Asignar espacio</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Calendario de ocupación por cubículo</span>
            <form method="GET" class="d-flex gap-2">
                <input type="date" name="fecha" class="form-control" value="{{ $fechaFiltro }}">
                <select name="consultorio_numero" class="form-select">
                    @for ($i = 1; $i <= 14; $i++)
                        <option value="{{ $i }}" @selected($consultorioSeleccionado === $i)>Consultorio {{ $i }}</option>
                    @endfor
                </select>
                <button class="btn btn-outline-secondary">Ver</button>
            </form>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @for ($i = 1; $i <= 14; $i++)
                    <div class="col-md-6 col-xl-4">
                        <div class="border rounded p-3 h-100">
                            <h6>Cubículo {{ $i }}</h6>
                            @php $items = $ocupacionPorCubiculo->get($i, collect()); @endphp
                            @if ($items->isEmpty())
                                <p class="text-muted mb-0 small">Sin ocupación</p>
                            @else
                                <ul class="list-unstyled small mb-0">
                                    @foreach ($items as $item)
                                        <li class="mb-2">
                                            <strong>{{ substr($item->hora_inicio, 0, 5) }} - {{ substr($item->hora_fin, 0, 5) }}</strong><br>
                                            {{ $item->estrategia }}<br>
                                            Usuario: {{ $item->usuarioAtendido?->name ?? '—' }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Bitácora de asignaciones (alta, baja y modificación)</div>
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Horario</th>
                        <th>Cubículo</th>
                        <th>Estrategia</th>
                        <th>Estratega</th>
                        <th>Supervisor</th>
                        <th>Usuario</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reservas as $reserva)
                        <tr>
                            <td>{{ $reserva->fecha->format('Y-m-d') }}</td>
                            <td>{{ substr($reserva->hora_inicio, 0, 5) }} - {{ substr($reserva->hora_fin, 0, 5) }}</td>
                            <td>Consultorio {{ $reserva->consultorio_numero }} · Cubículo {{ $reserva->cubiculo_numero }}</td>
                            <td>{{ $reserva->estrategia }}</td>
                            <td>{{ $reserva->estratega?->name ?? '—' }}</td>
                            <td>{{ $reserva->supervisor?->name ?? '—' }}</td>
                            <td>{{ $reserva->usuarioAtendido?->name ?? '—' }}</td>
                            <td>
                                <div class="d-flex gap-2">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('consultorios.edit', $reserva) }}">Modificar</a>
                                <form action="{{ route('consultorios.destroy', $reserva) }}" method="POST" onsubmit="return confirm('¿Eliminar reserva?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Baja</button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">Sin registros.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $reservas->links('pagination::bootstrap-5') }}
        </div>
    </div>
</x-app-layout>
