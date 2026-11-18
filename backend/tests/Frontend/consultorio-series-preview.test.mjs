import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const html = readFileSync(new URL('../../resources/views/consultorios/serie.blade.php', import.meta.url), 'utf8');
const script = html.match(/<script>([\s\S]*?)<\/script>/)[1];

function preview() {
    const el = (value = '') => ({
        value, validity: {valid: true}, checked: false, disabled: false, hidden: false,
        textContent: '', classList: {toggle() {}},
    });
    const elements = {
        confirmar: el(), accion: el('reprogramar'), alcance: el('esta'), motivo: el(),
        fecha: el('2026-12-30'), hora_inicio: el('08:00'), hora_fin: el('09:00'),
        consultorio_numero: Object.assign(el('47'), {options: [{text: 'Prueba · Núm. 47'}], selectedIndex: 0}),
    };
    const current = el(), next = el();
    const rows = [
        {dataset: {slot: '120', fecha: '2026-12-30'}, querySelector: () => current},
        {dataset: {slot: '121', fecha: '2027-01-06'}, querySelector: () => next},
    ];
    const fields = new Map(['#nuevo-horario', '#serie-aplicar', '#serie-total', '#serie-confirmacion'].map(k => [k, el()]));
    const listeners = {};
    const form = {
        elements, dataset: {baseId: '120', baseDate: '2026-12-30'},
        querySelectorAll: () => rows, querySelector: s => fields.get(s),
        addEventListener: (name, fn) => { listeners[name] = fn; },
    };
    runInNewContext(script, {document: {getElementById: () => form}});
    return {
        elements, current, next, rows, fields,
        change(name, value) {
            const target = elements[name];
            if (name === 'confirmar') target.checked = value;
            else target.value = value;
            listeners.change({target});
        },
    };
}

test('series starts with just the selected slot; changing the date shifts all selected dates across years', () => {
    const ui = preview();
    assert.equal(ui.rows[1].hidden, true);
    assert.match(ui.fields.get('#serie-total').textContent, /1 reserva\./);
    assert.equal(ui.fields.get('#serie-aplicar').disabled, true);
    ui.change('alcance', 'siguientes');
    ui.change('fecha', '2027-01-01');
    assert.equal(ui.rows[1].hidden, false);
    assert.match(ui.current.textContent, /01\/01\/2027 · 08:00–09:00 · Prueba/);
    assert.match(ui.next.textContent, /08\/01\/2027/);
    ui.change('confirmar', true);
    assert.equal(ui.fields.get('#serie-aplicar').disabled, false);
    ui.change('hora_fin', '09:30');
    assert.equal(ui.elements.confirmar.checked, false);
    ui.change('fecha', '2027-01-03'); // Sunday.
    ui.change('confirmar', true);
    assert.match(ui.current.textContent, /No se permiten domingos/);
    assert.equal(ui.fields.get('#serie-aplicar').disabled, true);
});

test('cancellation names its exact count; changing scope or action invalidates the earlier acknowledgement', () => {
    const ui = preview();
    ui.change('alcance', 'siguientes');
    ui.change('confirmar', true);
    ui.change('accion', 'cancelar');
    assert.equal(ui.fields.get('#nuevo-horario').disabled, true);
    assert.equal(ui.fields.get('#serie-aplicar').disabled, true);
    assert.equal(ui.current.textContent, 'Se liberará el cubículo');
    assert.equal(ui.next.textContent, 'Se liberará el cubículo');
    assert.equal(ui.fields.get('#serie-aplicar').textContent, 'Cancelar 2 reservas');
    ui.change('confirmar', true);
    assert.equal(ui.fields.get('#serie-aplicar').disabled, false);
    ui.change('alcance', 'esta');
    assert.equal(ui.fields.get('#serie-aplicar').disabled, true);
    assert.equal(ui.rows[1].hidden, true);
    assert.match(ui.fields.get('#serie-confirmacion').textContent, /cancelación de 1 reserva de/);
});
