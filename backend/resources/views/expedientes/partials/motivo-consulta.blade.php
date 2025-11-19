@php
    $motivo = $expediente->motivo_consulta;
    $hideWhenEmpty = $hideWhenEmpty ?? false;
@endphp

@if (! $hideWhenEmpty || filled($motivo))
    <div class="mt-4">
        <h6 class="text-muted text-uppercase small mb-2">Motivo de la consulta / Nota de ingreso</h6>
        @if (filled($motivo))
            <p class="mb-0">{!! nl2br(e($motivo)) !!}</p>
        @else
            <p class="mb-0 text-muted fst-italic">Sin información registrada.</p>
        @endif
    </div>
@endif
