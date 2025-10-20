@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl',
    'title' => null,
    'closeButton' => true,
])

@php
    $maxWidthClasses = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '3xl' => 'sm:max-w-3xl',
    ][$maxWidth] ?? 'sm:max-w-2xl';

    $labelId = ($title || isset($header)) ? 'modal-title-' . $name : null;
@endphp

<div
    x-data="{
        show: @js($show),
        previouslyFocused: null,
        close() {
            this.show = false;
        },
        focusables() {
            return Array.from(this.$refs.dialog?.querySelectorAll(
                'a[href], area[href], input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])'
            ) || []).filter((el) => !el.hasAttribute('disabled') && !el.getAttribute('aria-hidden') && el.offsetParent !== null);
        },
        focusFirst() {
            const focusables = this.focusables();
            const first = focusables[0];
            if (first) {
                first.focus();
            } else if (this.$refs.dialog) {
                this.$refs.dialog.focus();
            }
        },
        focusNext() {
            const focusables = this.focusables();
            if (!focusables.length) return;

            const active = document.activeElement;
            const index = focusables.indexOf(active);
            const nextIndex = index === -1 ? 0 : (index + 1) % focusables.length;
            const next = focusables[nextIndex];
            next.focus();
        },
        focusPrevious() {
            const focusables = this.focusables();
            if (!focusables.length) return;

            const active = document.activeElement;
            const index = focusables.indexOf(active);
            const previousIndex = index === -1 ? focusables.length - 1 : (index - 1 + focusables.length) % focusables.length;
            const previous = focusables[previousIndex];
            previous.focus();
        },
        handleTab(event) {
            if (event.shiftKey) {
                this.focusPrevious();
            } else {
                this.focusNext();
            }
        },
        trapFocus() {
            this.previouslyFocused = document.activeElement;
            this.$nextTick(() => this.focusFirst());
        },
        releaseFocus() {
            if (this.previouslyFocused && typeof this.previouslyFocused.focus === 'function') {
                this.previouslyFocused.focus();
            }
            this.previouslyFocused = null;
        },
    }"
    x-init="function () {
        if (this.show) {
            document.body.classList.add('overflow-hidden');
            this.trapFocus();
        }

        this.$watch('show', value => {
            document.body.classList.toggle('overflow-hidden', value);

            if (value) {
                this.trapFocus();
            } else {
                this.releaseFocus();
            }
        });
    }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') show = true"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') close()"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6 sm:px-6"
    style="display: none;"
>
    <div
        x-show="show"
        x-transition.opacity
        class="fixed inset-0 bg-gray-900/60"
        x-on:click="close()"
    ></div>

    <div
        x-show="show"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-ref="dialog"
        class="relative w-full max-h-[90vh] overflow-hidden bg-white border border-gray-200 shadow-2xl rounded-2xl {{ $maxWidthClasses }}"
        x-on:keydown.escape.window="close()"
        x-on:keydown.tab.prevent="handleTab($event)"
        role="dialog"
        aria-modal="true"
        tabindex="-1"
        @if($labelId)
            aria-labelledby="{{ $labelId }}"
        @else
            aria-label="Modal"
        @endif
    >
        @if($title || isset($header) || $closeButton)
            <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="min-h-[1.5rem] flex-1">
                    @if($title)
                        <h2 id="{{ $labelId }}" class="text-lg font-semibold text-gray-900">{{ $title }}</h2>
                    @elseif(isset($header))
                        <div id="{{ $labelId }}">{{ $header }}</div>
                    @endif
                </div>

                @if($closeButton)
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        x-on:click="close()"
                        aria-label="Cerrar"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
        @endif

        <div class="overflow-y-auto px-6 py-5">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="border-t border-gray-200 bg-gray-50 px-6 py-4">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
