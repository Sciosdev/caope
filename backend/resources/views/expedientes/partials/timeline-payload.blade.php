@php
    $timelineSectionCustomLabels = [
        'dsm_tr' => 'Posibles Diagnósticos',
    ];

    $formatTimelineSectionLabel = static function (string|int $key) use ($timelineSectionCustomLabels): string {
        if (is_int($key) || ctype_digit((string) $key)) {
            return 'Sección '.((int) $key + 1);
        }

        $normalizedKey = \Illuminate\Support\Str::of((string) $key)
            ->replace([' ', '.', '-'], '_')
            ->lower()
            ->toString();

        if (isset($timelineSectionCustomLabels[$normalizedKey])) {
            return $timelineSectionCustomLabels[$normalizedKey];
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
