<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
        <li class="breadcrumb-item"><a href="{{ route('consultorios.index') }}">Cubículos</a></li>
        <li class="breadcrumb-item active" aria-current="page">Gestionar reserva</li>
    @endsection

    @php
        // Never restore the acknowledgement or an obsolete revision after a validation error.
        $previous = static fn (string $key, string $default): string => is_string(old($key)) ? old($key) : $default;
        $cancel = $previous('accion', 'reprogramar') === 'cancelar';
        $following = $reserva->serie_uuid !== null && $previous('alcance', 'esta') === 'siguientes';
        $baseDate = $reserva->fecha->toDateString();
    @endphp
    <h4>Gestionar reserva de cubículo #{{ $reserva->id }}</h4>
    <p>Solo se pueden cambiar reservas que aún no han comenzado, según la hora de Ciudad de México. Las anteriores quedan intactas.</p>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>No se aplicaron cambios.</strong>
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form class="card" id="gestionar-serie" data-base-id="{{ $reserva->id }}" data-base-date="{{ $baseDate }}"
        method="POST" action="{{ route('consultorios.serie.apply', $reserva) }}">
        @csrf
        <input type="hidden" name="revision" value="{{ $revision }}">
        <div class="card-body">
            <fieldset class="mb-3">
                <legend class="h6">1. ¿Qué quieres hacer?</legend>
                <label class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="accion" value="reprogramar" @checked(! $cancel)>
                    Cambiar horario o cubículo
                </label>
                <label class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="accion" value="cancelar" @checked($cancel)>
                    Cancelar la reserva del espacio
                </label>
            </fieldset>
            <fieldset class="mb-3">
                <legend class="h6">2. Fechas a las que se aplicará</legend>
                <label class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="alcance" value="esta" @checked(! $following)>
                    Solo esta fecha ({{ $reserva->fecha->format('d/m/Y') }})
                </label>
                @if ($reserva->serie_uuid !== null)
                    <label class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="alcance" value="siguientes" @checked($following)>
                        Esta fecha y las siguientes de la misma serie
                    </label>
                @else
                    <p class="form-text mb-0">Esta reserva no tiene una serie registrada. Solo se puede gestionar de forma individual.</p>
                @endif
            </fieldset>
            <fieldset id="nuevo-horario" class="mb-3" @if($cancel) hidden disabled @endif>
                <legend class="h6">3. Nuevo horario</legend>
                <div class="row g-3">
                    <div class="col-sm-6 col-md-3">
                        <label for="serie-fecha" class="form-label">Nueva fecha de la reserva elegida</label>
                        <input type="date" class="form-control" id="serie-fecha" name="fecha" required
                            min="{{ now('America/Mexico_City')->toDateString() }}" value="{{ $previous('fecha', $baseDate) }}">
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label for="serie-inicio" class="form-label">Desde</label>
                        <input id="serie-inicio" type="time" name="hora_inicio" class="form-control" min="07:00" max="22:00" required value="{{ $previous('hora_inicio', substr($reserva->hora_inicio, 0, 5)) }}">
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label for="serie-fin" class="form-label">Hasta</label>
                        <input id="serie-fin" type="time" name="hora_fin" class="form-control" min="07:00" max="22:00" required value="{{ $previous('hora_fin', substr($reserva->hora_fin, 0, 5)) }}">
                    </div>
                    <div class="col-sm-6 col-md-5">
                        <label for="serie-cubiculo" class="form-label">Cubículo</label>
                        <select id="serie-cubiculo" name="consultorio_numero" class="form-select" required>
                            <option value="">Selecciona un cubículo activo</option>
                            @foreach ($cubiculosActivos as $cubiculo)
                                <option value="{{ $cubiculo->numero }}" @selected($previous('consultorio_numero', (string) $reserva->consultorio_numero) === (string) $cubiculo->numero)>{{ $cubiculo->nombre }} · Núm. {{ $cubiculo->numero }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="form-text mt-2 mb-0">Si eliges varias fechas, todas se moverán el mismo número de días y tendrán el cubículo y horario indicados. Si alguna se empalma, no se guardará ninguna.</p>
            </fieldset>
            <h5 class="h6">Vista previa</h5>
            <div id="serie-total" class="mb-2" role="status"></div>
            <div class="table-responsive mb-3">
                <table class="table table-sm">
                    <thead><tr><th scope="col">Reserva</th><th scope="col">Actual</th><th scope="col">Después de confirmar</th></tr></thead>
                    <tbody>
                        @foreach ($ocurrencias as $item)
                            <tr data-slot="{{ $item->id }}" data-fecha="{{ $item->fecha->toDateString() }}" @if($item->id !== $reserva->id) hidden @endif>
                                <th scope="row">#{{ $item->id }}</th>
                                <td>{{ $item->fecha->format('d/m/Y') }} · {{ substr($item->hora_inicio, 0, 5) }}–{{ substr($item->hora_fin, 0, 5) }} · Cubículo núm. {{ $item->consultorio_numero }}</td>
                                <td data-resultado></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p>Cancelar una reserva libera el cubículo. <strong>No elimina el expediente, sus sesiones clínicas ni sus documentos.</strong></p>
            <div class="mb-3">
                <label for="serie-motivo" class="form-label">Motivo (sin datos personales)</label>
                <input id="serie-motivo" name="motivo" class="form-control" required maxlength="300" value="{{ $previous('motivo', '') }}">
            </div>
            <label class="form-check mb-3">
                <input type="checkbox" id="serie-confirmar" class="form-check-input" name="confirmar" value="1" required>
                <span id="serie-confirmacion">Revisé las fechas y confirmo la operación indicada.</span>
            </label>
            <noscript><div class="alert alert-warning">Activa JavaScript para revisar las fechas antes de confirmar.</div></noscript>
            <div class="d-flex gap-2">
                <button id="serie-aplicar" type="submit" class="btn btn-primary" disabled>Aplicar cambios</button>
                <a class="btn btn-outline-secondary" href="{{ route('consultorios.index') }}">Volver sin cambios</a>
                @if ($reserva->expediente_id)
                    <a class="btn btn-link" href="{{ route('expedientes.show', $reserva->expediente_id) }}#horarios-asignados">Ver horarios del expediente</a>
                @endif
            </div>
        </div>
    </form>
    @push('scripts')
        <script>
            (() => {
                const form = document.getElementById('gestionar-serie');
                if (!form) return;
                const date = form.elements.fecha;
                const start = form.elements.hora_inicio;
                const end = form.elements.hora_fin;
                const room = form.elements.consultorio_numero;
                const ack = form.elements.confirmar;
                const schedule = form.querySelector('#nuevo-horario');
                const apply = form.querySelector('#serie-aplicar');
                const rows = Array.from(form.querySelectorAll('[data-slot]'));
                const midnight = iso => Date.parse(iso + 'T00:00:00Z');
                const shortDate = iso => iso.split('-').reverse().join('/');
                const preview = () => {
                    const cancel = form.elements.accion.value === 'cancelar';
                    const following = form.elements.alcance.value === 'siguientes';
                    schedule.disabled = schedule.hidden = cancel;
                    const selected = rows.filter(row => following || row.dataset.slot === form.dataset.baseId);
                    const count = selected.length;
                    const offset = midnight(date.value) - midnight(form.dataset.baseDate);
                    const validSchedule = Number.isFinite(offset) && date.validity.valid
                        && start.validity.valid && end.validity.valid && start.value < end.value && Boolean(room.value);
                    let valid = cancel || validSchedule;
                    for (const row of rows) {
                        row.hidden = !following && row.dataset.slot !== form.dataset.baseId;
                        if (row.hidden) continue;
                        const result = row.querySelector('[data-resultado]');
                        if (cancel) {
                            result.textContent = 'Se liberará el cubículo';
                        } else if (!validSchedule) {
                            result.textContent = 'Completa la nueva fecha, horario y cubículo.';
                        } else {
                            const destination = new Date(midnight(row.dataset.fecha) + offset);
                            const sunday = destination.getUTCDay() === 0;
                            result.textContent = shortDate(destination.toISOString().slice(0, 10)) + ' · ' + start.value
                                + '–' + end.value + ' · ' + room.options[room.selectedIndex].text
                                + (sunday ? ' · No se permiten domingos' : '');
                            if (sunday) valid = false;
                        }
                    }
                    const units = count === 1 ? '1 reserva' : count + ' reservas';
                    form.querySelector('#serie-total').textContent = (cancel ? 'Para cancelar: ' : 'Para reprogramar: ') + units + '.';
                    form.querySelector('#serie-confirmacion').textContent = (cancel ? 'Confirmo la cancelación de ' : 'Confirmo el cambio de ') + units + ' de la vista previa.';
                    apply.textContent = (cancel ? 'Cancelar ' : 'Reprogramar ') + units;
                    apply.classList.toggle('btn-danger', cancel);
                    apply.classList.toggle('btn-primary', !cancel);
                    apply.disabled = !count || !valid || !ack.checked;
                };
                const onInput = (event) => {
                    if (event.target !== ack && event.target !== form.elements.motivo) ack.checked = false;
                    preview();
                };
                form.addEventListener('input', onInput);
                form.addEventListener('change', onInput);
                preview();
            })();
        </script>
    @endpush
</x-app-layout>
