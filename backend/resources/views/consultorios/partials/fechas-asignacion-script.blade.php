<script>
document.addEventListener('DOMContentLoaded', () => {
    const expandDates = (mode, base, first, last, weekdays, limit) => {
        if (mode !== 'semanal') {
            if (!base) return {error: 'Selecciona la fecha de la reserva.'};
            if (new Date(base + 'T00:00:00Z').getUTCDay() === 0) return {error: 'Solo se permiten reservas de lunes a sábado.'};
            return {dates: [base]};
        }
        if (!first || !last || first > last) return {error: 'Revisa las fechas de inicio y fin.'};
        if (!weekdays.length) return {error: 'Selecciona al menos un día de la semana.'};
        const dates = [];
        const cursor = new Date(first + 'T00:00:00Z');
        const end = new Date(last + 'T00:00:00Z');
        while (cursor <= end) {
            if (weekdays.includes(cursor.getUTCDay())) {
                dates.push(cursor.toISOString().slice(0, 10));
                if (dates.length > limit) return {error: 'Solo se pueden registrar hasta ' + limit + ' fechas por operación.'};
            }
            cursor.setUTCDate(cursor.getUTCDate() + 1);
        }
        return dates.length ? {dates} : {error: 'El periodo no contiene ninguno de los días seleccionados.'};
    };

    const roomNumber = room => room?.id === 'cubiculo_id' ? room.selectedOptions[0]?.dataset.numero ?? '' : room?.value ?? '';

    const findConflicts = (reservas, dates, room, start, end) => {
        const selected = new Set(dates);
        return [...new Set(reservas.filter(item => Number(item.consultorio_numero) === Number(room)
            && selected.has(String(item.fecha).slice(0, 10))
            && String(item.hora_inicio).slice(0, 5) < end && String(item.hora_fin).slice(0, 5) > start)
            .map(item => String(item.fecha).slice(0, 10)))].sort();
    };

    document.querySelectorAll('[data-reservation-dates]').forEach(root => {
        const el = attr => root.querySelector('[data-' + attr + ']');
        const base = el('date'), from = el('from'), until = el('until');
        const days = [...root.querySelectorAll('[data-weekday]')];
        const room = document.getElementById(root.dataset.room);
        const start = document.getElementById(root.dataset.inputPrefix + '-hora-inicio');
        const end = document.getElementById(root.dataset.inputPrefix + '-hora-fin');
        const format = date => date.split('-').reverse().join('/');
        let requestId = 0;
        let currentRequest = null;

        const update = async () => {
            const id = ++requestId;
            currentRequest?.abort();
            if (root.closest('fieldset[disabled]')) return;
            const weekly = root.querySelector('[data-mode]:checked').value === 'semanal';
            el('single').hidden = weekly;
            base.disabled = weekly;
            base.required = !weekly;
            el('weekly').hidden = !weekly;
            el('weekly').disabled = !weekly;
            until.min = from.value;
            const selected = days.filter(day => day.checked).map(day => Number(day.value));
            const {dates, error} = expandDates(weekly ? 'semanal' : 'unica', base.value, from.value, until.value, selected, Number(root.dataset.limit));
            (weekly ? until : base).setCustomValidity(error || '');
            el('summary').textContent = error || ('Fechas a registrar: ' + dates.length + '.');
            el('preview').hidden = !dates || !weekly;
            el('list').textContent = dates ? dates.map(format).join(', ') : '';
            const feedback = el('feedback');
            feedback.hidden = true;
            if (!dates || !room?.value || !start?.value || !end?.value) return;

            const showWarning = message => {
                if (id !== requestId) return;
                feedback.textContent = message;
                feedback.hidden = false;
            };
            if (start.value >= end.value || start.value < '07:00' || end.value > '22:00') {
                showWarning('Revisa el horario: la atención es de 07:00 a 22:00 y la hora final debe ser posterior.');
                return;
            }

            const numero = roomNumber(room);
            if (!numero) return;
            const params = new URLSearchParams({consultorio_numero: numero, fecha_inicio: dates[0], fecha_fin: dates[dates.length - 1]});
            try {
                currentRequest = new AbortController();
                const response = await fetch(root.dataset.availability + '?' + params, {headers: {Accept: 'application/json'}, signal: currentRequest.signal});
                if (!response.ok) throw new Error('availability');
                const result = await response.json();
                const conflicts = findConflicts(result.reservas, dates, numero, start.value, end.value);
                if (conflicts.length) showWarning('Ya hay una reserva en ese cubículo y horario el ' + conflicts.map(format).join(', ') + '. Ajusta la selección; no se guardarán fechas parcialmente.');
            } catch (error) {
                if (error.name !== 'AbortError') showWarning('No fue posible verificar la disponibilidad. El servidor volverá a comprobar los horarios al guardar.');
            }
        };
        root.addEventListener('change', update);
        [room, start, end].forEach(input => input?.addEventListener('change', update));
        if (room?.classList.contains('js-select2') && window.jQuery) window.jQuery(room).on('select2:select select2:clear', update);
        update();
    });
});
</script>
