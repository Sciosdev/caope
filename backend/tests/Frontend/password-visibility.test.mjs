import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const component = readFileSync(new URL('../../resources/views/components/password-input.blade.php', import.meta.url), 'utf8');
const script = component.match(/<script data-password-visibility>([\s\S]*?)<\/script>/)?.[1];
assert.ok(script, 'The test must exercise the script shipped in the password component.');

function setup() {
    const listeners = new Map();
    const fields = ['contraseña', 'confirmación de contraseña'].map((name) => {
        const input = { type: 'password', value: 'Example-Test-Password-123!' };
        const icons = { '[data-password-show-icon]': { hidden: false }, '[data-password-hide-icon]': { hidden: true } };
        const attributes = { 'aria-label': `Mostrar ${name}`, 'aria-pressed': 'false' };
        const button = {
            dataset: { showLabel: `Mostrar ${name}`, hideLabel: `Ocultar ${name}` },
            setAttribute: (key, value) => { attributes[key] = value; },
            querySelector: (selector) => icons[selector],
            closest: (selector) => selector === '[data-password-toggle]' ? button : field,
        };
        const field = { querySelector: (selector) => selector === 'input' ? input : button };
        return { field, input, button, icons, attributes };
    });
    runInNewContext(script, { document: { addEventListener: (name, handler) => { listeners.set(name, handler); } } });
    return { fields, listeners };
}

test('toggles one field without changing its value or revealing confirmation', () => {
    const { fields: [password, confirmation], listeners } = setup();
    listeners.get('click')({ target: password.button });
    assert.equal(password.input.type, 'text');
    assert.equal(password.input.value, 'Example-Test-Password-123!');
    assert.equal(password.attributes['aria-label'], 'Ocultar contraseña');
    assert.equal(password.attributes['aria-pressed'], 'true');
    assert.equal(password.icons['[data-password-show-icon]'].hidden, true);
    assert.equal(password.icons['[data-password-hide-icon]'].hidden, false);
    assert.equal(confirmation.input.type, 'password');
    listeners.get('click')({ target: password.button });
    assert.equal(password.input.type, 'password');
    assert.equal(password.attributes['aria-label'], 'Mostrar contraseña');
    assert.equal(password.attributes['aria-pressed'], 'false');
});

test('clicking an eye icon toggles the confirmation control independently', () => {
    const { fields: [password, confirmation], listeners } = setup();
    listeners.get('click')({ target: { closest: () => confirmation.button } });
    assert.equal(confirmation.input.type, 'text');
    assert.equal(confirmation.attributes['aria-label'], 'Ocultar confirmación de contraseña');
    assert.equal(password.input.type, 'password');
});

test('ignores unrelated clicks and missing fields', () => {
    const { fields, listeners } = setup();
    listeners.get('click')({ target: { closest: () => null } });
    listeners.get('click')({ target: {} });
    listeners.get('click')({ target: { closest: () => ({ closest: () => null }) } });
    assert.ok(fields.every(({ input }) => input.type === 'password'));
});

for (const eventName of ['reset', 'submit']) {
    test(`${eventName} masks both controls without changing submitted values`, () => {
        const { fields, listeners } = setup();
        for (const { button } of fields) listeners.get('click')({ target: button });
        listeners.get(eventName)({ target: { querySelectorAll: () => fields.map(({ field }) => field) } });
        for (const { input, attributes, icons } of fields) {
            assert.equal(input.type, 'password');
            assert.equal(input.value, 'Example-Test-Password-123!');
            assert.equal(attributes['aria-pressed'], 'false');
            assert.equal(icons['[data-password-show-icon]'].hidden, false);
            assert.equal(icons['[data-password-hide-icon]'].hidden, true);
        }
    });
}
