@props([
    'title' => null,
])

<div {{ $attributes->class('bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden') }}>
    @if($title || isset($filters))
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                @if($title)
                    <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
                @endif

                @isset($filters)
                    <div class="flex flex-wrap items-center gap-3">
                        {{ $filters }}
                    </div>
                @endisset
            </div>
        </div>
    @endif

    <div class="px-6 py-5">
        @isset($table)
            {{ $table }}
        @else
            {{ $slot }}
        @endisset
    </div>
</div>
