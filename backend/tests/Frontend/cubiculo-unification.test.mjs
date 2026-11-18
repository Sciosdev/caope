import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext, Script } from 'node:vm';

const sources = {
    expediente: readFileSync(new URL('../../resources/views/expedientes/partials/form.blade.php', import.meta.url), 'utf8'),
    shared: readFileSync(new URL('../../resources/views/consultorios/partials/fechas-asignacion-script.blade.php', import.meta.url), 'utf8'),
    reservas: readFileSync(new URL('../../resources/views/consultorios/index.blade.php', import.meta.url), 'utf8'),
};

for (const [screen, source] of Object.entries(sources)) {
    test(`${screen}: the complete inline scripts remain valid after unifying room selection`, () => {
        const scripts = [...source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)];
        assert.ok(scripts.length > 0);
        for (const [, code] of scripts) {
            // Substitute only the known server-generated route expression.
            const javascript = code.replace(/@json\(route\('consultorios\.availability'\)\)/g, '"/consultorios/availability"');
            assert.doesNotThrow(() => new Script(javascript));
        }
    });
}

test('reservation selection has no independent sub-room input or obsolete JavaScript dependency', () => {
    assert.match(sources.reservas, /name="consultorio_numero"/);
    assert.doesNotMatch(sources.reservas, /name="cubiculo_numero"|\bformCubiculo\b/);
});

test('the case form uses the canonical ID, without the legacy physical-space selectors', () => {
    assert.match(sources.expediente, /name="cubiculo_id"/);
    assert.doesNotMatch(sources.expediente, /name="resumen_clinico\[cubiculo\]"|\bconsultorioInput\b|\bhiddenConsultorioInput\b|\bhiddenCubiculoInput\b/);
});

test('availability uses the selected room number, not its different database ID', () => {
    const helper = sources.shared.match(/const roomNumber = [^;]+;/)?.[0];
    assert.ok(helper, 'Exercise the helper shared by the two real forms.');
    const selected = {id: 'cubiculo_id', value: '118', selectedOptions: [{dataset: {numero: '18'}}]};
    const getNumber = runInNewContext(helper + '\nroomNumber;');
    assert.equal(getNumber(selected), '18');
    selected.selectedOptions = [];
    assert.equal(getNumber(selected), '');
    assert.equal(getNumber({id: 'asignacion-consultorio', value: '18'}), '18');
});
