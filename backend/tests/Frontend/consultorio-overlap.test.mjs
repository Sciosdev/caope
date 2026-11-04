import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const consultorios = readFileSync(new URL('../../resources/views/consultorios/index.blade.php', import.meta.url), 'utf8');
const expediente = readFileSync(new URL('../../resources/views/expedientes/partials/form.blade.php', import.meta.url), 'utf8');

function declaration(source, name) {
    const match = source.match(new RegExp(`const ${name} = [\\s\\S]*?\\n\\s*};`));
    assert.ok(match, `Exercise the actual ${name} helper shipped in the Blade view.`);
    return match[0];
}

const hasOverlap = runInNewContext([
    declaration(consultorios, 'timeToMinutes'),
    declaration(consultorios, 'toIsoWeekday'),
    declaration(consultorios, 'hasOverlap'),
    'hasOverlap;',
].join('\n'));
const findOverlap = runInNewContext([
    declaration(expediente, 'toMinutes'),
    declaration(expediente, 'findOverlap'),
    'findOverlap;',
].join('\n'));

const reservation = (overrides = {}) => ({
    fecha: '2026-11-04',
    consultorio_numero: 5,
    cubiculo_numero: 12,
    hora_inicio: '10:00:00',
    hora_fin: '11:00:00',
    ...overrides,
});

for (const [name, find] of [['bitácora', hasOverlap], ['expediente', findOverlap]]) {
    test(`${name}: another cubicle does not bypass the occupied consultorio`, () => {
        const occupied = reservation();
        assert.equal(find([occupied], '10:30', '11:30', 5, '2026-11-04'), occupied);
        assert.equal(find([reservation({ cubiculo_numero: 1 })], '09:30', '10:30', '5', '2026-11-04')?.cubiculo_numero, 1);
    });

    test(`${name}: a different consultorio or date does not block the selected space`, () => {
        assert.equal(find([reservation({ consultorio_numero: 6 })], '10:30', '11:30', 5, '2026-11-04'), undefined);
        assert.equal(find([reservation({ fecha: '2026-11-05' })], '10:30', '11:30', 5, '2026-11-04'), undefined);
    });

    test(`${name}: adjacent slots remain valid, overlaps and containment block`, () => {
        const occupied = reservation();
        assert.equal(find([occupied], '09:00', '10:00', 5, '2026-11-04'), undefined);
        assert.equal(find([occupied], '11:00', '12:00', 5, '2026-11-04'), undefined);
        assert.equal(find([occupied], '10:00', '11:00', 5, '2026-11-04'), occupied);
        assert.equal(find([occupied], '09:00', '12:00', 5, '2026-11-04'), occupied);
        assert.equal(find([occupied], '10:15', '10:45', 5, '2026-11-04'), occupied);
    });
}

test('weekly bitácora checks every selected weekday in the inclusive date range', () => {
    const laterWeek = reservation({ fecha: '2026-11-11', cubiculo_numero: 2 });
    assert.equal(hasOverlap([laterWeek], '10:30', '11:30', 5, '2026-11-04', '2026-11-18', 3), laterWeek);
    const endDate = reservation({ fecha: '2026-11-18' });
    assert.equal(hasOverlap([endDate], '10:30', '11:30', 5, '2026-11-04', '2026-11-18', '3'), endDate);
    assert.equal(hasOverlap([reservation({ fecha: '2026-11-05' })], '10:30', '11:30', 5, '2026-11-04', '2026-11-18', 3), undefined);
    assert.equal(hasOverlap([reservation({ fecha: '2026-11-25' })], '10:30', '11:30', 5, '2026-11-04', '2026-11-18', 3), undefined);
});

test('availability failures are explicitly unverified rather than silently available', () => {
    for (const source of [consultorios, expediente]) {
        const check = declaration(source, 'checkAvailability');
        assert.match(check, /if \(!response\.ok\) \{\s*(?:showAlert|showAssignmentAlert)\('No fue posible verificar la disponibilidad/);
        assert.match(check, /catch \(error\) \{[\s\S]*(?:showAlert|showAssignmentAlert)\('No fue posible verificar la disponibilidad/);
    }
});
