@props([
    'title' => null,
])

<div {{ $attributes->class('bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden') }}>
    @if($title || isset($header))
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between gap-4">
            @if($title)
                <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
            @endif

            @isset($header)
                <div class="flex items-center gap-2">{{ $header }}</div>
            @endisset
        </div>
    @endif

    <div class="px-6 py-5">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
            {{ $footer }}
        </div>
    @endisset
</div>
