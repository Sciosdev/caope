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

    @if(auth()->user()?->hasRole('admin'))
    <div class="card mb-4">
        <div class="card-header">Nueva asignación</div>
        <div class="card-body">
            <form method="POST" action="{{ route('consultorios.store') }}" class="row g-3" id="nueva-asignacion-form">
                @csrf
                <div class="col-md-2">
                    <label class="form-label">Día</label>
                    <input type="date" name="fecha" class="form-control" id="asignacion-fecha" value="{{ old('fecha', now()->toDateString()) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label d-block">Tipo de registro</label>
                    <div class="d-flex gap-3 mt-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="modo_repeticion" id="modo-repeticion-unica" value="unica" @checked(old('modo_repeticion', 'unica') === 'unica')>
                            <label class="form-check-label" for="modo-repeticion-unica">Fecha única</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="modo_repeticion" id="modo-repeticion-semanal" value="semanal" @checked(old('modo_repeticion') === 'semanal')>
                            <label class="form-check-label" for="modo-repeticion-semanal">Repetición semanal</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 d-none" id="repeticion-config">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Desde</label>
                            <input type="date" name="fecha_inicio_repeticion" class="form-control" value="{{ old('fecha_inicio_repeticion', now()->toDateString()) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Hasta</label>
                            <input type="date" name="fecha_fin_repeticion" class="form-control" value="{{ old('fecha_fin_repeticion', now()->addMonth()->toDateString()) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Días</label>
                            <div class="d-flex flex-wrap gap-2 small pt-1">
                                @foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb'] as $dayNumber => $dayLabel)
                                    <label class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="checkbox" name="dias_semana[]" value="{{ $dayNumber }}" @checked(in_array($dayNumber, old('dias_semana', [])))>
                                        <span class="form-check-label">{{ $dayLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
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
    @endif

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Calendario de ocupación por cubículo</span>
            <form method="GET" class="d-flex gap-2" id="ocupacion-filtro-form">
                <input type="month" class="form-control" id="ocupacion-mes" value="{{ \Illuminate\Support\Carbon::parse($fechaFiltro)->format('Y-m') }}">
                <input type="hidden" name="fecha" id="ocupacion-fecha" value="{{ $fechaFiltro }}">
                <select name="consultorio_numero" class="form-select" id="ocupacion-consultorio">
                    @for ($i = 1; $i <= 14; $i++)
                        <option value="{{ $i }}" @selected($consultorioSeleccionado === $i)>Consultorio {{ $i }}</option>
                    @endfor
                </select>
                <button class="btn btn-outline-secondary" type="submit" id="ocupacion-ver-btn">Ver día</button>
            </form>
        </div>
        <div class="card-body pb-0">
            <div id="disponibilidad-alerta" class="alert alert-warning d-none" role="alert"></div>
        </div>
        <div class="card-body">
            <div id="ocupacion-calendario" class="mb-4"></div>
            <h6 class="mb-3">Detalle del día seleccionado: <span id="ocupacion-dia-seleccionado">{{ $fechaFiltro }}</span></h6>
            <div class="row g-3">
                @for ($i = 1; $i <= 14; $i++)
                    <div class="col-md-6 col-xl-4" data-cubiculo-card="{{ $i }}">
                        <div class="border rounded p-3 h-100" data-cubiculo-container>
                            <h6>Cubículo {{ $i }}</h6>
                            @php $items = $ocupacionPorCubiculo->get($i, collect()); @endphp
                            <div data-cubiculo-content>
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
                    </div>
                @endfor
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span>Bitácora de asignaciones (alta, baja y modificación)</span>
            <div class="d-flex gap-2 align-items-center">
                <input type="date" class="form-control" id="bitacora-fecha-base" value="{{ $fechaFiltro }}">
                <select class="form-select" id="bitacora-vista">
                    <option value="semana">Vista semanal</option>
                    <option value="mes">Vista mensual</option>
                </select>
            </div>
        </div>
        <div class="card-body border-bottom">
            <div id="bitacora-vista-dinamica" class="table-responsive"></div>
        </div>
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
                        @if(auth()->user()?->hasRole('admin'))<th>Acciones</th>@endif
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
                            @if(auth()->user()?->hasRole('admin'))
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
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth()->user()?->hasRole('admin') ? 7 : 6 }}" class="text-center text-muted">Sin registros.</td></tr>
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
        const filtroMes = document.getElementById('ocupacion-mes');
        const filtroConsultorio = document.getElementById('ocupacion-consultorio');
        const cubiculoCards = document.querySelectorAll('[data-cubiculo-card]');
        const calendarioContainer = document.getElementById('ocupacion-calendario');
        const diaSeleccionadoLabel = document.getElementById('ocupacion-dia-seleccionado');
        const bitacoraFechaBase = document.getElementById('bitacora-fecha-base');
        const bitacoraVista = document.getElementById('bitacora-vista');
        const bitacoraContainer = document.getElementById('bitacora-vista-dinamica');
        const repeticionConfig = document.getElementById('repeticion-config');
        const modoRepeticionInputs = document.querySelectorAll('input[name="modo_repeticion"]');
        const weekDayLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

        const hideAlert = () => {
            alerta.textContent = '';
            alerta.classList.add('d-none');
        };

        const showAlert = (message) => {
            alerta.textContent = message;
            alerta.classList.remove('d-none');
        };

        const renderCubiculoItems = (cubiculo, items) => {
            const card = document.querySelector(`[data-cubiculo-card="${cubiculo}"]`);
            if (!card) {
                return;
            }

            const container = card.querySelector('[data-cubiculo-container]');
            const content = card.querySelector('[data-cubiculo-content]');
            if (!container || !content) {
                return;
            }

            if (!items.length) {
                container.classList.remove('border-danger-subtle', 'bg-danger-subtle');
                container.classList.add('border-success-subtle', 'bg-success-subtle');
                content.innerHTML = '<p class="text-muted mb-0 small">Sin ocupación</p>';
                return;
            }

            container.classList.remove('border-success-subtle', 'bg-success-subtle');
            container.classList.add('border-danger-subtle', 'bg-danger-subtle');
            const rows = items.map((item) => {
                const inicio = (item.hora_inicio ?? '').slice(0, 5);
                const fin = (item.hora_fin ?? '').slice(0, 5);
                const estrategia = item.estrategia ?? '—';
                return `<li class="mb-2"><strong>${inicio} - ${fin}</strong><br>${estrategia}</li>`;
            }).join('');

            content.innerHTML = `<ul class="list-unstyled small mb-0">${rows}</ul>`;
        };

        const dateISO = (date) => {
            const fixedDate = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
            return fixedDate.toISOString().slice(0, 10);
        };

        const monthBounds = (monthValue) => {
            const [year, month] = monthValue.split('-').map(Number);
            const start = new Date(year, month - 1, 1);
            const end = new Date(year, month, 0);

            return {
                start,
                end,
                startISO: dateISO(start),
                endISO: dateISO(end),
            };
        };

        const toggleRepeatConfig = () => {
            const mode = document.querySelector('input[name="modo_repeticion"]:checked')?.value ?? 'unica';
            repeticionConfig?.classList.toggle('d-none', mode !== 'semanal');
            if (formFecha) {
                formFecha.required = mode !== 'semanal';
            }
        };

        const fetchAvailability = async (params) => {
            const response = await fetch(`${availabilityEndpoint}?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                return null;
            }

            return response.json();
        };

        const renderMonthCalendar = (monthValue, groupedByDate) => {
            if (!calendarioContainer) {
                return;
            }

            const { start, end } = monthBounds(monthValue);
            const firstDayOffset = (start.getDay() + 6) % 7;
            const cells = [];
            for (let i = 0; i < firstDayOffset; i += 1) {
                cells.push('<div class="border rounded bg-light"></div>');
            }

            for (let day = 1; day <= end.getDate(); day += 1) {
                const current = new Date(start.getFullYear(), start.getMonth(), day);
                const iso = dateISO(current);
                const isSelected = iso === filtroFecha.value;
                const items = groupedByDate[iso] ?? [];
                const occupiedCubicles = new Set(items.map((item) => Number(item.cubiculo_numero))).size;
                cells.push(`
                    <button type="button" class="btn btn-sm border rounded text-start p-2 h-100 ${isSelected ? 'btn-primary text-white' : 'btn-light'}" data-calendar-day="${iso}">
                        <div class="fw-semibold">${day}</div>
                        <div class="small">${occupiedCubicles} cubículos ocupados</div>
                    </button>
                `);
            }

            calendarioContainer.innerHTML = `
                <div class="d-grid gap-2" style="grid-template-columns: repeat(7, minmax(0,1fr));">
                    ${weekDayLabels.map((label) => `<div class="text-center text-muted small fw-semibold">${label}</div>`).join('')}
                    ${cells.join('')}
                </div>
            `;
        };

        const renderBitacoraGrid = (items) => {
            if (!bitacoraContainer || !bitacoraFechaBase?.value) {
                return;
            }

            const mode = bitacoraVista?.value ?? 'semana';
            const base = new Date(`${bitacoraFechaBase.value}T00:00:00`);
            let rangeDates = [];

            if (mode === 'semana') {
                const day = base.getDay() || 7;
                const monday = new Date(base);
                monday.setDate(base.getDate() - day + 1);
                for (let i = 0; i < 6; i += 1) {
                    const current = new Date(monday);
                    current.setDate(monday.getDate() + i);
                    rangeDates.push(current);
                }
            } else {
                const { start, end } = monthBounds(bitacoraFechaBase.value.slice(0, 7));
                for (let i = 1; i <= end.getDate(); i += 1) {
                    rangeDates.push(new Date(start.getFullYear(), start.getMonth(), i));
                }
            }

            const dateHeaders = rangeDates.map((date) => dateISO(date));
            const grouped = items.reduce((carry, item) => {
                const key = `${item.cubiculo_numero}_${item.fecha}`;
                if (!carry[key]) {
                    carry[key] = [];
                }
                carry[key].push(item);
                return carry;
            }, {});

            const rows = Array.from({ length: 14 }, (_, index) => index + 1).map((cubiculo) => {
                const columns = dateHeaders.map((fecha) => {
                    const matches = grouped[`${cubiculo}_${fecha}`] ?? [];
                    if (!matches.length) {
                        return '<td class="text-muted">—</td>';
                    }

                    const summary = matches.map((item) => `${item.hora_inicio.slice(0, 5)}-${item.hora_fin.slice(0, 5)}`).join('<br>');

                    return `<td class="small"><strong>${matches.length}</strong><br>${summary}</td>`;
                }).join('');

                return `<tr><th class="text-nowrap">Cubículo ${cubiculo}</th>${columns}</tr>`;
            }).join('');

            bitacoraContainer.innerHTML = `
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Cubículo</th>
                            ${dateHeaders.map((fecha) => `<th class="small text-nowrap">${fecha}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            `;
        };

        const refreshCalendar = async () => {
            if (!filtroMes?.value || !filtroConsultorio?.value) {
                return;
            }

            try {
                const bounds = monthBounds(filtroMes.value);
                const params = new URLSearchParams({
                    fecha_inicio: bounds.startISO,
                    fecha_fin: bounds.endISO,
                    consultorio_numero: filtroConsultorio.value,
                });
                const data = await fetchAvailability(params);
                if (!data) {
                    return;
                }

                const groupedByDate = (data.reservas ?? []).reduce((carry, item) => {
                    const key = item.fecha;
                    if (!carry[key]) {
                        carry[key] = [];
                    }
                    carry[key].push(item);
                    return carry;
                }, {});

                const selectedDate = filtroFecha.value && groupedByDate[filtroFecha.value] ? filtroFecha.value : bounds.startISO;
                filtroFecha.value = selectedDate;
                diaSeleccionadoLabel.textContent = selectedDate;

                renderMonthCalendar(filtroMes.value, groupedByDate);
                const dayItems = (groupedByDate[selectedDate] ?? []).reduce((carry, item) => {
                    const key = Number(item.cubiculo_numero);
                    if (!carry[key]) {
                        carry[key] = [];
                    }
                    carry[key].push(item);
                    return carry;
                }, {});

                cubiculoCards.forEach((card) => {
                    const cubiculo = Number(card.dataset.cubiculoCard);
                    renderCubiculoItems(cubiculo, dayItems[cubiculo] ?? []);
                });

                renderBitacoraGrid(data.reservas ?? []);
            } catch (error) {
                console.error(error);
            }
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

        if (formConsultorio && formCubiculo && formFecha && formHoraInicio && formHoraFin) {
            formConsultorio.addEventListener('change', checkAvailability);
            formCubiculo.addEventListener('change', () => {
                checkAvailability();
            });
            formFecha.addEventListener('change', checkAvailability);
            formHoraInicio.addEventListener('change', checkAvailability);
            formHoraFin.addEventListener('change', checkAvailability);
        }
        modoRepeticionInputs.forEach((input) => {
            input.addEventListener('change', toggleRepeatConfig);
        });
        toggleRepeatConfig();

        calendarioContainer?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-calendar-day]');
            if (!button) {
                return;
            }

            filtroFecha.value = button.dataset.calendarDay;
            diaSeleccionadoLabel.textContent = filtroFecha.value;
            refreshCalendar();
        });

        filtroMes?.addEventListener('change', refreshCalendar);
        filtroFecha?.addEventListener('change', refreshCalendar);
        filtroConsultorio?.addEventListener('change', refreshCalendar);
        bitacoraFechaBase?.addEventListener('change', refreshCalendar);
        bitacoraVista?.addEventListener('change', refreshCalendar);

        refreshCalendar();
        setInterval(refreshCalendar, 30000);
    });
</script>
@endpush
