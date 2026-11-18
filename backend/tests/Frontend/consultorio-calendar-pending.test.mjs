import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { setImmediate as settle } from 'node:timers/promises';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

// Exercise the entire shipped calendar script (including its real event listeners),
// with anonymous fixture reservations and no network, Blade rendering or UI framework.
const view = readFileSync(new URL('../../resources/views/consultorios/index.blade.php', import.meta.url), 'utf8');
const endpoint = "@json(route('consultorios.availability'))";
const script = [...view.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)]
    .map(match => match[1]).find(source => source.includes(endpoint)).replace(endpoint, '"/availability"');

function start(request) {
    const nodes = new Map();
    for (const [id, value = ''] of [
        ['ocupacion-fecha', '2026-11'], ['ocupacion-consultorio', '41'],
        ['ocupacion-calendario'], ['ocupacion-dia-detalle'], ['ocupacion-dia-seleccionado'],
        ['bitacora-modo', 'semana'], ['bitacora-fecha-base', '2026-11-01'], ['bitacora-vista-dinamica'],
    ]) {
        const listeners = new Map();
        nodes.set(id, {
            value, output: '',
            set textContent(value) { this.output = value; },
            set innerHTML(value) { this.output = value; },
            addEventListener(type, handler) { listeners.set(type, handler); },
            fire(type, detail = {}) { listeners.get(type)?.(detail); },
        });
    }
    const requests = [];
    runInNewContext(script, {
        window: {}, document: {
            readyState: 'complete', getElementById: id => nodes.get(id) ?? null,
            querySelector: () => null, querySelectorAll: () => [], addEventListener() {},
        },
        setInterval() {}, URLSearchParams,
        fetch(input) {
            const params = new URL(input, 'https://example.test').searchParams;
            requests.push(params);
            return request(params);
        },
    });
    return { node: id => nodes.get(id), requests };
}

const json = reservas => ({ ok: true, json: () => Promise.resolve({ reservas }) });
const slot = (fecha, pendiente) => ({
    fecha, hora_inicio: '09:00', hora_fin: '10:00', consultorio_numero: 41,
    cubiculo_nombre: 'Espacio ficticio', solicitud_pendiente: pendiente,
});

test('the week beginning in October still shows pending occupied slots on both sides of November 1', async () => {
    const slots = [slot('2026-10-27', 'edicion'), slot('2026-11-01', 'baja')];
    const { node, requests } = start(params => json(slots.filter(s =>
        s.fecha >= params.get('fecha_inicio') && s.fecha <= params.get('fecha_fin')
    )));
    await settle();
    assert.ok(requests.some(p => p.get('fecha_inicio') === '2026-10-26' && p.get('fecha_fin') === '2026-11-01'));
    const week = node('bitacora-vista-dinamica').output;
    for (const expected of ['2026-10-27', '2026-11-01', 'Edición pendiente', 'Baja pendiente']) {
        assert.ok(week.includes(expected), expected);
    }
    assert.match(node('ocupacion-dia-detalle').output, /Baja pendiente/);
});

for (const [name, fail] of [
    ['network unavailable', () => Promise.reject(new Error('simulated offline'))],
    ['HTTP error', () => ({ ok: false })],
    ['invalid success payload', () => ({ ok: true, json: () => Promise.resolve({}) })],
]) {
    test(`${name}: unknown occupation is not rendered as an empty week or an available day`, async () => {
        const { node } = start(fail);
        await settle();
        for (const id of ['ocupacion-calendario', 'ocupacion-dia-detalle', 'bitacora-vista-dinamica']) {
            const output = node(id).output;
            assert.match(output, /no (?:se pudo|disponible)/i, id);
            assert.doesNotMatch(output, /sin (?:reservas|registros)|\blibre\b/i, id);
        }
    });
}

test('a slow response for the previous month cannot overwrite a newer failed request', async () => {
    let resume;
    const slow = new Promise(resolve => { resume = resolve; });
    const { node } = start(params => params.get('fecha_inicio') === '2026-11-01'
        ? slow : Promise.resolve({ ok: false }));
    const month = node('ocupacion-fecha');
    month.value = '2026-12';
    month.fire('change');
    await settle();
    resume(json([slot('2026-11-01', 'edicion')]));
    await settle();
    assert.match(node('ocupacion-calendario').output, /no se pudo/i);
    assert.doesNotMatch(node('ocupacion-dia-detalle').output, /Edición pendiente/);
});
