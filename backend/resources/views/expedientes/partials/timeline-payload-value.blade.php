@php
    $formatTimelineLabel = static function (string|int $key): string {
        if (is_int($key) || ctype_digit((string) $key)) {
            return 'Elemento '.((int) $key + 1);
        }

        return \Illuminate\Support\Str::of((string) $key)
            ->replace(['_', '.'], ' ')
            ->squish()
            ->headline()
            ->toString();
    };

    $formatTimelineScalar = static function (mixed $value): string {
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        if ($value === null || $value === '') {
            return '—';
        }

        return (string) $value;
    };

    $decodeJsonScalar = static function (mixed $value): mixed {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || ! in_array($trimmed[0], ['{', '['], true)) {
            return $value;
        }

        $decoded = json_decode($trimmed, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return $value;
        }

        return $decoded;
    };

    $value = $decodeJsonScalar($value);
@endphp

@if (is_array($value))
    @if ($value === [])
        <span class="text-muted">—</span>
    @elseif (array_is_list($value))
        <ul class="timeline-payload-list mb-0">
            @foreach ($value as $item)
                <li>
                    @include('expedientes.partials.timeline-payload-value', ['value' => $item])
                </li>
            @endforeach
        </ul>
    @else
        <dl class="timeline-payload-dl row mb-0 g-1">
            @foreach ($value as $childKey => $childValue)
                <dt class="col-sm-4 text-muted mb-0">{{ $formatTimelineLabel($childKey) }}</dt>
                <dd class="col-sm-8 mb-0">
                    @include('expedientes.partials.timeline-payload-value', ['value' => $childValue])
                </dd>
            @endforeach
        </dl>
    @endif
@else
    <span>{{ $formatTimelineScalar($value) }}</span>
@endif
