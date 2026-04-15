<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
        <li class="breadcrumb-item active" aria-current="page">Consultorios</li>
    @endsection

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Reserva de consultorios</h4>
    </div>
    @php
        $currentUser = auth()->user();
        $isAdmin = $currentUser?->hasRole('admin') ?? false;
        $isPapsAprobado = ($currentUser?->hasRole('paps') ?? false) && ! is_null($currentUser?->approved_at);
        $canManageBitacora = $isAdmin;
    @endphp

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

    @if($isAdmin)
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
                            <input type="date" name="fecha_inicio_repeticion" id="repeticion-fecha-inicio" class="form-control" value="{{ old('fecha_inicio_repeticion') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Hasta</label>
                            <input type="date" name="fecha_fin_repeticion" id="repeticion-fecha-fin" class="form-control" value="{{ old('fecha_fin_repeticion') }}">
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
            <div id="ocupacion-calendario" class="mb-0"></div>
        </div>
    </div>

    <div class="modal fade" id="ocupacion-dia-modal" tabindex="-1" aria-labelledby="ocupacion-dia-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1" id="ocupacion-dia-modal-label">Registros del día</h5>
                        <div id="ocupacion-dia-seleccionado" class="small text-muted"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="ocupacion-dia-detalle"></div>
                </div>
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
                <input type="date" class="form-control" id="bitacora-fecha-base" name="bitacora_inicio" value="{{ $bitacoraFechaSeleccionada }}" aria-label="Fecha base de bitácora">
                <select class="form-select" id="bitacora-modo" name="bitacora_modo" aria-label="Modo de vista de bitácora">
                    <option value="semana" @selected($bitacoraModo === 'semana')>Semana</option>
                    <option value="mes" @selected($bitacoraModo === 'mes')>Mes</option>
                </select>
                <button type="submit" class="btn btn-outline-secondary" id="bitacora-aplicar-filtro">Mostrar</button>
                <a href="{{ route('consultorios.export') }}" class="btn btn-outline-success">Descargar XLS</a>
            </form>
        </div>
        <div class="card-body border-bottom">
            <div id="bitacora-vista-dinamica" class="table-responsive"></div>
        </div>
        <div class="card-body table-responsive">
            @if($canManageBitacora)
                <form id="bitacora-bulk-delete-form" action="{{ route('consultorios.bulk-destroy') }}" method="POST" class="mb-3 d-flex align-items-center gap-2">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="fecha" value="{{ $fechaFiltro }}">
                    <input type="hidden" name="consultorio_numero" value="{{ $consultorioSeleccionado }}">
                    @if($cubiculoSeleccionado)
                        <input type="hidden" name="cubiculo_numero" value="{{ $cubiculoSeleccionado }}">
                    @endif
                    <input type="hidden" name="bitacora_inicio" value="{{ request('bitacora_inicio', $bitacoraFechaSeleccionada) }}">
                    <input type="hidden" name="bitacora_modo" value="{{ request('bitacora_modo', $bitacoraModo) }}">
                    <button type="submit" class="btn btn-sm btn-outline-danger" id="bitacora-bulk-delete-button" disabled aria-disabled="true">
                        Eliminar seleccionadas
                    </button>
                </form>
            @endif
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        @if($canManageBitacora)
                            <th style="width: 1%;"></th>
                        @endif
                        <th>Fecha</th>
                        <th>Horario</th>
                        <th>Cubículo</th>
                        <th>Estrategia</th>
                        <th>Estratega</th>
                        <th>Usuario (capturó)</th>
                        @if($canManageBitacora || $isPapsAprobado)<th>Acción realizada</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reservas as $reserva)
                        <tr>
                            @if($canManageBitacora)
                                <td>
                                    @if ($isAdmin)
                                        <input
                                            type="checkbox"
                                            class="form-check-input bitacora-select-item"
                                            data-reserva-id="{{ $reserva->id }}"
                                            name="reservas[]"
                                            value="{{ $reserva->id }}"
                                            form="bitacora-bulk-delete-form"
                                            aria-label="Seleccionar reserva {{ $reserva->id }}"
                                        >
                                    @endif
                                </td>
                            @endif
                            <td>{{ $reserva->fecha->format('Y-m-d') }}</td>
                            <td>{{ substr($reserva->hora_inicio, 0, 5) }} - {{ substr($reserva->hora_fin, 0, 5) }}</td>
                            <td>Consultorio {{ $reserva->consultorio_numero }} · Cubículo {{ $reserva->cubiculo_numero }}</td>
                            <td>
                                {{ $reserva->estrategia }}
                                @if ($reserva->origen_expediente)
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle ms-2">Desde expediente</span>
                                @endif
                            </td>
                            <td>{{ $reserva->estratega?->name ?? '—' }}</td>
                            <td>{{ $reserva->creadoPor?->name ?? '—' }}</td>
                            @if($canManageBitacora || $isPapsAprobado)
                            <td>
                                <span class="small text-muted d-block mb-2">
                                    {{ $reserva->origen_expediente ? 'Alta automática (asignación de cubículo desde expediente)' : 'Alta manual (asignación de cubículo)' }}
                                </span>
                                @if ($isAdmin)
                                    <div class="d-flex gap-2">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('consultorios.edit', $reserva) }}">Editar</a>
                                        <form action="{{ route('consultorios.destroy', $reserva) }}" method="POST" onsubmit="return confirm('¿Dar de baja reserva?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </div>
                                @elseif ($isPapsAprobado)
                                    <div class="d-flex gap-2">
                                        <form action="{{ route('consultorios.request-edit', $reserva) }}" method="POST" onsubmit="return confirm('Se enviará una solicitud al administrador para editar este registro.');">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-primary">Solicitar edición</button>
                                        </form>
                                        <form
                                            action="{{ route('consultorios.request-destroy', $reserva) }}"
                                            method="POST"
                                            onsubmit="return confirm('Se enviará una solicitud al administrador para dar de baja este registro.');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Solicitar baja</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ ($canManageBitacora || $isPapsAprobado) ? 8 : 6 }}" class="text-center text-muted">Sin registros.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $reservas->links('pagination::bootstrap-5') }}
        </div>
    </div>
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
        const dayDetailModalElement = document.getElementById('ocupacion-dia-modal');
        const bitacoraFechaBase = document.getElementById('bitacora-fecha-base');
        const bitacoraModo = document.getElementById('bitacora-modo');
        const bitacoraAplicarFiltro = document.getElementById('bitacora-aplicar-filtro');
        const bitacoraContainer = document.getElementById('bitacora-vista-dinamica');
        const getBitacoraSelectItems = () => Array.from(document.querySelectorAll('.bitacora-select-item'));
        const getBitacoraBulkDeleteButton = () => document.getElementById('bitacora-bulk-delete-button');
        const getBitacoraBulkDeleteForm = () => document.getElementById('bitacora-bulk-delete-form');
        const repeticionConfig = document.getElementById('repeticion-config');
        const modoRepeticionInputs = document.querySelectorAll('input[name="modo_repeticion"]');
        const repeticionFechaInicio = document.getElementById('repeticion-fecha-inicio');
        const repeticionFechaFin = document.getElementById('repeticion-fecha-fin');
        const diaSemanaSelect = document.getElementById('repeticion-dia-semana');
        const weekDayLabels = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        let groupedByDateCache = {};
        const dayDetailModal = dayDetailModalElement && window.bootstrap?.Modal
            ? new window.bootstrap.Modal(dayDetailModalElement)
            : null;
        const formatSelectedDateLabel = (fecha) => {
            if (!fecha || !diaSeleccionadoLabel) {
                return;
            }

            const selectedDate = new Date(`${fecha}T00:00:00`);
            const isValidDate = !Number.isNaN(selectedDate.getTime());
            const formattedDate = isValidDate
                ? selectedDate.toLocaleDateString('es-MX', {
                    weekday: 'long',
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric',
                })
                : fecha;

            diaSeleccionadoLabel.innerHTML = `
                <div class="fecha-label">Día seleccionado</div>
                <div class="fecha-valor text-capitalize">${formattedDate}</div>
            `;
        };

        if (diaSemanaSelect?.value) {
            diaSemanaSelect.dataset.userSelected = 'true';
        }

        const timeToMinutes = (time) => {
            const [hours = '0', minutes = '0'] = (time ?? '').split(':');
            return (Number(hours) * 60) + Number(minutes);
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

            const sortedItems = [...(items ?? [])].sort((a, b) => {
                const horaInicio = (a.hora_inicio ?? '').localeCompare(b.hora_inicio ?? '');
                if (horaInicio !== 0) {
                    return horaInicio;
                }

                return Number(a.cubiculo_numero) - Number(b.cubiculo_numero);
            });

            if (!sortedItems.length) {
                detalleDiaContainer.innerHTML = '<span class="text-muted">Sin reservas para este día.</span>';
                return;
            }

            const detailRows = sortedItems.map((item) => {
                const horaInicio = (item.hora_inicio ?? '').slice(0, 5);
                const horaFin = (item.hora_fin ?? '').slice(0, 5);
                const cubiculo = item.cubiculo_numero ?? '—';
                const estrategia = item.estrategia ?? '—';
                const estratega = item.estratega_nombre ?? '—';
                const usuario = item.usuario_atendido_nombre ?? '—';

                return `
                    <tr>
                        <td class="text-nowrap">${horaInicio} - ${horaFin}</td>
                        <td class="text-nowrap">Cubículo ${cubiculo}</td>
                        <td>${estrategia}</td>
                        <td>${estratega}</td>
                        <td>${usuario}</td>
                    </tr>
                `;
            }).join('');

            detalleDiaContainer.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold">Registros del día</span>
                    <span class="badge text-bg-primary">${sortedItems.length} registro(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Horario</th>
                                <th>Cubículo</th>
                                <th>Estrategia</th>
                                <th>Estratega</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>${detailRows}</tbody>
                    </table>
                </div>
            `;
        };

        const openDayDetailModal = (fecha, items) => {
            if (!fecha) {
                return;
            }

            formatSelectedDateLabel(fecha);
            renderDayDetail(items);
            dayDetailModal?.show();
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

        const toIsoWeekday = (dateValue) => {
            if (!dateValue) {
                return null;
            }

            const selectedDate = new Date(`${dateValue}T00:00:00`);
            if (Number.isNaN(selectedDate.getTime())) {
                return null;
            }

            const isoDay = selectedDate.getDay() === 0 ? 7 : selectedDate.getDay();
            return isoDay <= 6 ? isoDay : null;
        };

        const syncRepeatDayFromStartDate = () => {
            if (!diaSemanaSelect || diaSemanaSelect.dataset.userSelected === 'true') {
                return;
            }

            const isoDay = toIsoWeekday(repeticionFechaInicio?.value);
            if (isoDay) {
                diaSemanaSelect.value = String(isoDay);
            }
        };

        const syncRepeatStartFromSelectedDate = () => {
            if (!repeticionFechaInicio || !formFecha?.value || repeticionFechaInicio.dataset.userSelected === 'true') {
                return;
            }

            repeticionFechaInicio.value = formFecha.value;
            syncRepeatDayFromStartDate();
        };

        const syncRepeatEndFromStartDate = () => {
            if (!repeticionFechaFin || repeticionFechaFin.dataset.userSelected === 'true' || !repeticionFechaInicio?.value) {
                return;
            }

            const endDate = new Date(`${repeticionFechaInicio.value}T00:00:00`);
            if (Number.isNaN(endDate.getTime())) {
                return;
            }

            endDate.setMonth(endDate.getMonth() + 1);
            repeticionFechaFin.value = dateISO(endDate);
        };

        const toggleRepeatConfig = () => {
            const mode = document.querySelector('input[name="modo_repeticion"]:checked')?.value ?? 'unica';
            repeticionConfig?.classList.toggle('d-none', mode !== 'semanal');
            if (formFecha) {
                formFecha.required = mode !== 'semanal';
            }

            if (mode === 'semanal') {
                syncRepeatStartFromSelectedDate();
                syncRepeatEndFromStartDate();
                syncRepeatDayFromStartDate();
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

        const renderYearCalendars = (yearValue, groupedByDate) => {
            if (!calendarioContainer) {
                return;
            }

            const currentMonthIndex = new Date().getMonth();
            const monthCards = [currentMonthIndex].map((monthIndex) => {
                const monthValue = `${yearValue}-${String(monthIndex + 1).padStart(2, '0')}`;
                const { start, end } = monthBounds(monthValue);
                const startOffset = (start.getDay() + 6) % 7;
                const calendarStart = new Date(start);
                calendarStart.setDate(start.getDate() - startOffset);

                const rows = [];
                const cursor = new Date(calendarStart);

                while (cursor <= end || cursor.getDay() !== 1) {
                    const weekCells = [];
                    for (let i = 0; i < 7; i += 1) {
                        const current = new Date(cursor);
                        const iso = dateISO(current);
                        const items = groupedByDate[iso] ?? [];
                        const isSelected = iso === filtroFecha.value;
                        const isCurrentMonth = current.getMonth() === start.getMonth();
                        const dayOfWeek = current.getDay();
                        const isBusinessDay = dayOfWeek >= 1 && dayOfWeek <= 5;
                        const dayClass = isBusinessDay ? 'bg-success-subtle' : 'bg-danger-subtle';
                        const mutedClass = isCurrentMonth ? '' : 'text-muted opacity-50';
                        const selectionStyle = isSelected ? ' style="box-shadow: inset 0 0 0 2px #212529;"' : '';
                        const reservationsLabel = items.length ? `${items.length} reserva(s)` : 'Sin reservas';
                        const recordsLabel = `${items.length} registro${items.length === 1 ? '' : 's'}`;
                        weekCells.push(`
                            <td class="${dayClass} align-top p-0">
                                <button type="button" class="btn btn-sm w-100 h-100 text-start rounded-0 border-0 p-1 ${mutedClass}" style="min-height: 74px;"${selectionStyle} data-calendar-day="${iso}">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <span class="fw-semibold">${current.getDate()}</span>
                                        <span class="small text-nowrap">${recordsLabel}</span>
                                    </div>
                                    <div class="small text-truncate">${reservationsLabel}</div>
                                </button>
                            </td>
                        `);
                        cursor.setDate(cursor.getDate() + 1);
                    }

                    rows.push(`<tr>${weekCells.join('')}</tr>`);
                }

                const monthTitle = start.toLocaleDateString('es-MX', {
                    month: 'long',
                    year: 'numeric',
                });

                return `
                    <div class="col-12">
                        <div class="border rounded p-2 h-100">
                            <h6 class="mb-2 text-capitalize">Mes: ${monthTitle}</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0" style="table-layout: fixed;">
                                    <thead class="table-light">
                                        <tr>
                                            ${weekDayLabels.map((label) => `<th class="text-center small fw-semibold">${label}</th>`).join('')}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${rows.join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
            });

            calendarioContainer.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Calendarios de ${yearValue}</h6>
                    <small class="text-muted">Mostrando únicamente el mes en curso. Haz clic en cualquier día para seleccionarlo.</small>
                </div>
                <div class="row g-3">
                    ${monthCards.join('')}
                </div>
                <div class="d-flex flex-wrap gap-3 mt-3 small">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rounded border bg-success-subtle" style="width: 1rem; height: 1rem;"></span>
                        <span>Día hábil</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="rounded border bg-danger-subtle" style="width: 1rem; height: 1rem;"></span>
                        <span>Día no hábil</span>
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
                if (!mostrados.length) {
                    return '';
                }

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
                ${dayBlocks.trim()
                    ? `<div class="row row-cols-1 g-3">${dayBlocks}</div>`
                    : '<p class="text-muted mb-0 small">Sin registros para el periodo seleccionado.</p>'}
            `;
        };

        const refreshCalendar = async () => {
            if (!filtroFecha?.value) {
                return;
            }

            try {
                const yearValue = filtroFecha.value.slice(0, 4);
                const selectedDate = filtroFecha.value?.startsWith(`${yearValue}-`)
                    ? filtroFecha.value
                    : `${yearValue}-01-01`;
                filtroFecha.value = selectedDate;
                formatSelectedDateLabel(selectedDate);
                renderYearCalendars(yearValue, {});
                renderDayDetail([]);
                const params = new URLSearchParams({
                    fecha_inicio: `${yearValue}-01-01`,
                    fecha_fin: `${yearValue}-12-31`,
                });

                if (filtroConsultorio?.value) {
                    params.set('consultorio_numero', filtroConsultorio.value);
                }
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

                groupedByDateCache = groupedByDate;

                renderYearCalendars(yearValue, groupedByDate);
                renderDayDetail(groupedByDate[selectedDate] ?? []);
            } catch (error) {
                console.error(error);
                showAlert('No fue posible cargar las reservas del calendario. Mostrando vista base sin registros.');
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
                renderBitacoraGrid([]);
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
                showAlert('No fue posible cargar la bitácora dinámica. Se muestra una vista vacía.');
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
            formFecha.addEventListener('change', () => {
                checkAvailability();
                if (document.querySelector('input[name="modo_repeticion"]:checked')?.value === 'semanal') {
                    syncRepeatStartFromSelectedDate();
                    syncRepeatEndFromStartDate();
                }
            });
            formHoraInicio.addEventListener('change', checkAvailability);
            formHoraFin.addEventListener('change', checkAvailability);
        }
        modoRepeticionInputs.forEach((input) => {
            input.addEventListener('change', toggleRepeatConfig);
        });
        repeticionFechaInicio?.addEventListener('change', () => {
            repeticionFechaInicio.dataset.userSelected = repeticionFechaInicio.value ? 'true' : 'false';
            syncRepeatDayFromStartDate();
            syncRepeatEndFromStartDate();
        });
        repeticionFechaFin?.addEventListener('change', () => {
            repeticionFechaFin.dataset.userSelected = repeticionFechaFin.value ? 'true' : 'false';
        });
        diaSemanaSelect?.addEventListener('change', () => {
            diaSemanaSelect.dataset.userSelected = diaSemanaSelect.value ? 'true' : 'false';
        });
        toggleRepeatConfig();
        calendarioContainer?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-calendar-day]');
            if (!button) {
                return;
            }

            filtroFecha.value = button.dataset.calendarDay;
            if (bitacoraFechaBase) {
                bitacoraFechaBase.value = filtroFecha.value;
            }
            openDayDetailModal(filtroFecha.value, groupedByDateCache[filtroFecha.value] ?? []);
            refreshCalendar();
            refreshBitacora();
        });

        filtroFecha?.addEventListener('change', () => {
            if (bitacoraFechaBase) {
                bitacoraFechaBase.value = filtroFecha.value;
            }
            openDayDetailModal(filtroFecha.value, groupedByDateCache[filtroFecha.value] ?? []);
            refreshCalendar();
            refreshBitacora();
        });
        filtroConsultorio?.addEventListener('change', () => {
            refreshCalendar();
        });
        bitacoraAplicarFiltro?.addEventListener('click', refreshBitacora);
        bitacoraFechaBase?.addEventListener('change', refreshBitacora);
        bitacoraModo?.addEventListener('change', refreshBitacora);

        const updateBitacoraSelectionState = () => {
            const bitacoraBulkDeleteButton = getBitacoraBulkDeleteButton();
            if (!bitacoraBulkDeleteButton) {
                return;
            }

            const bitacoraSelectItems = getBitacoraSelectItems();
            const selectedCount = bitacoraSelectItems.filter((item) => item.checked).length;
            const isDisabled = selectedCount === 0;
            bitacoraBulkDeleteButton.disabled = isDisabled;
            bitacoraBulkDeleteButton.setAttribute('aria-disabled', isDisabled ? 'true' : 'false');
        };

        const handleBitacoraItemSelection = (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) {
                return;
            }

            if (!target.classList.contains('bitacora-select-item')) {
                return;
            }

            updateBitacoraSelectionState();
        };

        document.addEventListener('change', handleBitacoraItemSelection);
        document.addEventListener('input', handleBitacoraItemSelection);

        const submitBitacoraBulkDelete = (event) => {
            const bitacoraBulkDeleteForm = event.currentTarget;
            if (!(bitacoraBulkDeleteForm instanceof HTMLFormElement)) {
                return;
            }

            const bitacoraSelectItems = getBitacoraSelectItems();
            const selectedItems = bitacoraSelectItems.filter((item) => item.checked);
            const selectedCount = selectedItems.length;

            if (!selectedCount) {
                event.preventDefault();
                return;
            }

            if (!window.confirm(`¿Eliminar ${selectedCount} registro${selectedCount === 1 ? '' : 's'} seleccionado${selectedCount === 1 ? '' : 's'}?`)) {
                event.preventDefault();
                return;
            }
        };

        getBitacoraBulkDeleteForm()?.addEventListener('submit', submitBitacoraBulkDelete);

        updateBitacoraSelectionState();

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
</x-app-layout>
