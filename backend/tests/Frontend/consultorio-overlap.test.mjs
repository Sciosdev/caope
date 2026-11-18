import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const read = file => readFileSync(new URL('../../resources/views/' + file + '.blade.php', import.meta.url), 'utf8');
const shared = read('consultorios/partials/fechas-asignacion-script');
const controls = read('consultorios/partials/fechas-asignacion');
function helper(name) {
    const match = shared.match(new RegExp('const ' + name + ' = [\\s\\S]*?\\n    };'));
    assert.ok(match, 'Exercise actual helper: ' + name);
    return runInNewContext(match[0] + '\n' + name);
}
const expand = helper('expandDates');
const conflicts = helper('findConflicts');
const booking = (overrides = {}) => ({
    fecha: '2026-11-04', consultorio_numero: 5, cubiculo_numero: 12,
    hora_inicio: '10:00:00', hora_fin: '11:00:00', ...overrides,
});

for (const [screen, nested] of [['consultorios/index', false], ['expedientes/partials/form', true]]) {
    test(screen + ' uses the shared dates, conflict preview and correct field namespace', () => {
        assert.ok(read(screen).includes("@include('consultorios.partials.fechas-asignacion', ['nested' => " + nested + "])"));
        assert.ok(controls.includes("@include('consultorios.partials.fechas-asignacion-script')"));
    });
}

test('same physical room overlaps, contains or adjoins strictly by time, regardless of historical sub-room', () => {
    const check = (start, end, fields = {}) => conflicts([booking(fields)], ['2026-11-04'], '5', start, end).length;
    for (const [start, end] of [['10:30','11:30'],['09:30','10:30'],['10:00','11:00'],['09:00','12:00'],['10:15','10:45']]) {
        assert.equal(check(start, end), 1);
    }
    assert.equal(check('09:00', '10:00'), 0);
    assert.equal(check('11:00', '12:00'), 0);
    assert.equal(check('10:30', '11:30', {consultorio_numero: 7}), 0);
    assert.equal(check('10:30', '11:30', {fecha: '2026-11-05'}), 0);
    assert.equal(check('09:30', '10:30', {cubiculo_numero: 1}), 1);
});
test('a multi-week Monday/Wednesday preview includes each selected date and every distinct conflict', () => {
    const dates = expand('semanal', '', '2026-11-02', '2026-11-11', [1,3], 180).dates;
    assert.deepEqual([...dates], ['2026-11-02', '2026-11-04', '2026-11-09', '2026-11-11']);
    const bookings = ['2026-11-11', '2026-11-04', '2026-11-04', '2026-11-05', '2026-11-18'].map(fecha => booking({fecha}));
    assert.deepEqual([...conflicts(bookings, dates, 5, '10:00','11:00')], ['2026-11-04','2026-11-11']);
});
test('preview accepts a single date and the inclusive limit; rejects Sunday, empty, reversed, and oversized schedules', () => {
    assert.deepEqual([...expand('unica', '2026-11-04', '', '', [], 180).dates], ['2026-11-04']);
    assert.ok(expand('unica', '2026-11-08', '', '', [], 180).error);
    for (const args of [['2026-11-02','2026-11-06',[],180],['2026-11-05','2026-11-04',[3],180],['2026-11-02','2026-11-03',[3],180],['2026-11-02','2040-01-01',[1],180]]) {
        assert.ok(expand('semanal', '', ...args).error);
    }
    assert.equal(expand('semanal', '', '2026-11-02', '2026-11-11', [1,3], 4).dates.length, 4);
});
test('failed HTTP responses cannot claim availability and stale requests are cancelled', () => {
    assert.match(shared, /if \(!response\.ok\) throw/);
    assert.match(shared, /catch \(error\) \{[\s\S]*?No fue posible verificar la disponibilidad/);
    assert.match(shared, /if \(id !== requestId\) return/);
    assert.ok(shared.includes('currentRequest?.abort()'));
});
