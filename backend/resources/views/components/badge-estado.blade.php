@props([
    'estado',
    'size' => 'sm',
])

@php
    $normalized = \Illuminate\Support\Str::of($estado)->lower()->slug('_');

    $sizes = [
        'xs' => 'text-xs px-2 py-0.5',
        'sm' => 'text-sm px-2.5 py-0.5',
        'md' => 'text-base px-3 py-1',
    ];

    $variants = [
        'pendiente' => 'bg-amber-100 text-amber-800 ring-amber-200',
        'en_proceso' => 'bg-blue-100 text-blue-800 ring-blue-200',
        'aprobado' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        'rechazado' => 'bg-red-100 text-red-800 ring-red-200',
        'completado' => 'bg-teal-100 text-teal-800 ring-teal-200',
        'cerrado' => 'bg-slate-100 text-slate-800 ring-slate-200',
    ];

    $baseClasses = 'inline-flex items-center font-medium rounded-full ring-1 ring-inset';
    $sizeClasses = $sizes[$size] ?? $sizes['sm'];
    $colorClasses = $variants[$normalized] ?? 'bg-gray-100 text-gray-800 ring-gray-200';
@endphp

<span {{ $attributes->class("$baseClasses $sizeClasses $colorClasses") }}>
    {{ $slot->isEmpty() ? \Illuminate\Support\Str::of($estado)->title() : $slot }}
</span>
