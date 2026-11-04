@props([
    'id',
    'name' => null,
    'required' => false,
    'showLabel' => __('Mostrar contraseña'),
    'hideLabel' => __('Ocultar contraseña'),
])

<div class="input-group" data-password-field>
    <input type="password" id="{{ $id }}" name="{{ $name ?? $id }}" class="form-control" autocomplete="new-password" @required($required)>
    <button
        type="button"
        class="btn btn-outline-secondary"
        data-password-toggle
        data-show-label="{{ $showLabel }}"
        data-hide-label="{{ $hideLabel }}"
        aria-label="{{ $showLabel }}"
        title="{{ $showLabel }}"
        aria-controls="{{ $id }}"
        aria-pressed="false"
    >
        <span data-password-show-icon aria-hidden="true">
            <svg focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                <circle cx="12" cy="12" r="3" />
            </svg>
        </span>
        <span data-password-hide-icon hidden aria-hidden="true">
            <svg focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="m3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a20 20 0 0 1-3 4.1M6.2 6.2A20 20 0 0 0 2 12s3.5 7 10 7a11 11 0 0 0 5.8-1.8M9.9 9.9a3 3 0 0 0 4.2 4.2" />
            </svg>
        </span>
    </button>
</div>

@once
    @push('scripts')
        <script data-password-visibility>
            (() => {
                const setVisibility = (field, visible) => {
                    const input = field?.querySelector('input');
                    const button = field?.querySelector('[data-password-toggle]');

                    if (!input || !button) return;

                    input.type = visible ? 'text' : 'password';
                    const label = visible ? button.dataset.hideLabel : button.dataset.showLabel;
                    button.setAttribute('aria-label', label);
                    button.setAttribute('title', label);
                    button.setAttribute('aria-pressed', String(visible));
                    button.querySelector('[data-password-show-icon]').hidden = visible;
                    button.querySelector('[data-password-hide-icon]').hidden = !visible;
                };

                document.addEventListener('click', (event) => {
                    const button = event.target?.closest?.('[data-password-toggle]');
                    if (!button) return;

                    const field = button.closest('[data-password-field]');
                    const input = field?.querySelector('input');
                    if (!input) return;

                    setVisibility(field, input.type === 'password');
                });

                ['reset', 'submit'].forEach((eventName) => {
                    document.addEventListener(eventName, (event) => {
                        event.target.querySelectorAll('[data-password-field]').forEach((field) => {
                            setVisibility(field, false);
                        });
                    });
                });
            })();
        </script>
    @endpush
@endonce
