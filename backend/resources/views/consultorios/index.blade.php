<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
        <li class="breadcrumb-item active" aria-current="page">Consultorios</li>
    @endsection

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Reserva de consultorios</h4>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>No se pudo registrar la asignación.</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">Nueva asignación</div>
        <div class="card-body">
            <form method="POST" action="{{ route('consultorios.store') }}" class="row g-3" id="nueva-asignacion-form">
                @csrf
                <div class="col-md-2">
                    <label class="form-label">Día</label>
                    <input type="date" name="fecha" class="form-control" id="asignacion-fecha" value="{{ old('fecha', now()->toDateString()) }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Inicio</label>
                    <input type="time" name="hora_inicio" class="form-control" id="asignacion-hora-inicio" min="07:00" max="22:00" value="{{ old('hora_inicio', '07:00') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Fin</label>
                    <input type="time" name="hora_fin" class="form-control" id="asignacion-hora-fin" min="07:00" max="22:00" value="{{ old('hora_fin', '08:00') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Consultorio</label>
                    <select name="consultorio_numero" class="form-select" id="asignacion-consultorio" required>
                        @for ($i = 1; $i <= 14; $i++)
                            <option value="{{ $i }}" @selected((int) old('consultorio_numero', 1) === $i)>Consultorio {{ $i }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cubículo</label>
                    <select name="cubiculo_numero" class="form-select" id="asignacion-cubiculo" required>
                        @for ($i = 1; $i <= 14; $i++)
                            <option value="{{ $i }}" @selected((int) old('cubiculo_numero', 1) === $i)>Cubículo {{ $i }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-4">
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
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Asignar espacio</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Calendario de ocupación por cubículo</span>
            <form method="GET" class="d-flex gap-2" id="ocupacion-filtro-form">
                <input type="date" name="fecha" class="form-control" id="ocupacion-fecha" value="{{ $fechaFiltro }}">
                <select name="consultorio_numero" class="form-select" id="ocupacion-consultorio">
                    @for ($i = 1; $i <= 14; $i++)
                        <option value="{{ $i }}" @selected($consultorioSeleccionado === $i)>Consultorio {{ $i }}</option>
                    @endfor
                </select>
                <select name="cubiculo_numero" class="form-select" id="ocupacion-cubiculo">
                    <option value="">Todos los cubículos</option>
                    @for ($i = 1; $i <= 14; $i++)
                        <option value="{{ $i }}" @selected($cubiculoSeleccionado === $i)>Cubículo {{ $i }}</option>
                    @endfor
                </select>
                <button class="btn btn-outline-secondary" id="ocupacion-ver-btn">Ver</button>
            </form>
        </div>
        <div class="card-body pb-0">
            <div id="disponibilidad-alerta" class="alert alert-warning d-none" role="alert"></div>
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
                        <tr><td colspan="7" class="text-center text-muted">Sin registros.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $reservas->links('pagination::bootstrap-5') }}
        </div>
    </div>
</x-app-layout>


@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const availabilityEndpoint = @json(route('consultorios.availability'));
        const formFecha = document.getElementById('asignacion-fecha');
        const formConsultorio = document.getElementById('asignacion-consultorio');
        const formCubiculo = document.getElementById('asignacion-cubiculo');
        const formHoraInicio = document.getElementById('asignacion-hora-inicio');
        const formHoraFin = document.getElementById('asignacion-hora-fin');
        const alerta = document.getElementById('disponibilidad-alerta');
        const filtroFecha = document.getElementById('ocupacion-fecha');
        const filtroConsultorio = document.getElementById('ocupacion-consultorio');
        const filtroCubiculo = document.getElementById('ocupacion-cubiculo');
        const filtroForm = document.getElementById('ocupacion-filtro-form');

        const hideAlert = () => {
            alerta.textContent = '';
            alerta.classList.add('d-none');
        };

        const showAlert = (message) => {
            alerta.textContent = message;
            alerta.classList.remove('d-none');
        };

        const refreshCalendar = () => {
            if (!filtroForm) {
                return;
            }

            filtroFecha.value = formFecha.value;
            filtroConsultorio.value = formConsultorio.value;
            filtroCubiculo.value = formCubiculo.value;
            filtroForm.submit();
        };

        const hasOverlap = (items, start, end, cubiculo) => {
            return items.find((item) => Number(item.cubiculo_numero) === Number(cubiculo)
                && item.hora_inicio < end
                && item.hora_fin > start);
        };

        const checkAvailability = async () => {
            hideAlert();

            if (!formFecha.value || !formConsultorio.value || !formCubiculo.value || !formHoraInicio.value || !formHoraFin.value) {
                return;
            }

            if (formHoraInicio.value >= formHoraFin.value) {
                showAlert('La hora de inicio debe ser menor a la hora de fin.');
                return;
            }

            try {
                const params = new URLSearchParams({
                    fecha: formFecha.value,
                    consultorio_numero: formConsultorio.value,
                });
                const response = await fetch(`${availabilityEndpoint}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                const overlap = hasOverlap(data.reservas ?? [], formHoraInicio.value, formHoraFin.value, formCubiculo.value);

                if (overlap) {
                    showAlert(`⚠️ El Consultorio ${formConsultorio.value}, Cubículo ${formCubiculo.value} ya está ocupado de ${overlap.hora_inicio.slice(0, 5)} a ${overlap.hora_fin.slice(0, 5)}.`);
                }
            } catch (error) {
                console.error(error);
            }
        };

        formConsultorio.addEventListener('change', () => {
            refreshCalendar();
        });

        formCubiculo.addEventListener('change', () => {
            checkAvailability();
            refreshCalendar();
        });
        formFecha.addEventListener('change', () => {
            refreshCalendar();
        });
        formHoraInicio.addEventListener('change', checkAvailability);
        formHoraFin.addEventListener('change', checkAvailability);
    });
</script>
@endpush
