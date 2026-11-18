<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
        <li class="breadcrumb-item active" aria-current="page">Cubículos</li>
    @endsection

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Reserva de cubículos</h4>
    </div>
    @php
        $currentUser = auth()->user();
        $isAdmin = $currentUser?->hasRole('admin') ?? false;
        $isPapsAprobado = ($currentUser?->hasRole('paps') ?? false) && ! is_null($currentUser?->approved_at);
        $canManageBitacora = $isAdmin;
        $canViewBitacora = $isAdmin || ! ($currentUser?->hasAnyRole(['coordinador', 'docente', 'alumno']) ?? false);
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
                @include('consultorios.partials.fechas-asignacion', ['nested' => false])
                <div class="col-md-2">
                    <label class="form-label">Inicio</label>
                    <input type="time" name="hora_inicio" class="form-control" id="asignacion-hora-inicio" min="07:00" max="22:00" value="{{ old('hora_inicio', '07:00') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Fin</label>
                    <input type="time" name="hora_fin" class="form-control" id="asignacion-hora-fin" min="07:00" max="22:00" value="{{ old('hora_fin', '08:00') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cubículo</label>
                    <select name="consultorio_numero" class="form-select" id="asignacion-consultorio" required>
                        @forelse ($consultoriosActivos as $cubiculo)
                            <option value="{{ $cubiculo->numero }}" @selected((int) old('consultorio_numero', (int) ($consultoriosActivos->first()?->numero ?? 0)) === (int) $cubiculo->numero)>
                                {{ trim((string) $cubiculo->nombre) !== '' ? $cubiculo->nombre : 'Cubículo sin nombre' }}
                            </option>
                        @empty
                            <option value="">Sin cubículos disponibles</option>
                        @endforelse
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
                    <label class="form-label">Facilitador</label>
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
                <input type="month" class="form-control" name="fecha" id="ocupacion-fecha" value="{{ \Illuminate\Support\Carbon::parse($fechaFiltro)->format('Y-m') }}">
                <select name="consultorio_numero" class="form-select" id="ocupacion-consultorio">
                    @forelse ($consultoriosActivos as $cubiculo)
                        <option value="{{ $cubiculo->numero }}" @selected($consultorioSeleccionado === (int) $cubiculo->numero)>
                            {{ trim((string) $cubiculo->nombre) !== '' ? $cubiculo->nombre : 'Cubículo sin nombre' }}
                        </option>
                    @empty
                        <option value="">Sin cubículos asignados</option>
                    @endforelse
                </select>
                <button class="btn btn-outline-secondary" type="submit" id="ocupacion-ver-btn">Ver mes</button>
            </form>
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

    @if($canViewBitacora)
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span>Bitácora de asignaciones (alta, baja y modificación)</span>
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="fecha" value="{{ $fechaFiltro }}">
                <input type="hidden" name="consultorio_numero" value="{{ $consultorioSeleccionado }}">
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
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Horario</th>
                        <th>Cubículo</th>
                        <th>Estrategia</th>
                        <th>Estratega</th>
                        <th>Facilitador</th>
                        @if($canManageBitacora || $isPapsAprobado)<th>Acción realizada</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reservas as $reserva)
                        <tr>
                            <td>{{ $reserva->fecha->format('Y-m-d') }}</td>
                            <td>{{ substr($reserva->hora_inicio, 0, 5) }} - {{ substr($reserva->hora_fin, 0, 5) }}</td>
                            <td>
                                {{ trim((string) ($reserva->cubiculo?->nombre ?? '')) !== '' ? $reserva->cubiculo->nombre : 'Cubículo sin catálogo' }}
                                · Núm. {{ $reserva->consultorio_numero }}
                                @if ($reserva->solicitud_pendiente)
                                    <span class="badge text-bg-warning">{{ $reserva->solicitud_pendiente === 'baja' ? 'Baja pendiente' : 'Edición pendiente' }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $reserva->estrategia }}
                                @if ($reserva->origen_expediente)
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle ms-2">Desde expediente</span>
                                @endif
                            </td>
                            <td>{{ $reserva->estratega?->name ?? '—' }}</td>
                            <td>{{ $reserva->usuarioAtendido?->name ?? '—' }}</td>
                            @if($canManageBitacora || $isPapsAprobado)
                            <td>
                                <span class="small text-muted d-block mb-2">
                                    {{ $reserva->origen_expediente ? 'Alta automática (asignación de cubículo desde expediente)' : 'Alta manual (asignación de cubículo)' }}
                                </span>
                                @if ($reserva->solicitud_pendiente)
                                    @if ($isAdmin)
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.consultorios.solicitudes.index') }}">Gestionar solicitudes</a>
                                    @else
                                        <span class="small text-muted">En revisión; el horario actual sigue vigente.</span>
                                    @endif
                                @elseif ($reserva->expediente?->trashed())
                                    <span class="small text-muted">Expediente archivado · solo lectura</span>
                                @elseif (($isAdmin || $reserva->serie_uuid) && ! $reserva->hasNotStarted())
                                    <span class="small text-muted">La reserva ya comenzó · solo lectura</span>
                                @elseif ($isAdmin)
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('consultorios.serie.edit', $reserva) }}">Reprogramar o cancelar reserva</a>
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('consultorios.edit', $reserva) }}">Editar datos de asignación</a>
                                    </div>
                                @elseif ($isPapsAprobado)
                                    <div class="d-flex gap-2">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('consultorios.edit', $reserva) }}">Solicitar edición</a>
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
                        <tr><td colspan="{{ ($canManageBitacora || $isPapsAprobado) ? 7 : 6 }}" class="text-center text-muted">Sin registros.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $reservas->links('pagination::bootstrap-5') }}
        </div>
    </div>
    @endif

@push('scripts')
<script>
    const initConsultoriosPage = () => {
        if (window.__consultoriosPageInitialized) {
            return;
        }
        window.__consultoriosPageInitialized = true;
        const availabilityEndpoint = @json(route('consultorios.availability'));
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
        const weekDayLabels = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        let groupedByDateCache = {};
        let selectedCalendarDay = null;
        let calendarRequest = 0;
        let bitacoraRequest = 0;
        const pendingBadge = (item) => {
            const label = item.solicitud_pendiente === 'baja' ? 'Baja pendiente'
                : item.solicitud_pendiente === 'edicion' ? 'Edición pendiente' : '';
            return label ? `<span class="badge text-bg-warning">${label}</span>` : '';
        };
        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        })[character]);
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
                <div class="fecha-valor text-capitalize">${escapeHtml(formattedDate)}</div>
            `;
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

                return Number(a.consultorio_numero) - Number(b.consultorio_numero);
            });

            if (!sortedItems.length) {
                detalleDiaContainer.innerHTML = '<span class="text-muted">Sin reservas para este día.</span>';
                return;
            }

            const detailRows = sortedItems.map((item) => {
                const horaInicio = escapeHtml((item.hora_inicio ?? '').slice(0, 5));
                const horaFin = escapeHtml((item.hora_fin ?? '').slice(0, 5));
                const cubiculoNumero = escapeHtml(item.consultorio_numero ?? '—');
                const cubiculoNombre = escapeHtml(item.cubiculo_nombre ?? 'Cubículo sin catálogo');
                const estrategia = escapeHtml(item.estrategia ?? '—');
                const estratega = escapeHtml(item.estratega_nombre ?? '—');
                const usuario = escapeHtml(item.usuario_atendido_nombre ?? '—');

                return `
                    <tr>
                        <td class="text-nowrap">${horaInicio} - ${horaFin} ${pendingBadge(item)}</td>
                        <td class="text-nowrap">${cubiculoNombre} · Núm. ${cubiculoNumero}</td>
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
                                <th>Facilitador</th>
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

        const fetchAvailability = async (params) => {
            const response = await fetch(`${availabilityEndpoint}?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('No se pudo consultar la ocupación.');
            }

            const data = await response.json();
            if (!Array.isArray(data?.reservas)) {
                throw new Error('Respuesta de ocupación incompleta.');
            }
            return data;
        };

        const renderMonthCalendar = (monthValue, groupedByDate) => {
            if (!calendarioContainer) {
                return;
            }

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
                    const isSelected = iso === selectedCalendarDay;
                    const isCurrentMonth = current.getMonth() === start.getMonth();
                    const dayOfWeek = current.getDay();
                    const isBusinessDay = dayOfWeek >= 1 && dayOfWeek <= 6;
                    const hasRecords = items.length > 0;
                    const dayClass = hasRecords
                        ? (isBusinessDay ? 'bg-success-subtle' : 'bg-danger-subtle')
                        : 'bg-light';
                    const mutedClass = isCurrentMonth ? '' : 'text-muted opacity-50';
                    const selectionStyle = isSelected ? 'box-shadow: inset 0 0 0 2px #212529;' : '';
                    const reservationsLabel = items.length ? `${items.length} reserva(s)` : 'Sin reservas';
                    const recordsLabel = `${items.length} registro${items.length === 1 ? '' : 's'}`;
                    weekCells.push(`
                        <td class="${dayClass} align-top p-0">
                            <button type="button" class="btn btn-sm w-100 h-100 text-start rounded-0 border-0 p-1 ${mutedClass}" style="min-height: 74px;${selectionStyle}" data-calendar-day="${iso}" ${!isCurrentMonth ? 'disabled' : ''}>
                                <div class="d-flex justify-content-between align-items-start">
                                    <span class="fw-semibold">${current.getDate()}</span>
                                    <span class="small text-nowrap">${isCurrentMonth ? recordsLabel : ''}</span>
                                </div>
                                <div class="small text-truncate">${isCurrentMonth ? reservationsLabel : ''}</div>
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

            calendarioContainer.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-capitalize">Calendario de ${escapeHtml(monthTitle)}</h6>
                    <small class="text-muted">Haz clic en cualquier día para ver su detalle.</small>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <div class="border rounded p-2 h-100">
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
                </div>
                <div class="d-flex flex-wrap gap-3 mt-3 small">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rounded border bg-light" style="width: 1rem; height: 1rem;"></span>
                        <span>Sin registros</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="rounded border bg-success-subtle" style="width: 1rem; height: 1rem;"></span>
                        <span>Día hábil con registros</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="rounded border bg-danger-subtle" style="width: 1rem; height: 1rem;"></span>
                        <span>Día no hábil con registros</span>
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

                return Number(a.consultorio_numero) - Number(b.consultorio_numero);
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
                    const horaInicio = escapeHtml((item.hora_inicio ?? '').slice(0, 5));
                    const horaFin = escapeHtml((item.hora_fin ?? '').slice(0, 5));
                    const cubiculoNumero = escapeHtml(item.consultorio_numero ?? '—');
                    const cubiculoNombre = escapeHtml(item.cubiculo_nombre ?? 'Cubículo sin catálogo');
                    const estrategia = escapeHtml(item.estrategia ?? '—');
                    const estratega = escapeHtml(item.estratega_nombre ?? '—');
                    const usuario = escapeHtml(item.usuario_atendido_nombre ?? '—');

                    return `
                        <tr>
                            <td class="small text-nowrap">${horaInicio} - ${horaFin} ${pendingBadge(item)}</td>
                            <td class="small text-nowrap">${cubiculoNombre} · Núm. ${cubiculoNumero}</td>
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
                                        <th>Cubículo</th>
                                        <th>Estrategia</th>
                                        <th>Estratega</th>
                                        <th>Facilitador</th>
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
                        <div class="fw-semibold mb-3">${escapeHtml(fecha)}</div>
                        ${renderSection('Registros encontrados', mostrados, 'text-bg-primary')}
                    </div>
                `;
            }).join('');

            const labelPeriodo = bounds.startISO === bounds.endISO
                ? `la fecha ${bounds.startISO}`
                : `el periodo ${bounds.startISO} al ${bounds.endISO}`;

            bitacoraContainer.innerHTML = `
                <div class="alert alert-light border d-flex justify-content-between align-items-center" role="status">
                    <span>Total de registros encontrados en ${escapeHtml(labelPeriodo)}:</span>
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

            const revision = ++calendarRequest;
            try {
                const monthValue = filtroFecha.value;
                const bounds = monthBounds(monthValue);
                if (!selectedCalendarDay || selectedCalendarDay < bounds.startISO || selectedCalendarDay > bounds.endISO) {
                    selectedCalendarDay = bounds.startISO;
                }
                formatSelectedDateLabel(selectedCalendarDay);
                calendarioContainer.textContent = 'Consultando ocupación…';
                if (detalleDiaContainer) detalleDiaContainer.textContent = 'Consultando ocupación…';
                const params = new URLSearchParams({
                    fecha_inicio: bounds.startISO,
                    fecha_fin: bounds.endISO,
                });

                if (filtroConsultorio?.value) {
                    params.set('consultorio_numero', filtroConsultorio.value);
                }
                const data = await fetchAvailability(params);
                if (revision !== calendarRequest) {
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

                renderMonthCalendar(monthValue, groupedByDate);
                renderDayDetail(groupedByDate[selectedCalendarDay] ?? []);
            } catch (error) {
                if (revision !== calendarRequest) return;
                groupedByDateCache = {};
                calendarioContainer.textContent = 'No se pudo consultar la ocupación. Actualiza la página para reintentar.';
                if (detalleDiaContainer) detalleDiaContainer.textContent = 'Ocupación no disponible.';
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
                endDate.setTime(startDate.getTime());
                endDate.setDate(endDate.getDate() + 6);
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

            const revision = ++bitacoraRequest;
            try {
                bitacoraContainer.textContent = 'Consultando la bitácora…';
                const params = new URLSearchParams({
                    fecha_inicio: bounds.startISO,
                    fecha_fin: bounds.endISO,
                });
                const data = await fetchAvailability(params);
                if (revision !== bitacoraRequest) {
                    return;
                }

                renderBitacoraGrid(data.reservas ?? []);
            } catch (error) {
                if (revision === bitacoraRequest && bitacoraContainer) {
                    bitacoraContainer.textContent = 'No se pudo consultar la bitácora para este periodo.';
                }
            }
        };

        calendarioContainer?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-calendar-day]');
            if (!button) {
                return;
            }

            selectedCalendarDay = button.dataset.calendarDay;
            if (bitacoraFechaBase) {
                bitacoraFechaBase.value = selectedCalendarDay;
            }
            openDayDetailModal(selectedCalendarDay, groupedByDateCache[selectedCalendarDay] ?? []);
            refreshCalendar();
            refreshBitacora();
        });

        filtroFecha?.addEventListener('change', () => {
            if (bitacoraFechaBase) {
                bitacoraFechaBase.value = `${filtroFecha.value}-01`;
            }
            selectedCalendarDay = `${filtroFecha.value}-01`;
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
</x-app-layout>
