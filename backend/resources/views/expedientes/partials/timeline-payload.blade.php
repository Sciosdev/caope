@php
    $formatTimelineSectionLabel = static function (string|int $key): string {
        if (is_int($key) || ctype_digit((string) $key)) {
            return 'Sección '.((int) $key + 1);
        }

        return \Illuminate\Support\Str::of((string) $key)
            ->replace(['_', '.'], ' ')
            ->squish()
            ->headline()
            ->toString();
    };
@endphp

<div class="timeline-payload mt-2">
    @if (is_array($payload) && ! empty($payload))
        @foreach ($payload as $section => $value)
            <div class="timeline-payload-section border rounded bg-light-subtle p-2 mb-2">
                <p class="timeline-payload-title text-uppercase text-muted fw-semibold small mb-1">{{ $formatTimelineSectionLabel($section) }}</p>
                @include('expedientes.partials.timeline-payload-value', ['value' => $value])
            </div>
        @endforeach
    @endif
</div>
