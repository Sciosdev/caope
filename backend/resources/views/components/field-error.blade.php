@props([
    'field',
    'bag' => 'default',
])

@php
    $errorBag = $errors->getBag($bag ?? 'default');
@endphp

@if ($errorBag->has($field))
    <p {{ $attributes->class('mt-1 text-sm text-red-600') }}>
        {{ $errorBag->first($field) }}
    </p>
@endif
