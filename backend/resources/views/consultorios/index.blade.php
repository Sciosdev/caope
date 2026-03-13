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
                            <label class="form-label">Día hábil</label>
                            @php
                                $diaSeleccionado = (int) collect(old('dias_semana', []))->first();
                            @endphp
                            <select class="form-select" name="dias_semana[]" id="repeticion-dia-semana">
                                <option value="">Selecciona un día</option>
                                @foreach ([1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'] as $dayNumber => $dayLabel)
                                    <option value="{{ $dayNumber }}" @selected($diaSeleccionado === $dayNumber)>{{ $dayLabel }}</option>
                                @endforeach
                            </select>
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
                        @foreach ($consultoriosActivos as $consultorio)
                            <option value="{{ $consultorio->numero }}" @selected((int) old('consultorio_numero', (int) ($consultoriosActivos->first()->numero ?? 1)) === (int) $consultorio->numero)>{{ $consultorio->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cubículo</label>
                    <select name="cubiculo_numero" class="form-select" id="asignacion-cubiculo" required>
                        @foreach ($cubiculosActivos as $cubiculo)
                            <option value="{{ $cubiculo->numero }}" @selected((int) old('cubiculo_numero', (int) ($cubiculosActivos->first()->numero ?? 1)) === (int) $cubiculo->numero)>{{ $cubiculo->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Estrategia</label>
                    <select name="estrategia" class="form-select" required>
                        <option value="">Selecciona una estrategia</option>
                        @foreach ($estrategiasActivas as $estrategia)
                            <option value="{{ $estrategia->nombre }}" @selected(old('estrategia') === $estrategia->nombre)>{{ $estrategia->nombre }}</option>
                        @endforeach
                    </select>
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
                <input type="date" class="form-control" name="fecha" id="ocupacion-fecha" value="{{ $fechaFiltro }}">
                <select name="consultorio_numero" class="form-select" id="ocupacion-consultorio">
                    @foreach ($consultoriosActivos as $consultorio)
                        <option value="{{ $consultorio->numero }}" @selected($consultorioSeleccionado === (int) $consultorio->numero)>{{ $consultorio->nombre }}</option>
                    @endforeach
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
            @php
                $detalleDiaSeleccionado = $ocupacionPorCubiculo
                    ->flatten(1)
                    ->sortBy(['hora_inicio', 'cubiculo_numero'])
                    ->values();
            @endphp
            <div id="ocupacion-dia-detalle" class="row g-3">
                @forelse ($detalleDiaSeleccionado as $item)
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <p class="mb-1"><strong>{{ substr($item->hora_inicio, 0, 5) }} - {{ substr($item->hora_fin, 0, 5) }}</strong></p>
                            <p class="mb-1">Consultorio {{ $item->consultorio_numero }} · Cubículo {{ $item->cubiculo_numero }}</p>
                            <p class="mb-1">{{ $item->estrategia }}</p>
                            <p class="mb-0 text-muted small">Usuario: {{ $item->usuarioAtendido?->name ?? '—' }}</p>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-light border mb-0">No hay registros para este día.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span>Bitácora de asignaciones (alta, baja y modificación)</span>
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="fecha" value="{{ $fechaFiltro }}">
                <input type="hidden" name="consultorio_numero" value="{{ $consultorioSeleccionado }}">
                @if($cubiculoSeleccionado)
                    <input type="hidden" name="cubiculo_numero" value="{{ $cubiculoSeleccionado }}">
                @endif
                <input type="date" class="form-control" id="bitacora-fecha-base" name="bitacora_inicio" value="{{ $bitacoraInicio }}" aria-label="Fecha base de bitácora">
                <select class="form-select" id="bitacora-modo" name="bitacora_modo" aria-label="Modo de vista de bitácora">
                    <option value="semana" @selected($bitacoraModo === 'semana')>Semana</option>
                    <option value="mes" @selected($bitacoraModo === 'mes')>Mes</option>
                </select>
                <button type="submit" class="btn btn-outline-secondary" id="bitacora-aplicar-filtro">Mostrar</button>
            </form>
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
    const initConsultoriosPage = () => {
        if (window.__consultoriosPageInitialized) {
            return;
        }
        window.__consultoriosPageInitialized = true;
        const availabilityEndpoint = @json(route('consultorios.availability'));
        const formFecha = document.getElementById('asignacion-fecha');
        const formConsultorio = document.getElementById('asignacion-consultorio');
        const formCubiculo = document.getElementById('asignacion-cubiculo');
        const formHoraInicio = document.getElementById('asignacion-hora-inicio');
        const formHoraFin = document.getElementById('asignacion-hora-fin');
        const alerta = document.getElementById('disponibilidad-alerta');
        const filtroFecha = document.getElementById('ocupacion-fecha');
        const filtroConsultorio = document.getElementById('ocupacion-consultorio');
        const calendarioContainer = document.getElementById('ocupacion-calendario');
        const diaSeleccionadoLabel = document.getElementById('ocupacion-dia-seleccionado');
        const detalleDiaContainer = document.getElementById('ocupacion-dia-detalle');
        const bitacoraFechaBase = document.getElementById('bitacora-fecha-base');
        const bitacoraModo = document.getElementById('bitacora-modo');
        const bitacoraAplicarFiltro = document.getElementById('bitacora-aplicar-filtro');
        const bitacoraContainer = document.getElementById('bitacora-vista-dinamica');
        const repeticionConfig = document.getElementById('repeticion-config');
        const modoRepeticionInputs = document.querySelectorAll('input[name="modo_repeticion"]');
        const diaSemanaSelect = document.getElementById('repeticion-dia-semana');
        const catalogoCubiculos = @json($cubiculosActivos->pluck('numero')->map(fn ($numero) => (int) $numero)->values());
        const weekDayLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        const TOTAL_CUBICULOS = catalogoCubiculos.length || 14;
        const JORNADA_INICIO = 7 * 60;
        const JORNADA_FIN = 22 * 60;

        const timeToMinutes = (time) => {
            const [hours = '0', minutes = '0'] = (time ?? '').split(':');
            return (Number(hours) * 60) + Number(minutes);
        };

        const hasAnyAvailableSlot = (items) => {
            if (!items.length) {
                return true;
            }

            const events = [];

            items.forEach((item) => {
                const start = Math.max(timeToMinutes(item.hora_inicio), JORNADA_INICIO);
                const end = Math.min(timeToMinutes(item.hora_fin), JORNADA_FIN);

                if (end <= start) {
                    return;
                }

                events.push({ minute: start, delta: 1 });
                events.push({ minute: end, delta: -1 });
            });

            if (!events.length) {
                return true;
            }

            events.sort((a, b) => {
                if (a.minute !== b.minute) {
                    return a.minute - b.minute;
                }

                return a.delta - b.delta;
            });

            let activeReservations = 0;
            let previousMinute = JORNADA_INICIO;

            for (const event of events) {
                const currentMinute = Math.max(Math.min(event.minute, JORNADA_FIN), JORNADA_INICIO);

                if (currentMinute > previousMinute && activeReservations < TOTAL_CUBICULOS) {
                    return true;
                }

                activeReservations += event.delta;
                previousMinute = currentMinute;
            }

            return previousMinute < JORNADA_FIN && activeReservations < TOTAL_CUBICULOS;
        };

        const hideAlert = () => {
            alerta.textContent = '';
            alerta.classList.add('d-none');
        };

        const showAlert = (message) => {
            alerta.textContent = message;
            alerta.classList.remove('d-none');
        };

        const renderDayDetail = (items) => {
            if (!detalleDiaContainer) {
                return;
            }

            if (!items.length) {
                detalleDiaContainer.innerHTML = '<div class="col-12"><div class="alert alert-light border mb-0">No hay registros para este día.</div></div>';
                return;
            }

            const sortedItems = [...items].sort((a, b) => {
                const horaInicio = (a.hora_inicio ?? '').localeCompare(b.hora_inicio ?? '');
                if (horaInicio !== 0) {
                    return horaInicio;
                }

                return Number(a.cubiculo_numero) - Number(b.cubiculo_numero);
            });

            const rows = sortedItems.map((item) => {
                const inicio = (item.hora_inicio ?? '').slice(0, 5);
                const fin = (item.hora_fin ?? '').slice(0, 5);
                const estrategia = item.estrategia ?? '—';
                const consultorio = item.consultorio_numero ?? filtroConsultorio?.value ?? '—';
                const cubiculo = item.cubiculo_numero ?? '—';
                const usuario = item.usuario_atendido_nombre ?? '—';

                return `
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <p class="mb-1"><strong>${inicio} - ${fin}</strong></p>
                            <p class="mb-1">Consultorio ${consultorio} · Cubículo ${cubiculo}</p>
                            <p class="mb-1">${estrategia}</p>
                            <p class="mb-0 text-muted small">Usuario: ${usuario}</p>
                        </div>
                    </div>
                `;
            }).join('');

            detalleDiaContainer.innerHTML = rows;
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

            if (mode === 'semanal' && !diaSemanaSelect?.value && formFecha?.value) {
                const selectedDate = new Date(`${formFecha.value}T00:00:00`);
                const isoDay = selectedDate.getDay() === 0 ? 7 : selectedDate.getDay();
                if (isoDay <= 6 && diaSemanaSelect) {
                    diaSemanaSelect.value = String(isoDay);
                }
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
                const isAvailable = hasAnyAvailableSlot(items);
                const dayStyle = isAvailable
                    ? 'background-color: #7fae9d; color: #0f2920; border-color: #6a9787;'
                    : 'background-color: #9b6b87; color: #ffffff; border-color: #855774;';
                cells.push(`
                    <button type="button" class="btn btn-sm border rounded text-start p-2 h-100" style="${dayStyle}${isSelected ? ' box-shadow: inset 0 0 0 2px #212529;' : ''}" data-calendar-day="${iso}">
                        <div class="fw-semibold">${day}</div>
                        <div class="small">${isAvailable ? 'Disponible' : 'Sin disponibilidad'}</div>
                        <div class="small opacity-75">${occupiedCubicles} cubículos ocupados</div>
                    </button>
                `);
            }

            calendarioContainer.innerHTML = `
                <div class="d-grid gap-2" style="grid-template-columns: repeat(7, minmax(0,1fr));">
                    ${weekDayLabels.map((label) => `<div class="text-center text-muted small fw-semibold">${label}</div>`).join('')}
                    ${cells.join('')}
                </div>
                <div class="d-flex flex-wrap gap-3 mt-3 small">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rounded" style="width: 1rem; height: 1rem; background-color: #7fae9d;"></span>
                        <span>Disponible</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="rounded" style="width: 1rem; height: 1rem; background-color: #9b6b87;"></span>
                        <span>Sin disponibilidad</span>
                    </div>
                </div>
            `;
        };

        const renderBitacoraGrid = (items) => {
            if (!bitacoraContainer) {
                return;
            }

            const bounds = computeBitacoraBounds();
            if (!bounds) {
                return;
            }

            const startDate = new Date(`${bounds.startISO}T00:00:00`);
            const endDate = new Date(`${bounds.endISO}T00:00:00`);
            const rangeDates = [];
            const cursor = new Date(startDate);

            while (cursor <= endDate) {
                rangeDates.push(new Date(cursor));
                cursor.setDate(cursor.getDate() + 1);
            }

            const dateHeaders = rangeDates.map((date) => dateISO(date));
            const agruparPorFecha = (sourceItems) => sourceItems.reduce((carry, item) => {
                const key = item.fecha;
                if (!carry[key]) {
                    carry[key] = [];
                }
                carry[key].push(item);
                return carry;
            }, {});
            const mostradosPorFecha = agruparPorFecha(items ?? []);
            const totalRegistros = (items ?? []).length;

            const ordenarRegistros = (sourceItems) => [...sourceItems].sort((a, b) => {
                const horaInicio = (a.hora_inicio ?? '').localeCompare(b.hora_inicio ?? '');
                if (horaInicio !== 0) {
                    return horaInicio;
                }

                return Number(a.cubiculo_numero) - Number(b.cubiculo_numero);
            });

            const renderSection = (titulo, registros, badgeClass) => {
                if (!registros.length) {
                    return `
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">${titulo}</h6>
                                <span class="badge ${badgeClass}">0 registros</span>
                            </div>
                            <p class="text-muted mb-0 small">Sin registros para este día.</p>
                        </div>
                    `;
                }

                const rows = registros.map((item) => {
                    const horaInicio = (item.hora_inicio ?? '').slice(0, 5);
                    const horaFin = (item.hora_fin ?? '').slice(0, 5);
                    const consultorio = item.consultorio_numero ?? '—';
                    const cubiculo = item.cubiculo_numero ?? '—';
                    const estrategia = item.estrategia ?? '—';
                    const estratega = item.estratega_nombre ?? '—';
                    const usuario = item.usuario_atendido_nombre ?? '—';

                    return `
                        <tr>
                            <td class="small text-nowrap">${horaInicio} - ${horaFin}</td>
                            <td class="small text-nowrap">Consultorio ${consultorio} · Cubículo ${cubiculo}</td>
                            <td class="small">${estrategia}</td>
                            <td class="small">${estratega}</td>
                            <td class="small">${usuario}</td>
                        </tr>
                    `;
                }).join('');

                return `
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">${titulo}</h6>
                            <span class="badge ${badgeClass}">${registros.length} registro(s)</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Horario</th>
                                        <th>Consultorio / Cubículo</th>
                                        <th>Estrategia</th>
                                        <th>Estratega</th>
                                        <th>Usuario</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>
                    </div>
                `;
            };

            const dayBlocks = dateHeaders.map((fecha) => {
                const mostrados = ordenarRegistros(mostradosPorFecha[fecha] ?? []);

                return `
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-3">${fecha}</div>
                        ${renderSection('Registros encontrados', mostrados, 'text-bg-primary')}
                    </div>
                `;
            }).join('');

            const labelPeriodo = bounds.startISO === bounds.endISO
                ? `la fecha ${bounds.startISO}`
                : `el periodo ${bounds.startISO} al ${bounds.endISO}`;

            bitacoraContainer.innerHTML = `
                <div class="alert alert-light border d-flex justify-content-between align-items-center" role="status">
                    <span>Total de registros encontrados en ${labelPeriodo}:</span>
                    <strong>${totalRegistros}</strong>
                </div>
                <div class="row row-cols-1 g-3">${dayBlocks}</div>
            `;
        };

        const refreshCalendar = async () => {
            if (!filtroFecha?.value || !filtroConsultorio?.value) {
                return;
            }

            try {
                const monthValue = filtroFecha.value.slice(0, 7);
                const bounds = monthBounds(monthValue);
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

                const selectedDate = filtroFecha.value?.startsWith(monthValue)
                    ? filtroFecha.value
                    : bounds.startISO;
                filtroFecha.value = selectedDate;
                diaSeleccionadoLabel.textContent = selectedDate;

                renderMonthCalendar(monthValue, groupedByDate);
                renderDayDetail(groupedByDate[selectedDate] ?? []);
            } catch (error) {
                console.error(error);
            }
        };

        const computeBitacoraBounds = () => {
            if (!bitacoraFechaBase?.value) {
                return null;
            }

            const base = new Date(`${bitacoraFechaBase.value}T00:00:00`);
            if (Number.isNaN(base.getTime())) {
                return null;
            }

            const mode = bitacoraModo?.value === 'mes' ? 'mes' : 'semana';
            const startDate = new Date(base);
            const endDate = new Date(base);

            if (mode === 'mes') {
                startDate.setDate(1);
                endDate.setMonth(endDate.getMonth() + 1, 0);
            } else {
                const day = startDate.getDay();
                const diffToMonday = day === 0 ? -6 : 1 - day;
                startDate.setDate(startDate.getDate() + diffToMonday);
                endDate.setDate(startDate.getDate() + 6);
            }

            return {
                mode,
                startISO: dateISO(startDate),
                endISO: dateISO(endDate),
            };
        };

        const refreshBitacora = async () => {
            const bounds = computeBitacoraBounds();
            if (!bounds) {
                return;
            }

            try {
                const params = new URLSearchParams({
                    fecha_inicio: bounds.startISO,
                    fecha_fin: bounds.endISO,
                });
                const data = await fetchAvailability(params);
                if (!data) {
                    return;
                }

                renderBitacoraGrid(data.reservas ?? []);
            } catch (error) {
                console.error(error);
            }
        };

        const hasOverlap = (items, start, end, cubiculo) => {
            const startMinutes = timeToMinutes(start);
            const endMinutes = timeToMinutes(end);

            return items.find((item) => {
                if (Number(item.cubiculo_numero) !== Number(cubiculo)) {
                    return false;
                }

                const itemStartMinutes = timeToMinutes(item.hora_inicio);
                const itemEndMinutes = timeToMinutes(item.hora_fin);

                return itemStartMinutes < endMinutes && itemEndMinutes > startMinutes;
            });
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
            if (bitacoraFechaBase) {
                bitacoraFechaBase.value = filtroFecha.value;
            }
            refreshCalendar();
            refreshBitacora();
        });

        filtroFecha?.addEventListener('change', () => {
            if (bitacoraFechaBase) {
                bitacoraFechaBase.value = filtroFecha.value;
            }
            refreshCalendar();
            refreshBitacora();
        });
        filtroConsultorio?.addEventListener('change', () => {
            refreshCalendar();
        });
        bitacoraAplicarFiltro?.addEventListener('click', refreshBitacora);
        bitacoraFechaBase?.addEventListener('change', refreshBitacora);
        bitacoraModo?.addEventListener('change', refreshBitacora);

        refreshCalendar();
        refreshBitacora();
        setInterval(refreshCalendar, 30000);
        setInterval(refreshBitacora, 30000);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initConsultoriosPage, { once: true });
    } else {
        initConsultoriosPage();
    }
</script>
@endpush
