@php
    $field = fn (string $key): string => $nested ? 'consultorio_reserva['.$key.']' : $key;
    $oldKey = $nested ? 'consultorio_reserva.' : '';
    $weekly = old($oldKey.'modo_repeticion') === 'semanal';
    $weekdays = (array) old($oldKey.'dias_semana', []);
    $baseDate = old($oldKey.'fecha', now('America/Mexico_City')->toDateString());
    $id = $nested ? 'expediente-asignacion' : 'asignacion';
@endphp
<div class="col-12" data-reservation-dates data-room="{{ $nested ? 'cubiculo_id' : 'asignacion-consultorio' }}" data-input-prefix="{{ $id }}"
    data-availability="{{ route('consultorios.availability') }}" data-limit="{{ \App\Services\ReservationDates::LIMIT }}">
    <div class="row g-3">
        <div class="col-md-4">
            <fieldset>
                <legend class="form-label">Tipo de registro</legend>
                @foreach (['unica' => 'Fecha única', 'semanal' => 'Repetición semanal'] as $mode => $label)
                    <label class="form-check form-check-inline">
                        <input type="radio" class="form-check-input" data-mode name="{{ $field('modo_repeticion') }}" value="{{ $mode }}" @checked($weekly === ($mode === 'semanal'))>
                        {{ $label }}
                    </label>
                @endforeach
            </fieldset>
        </div>
        <div class="col-md-3" data-single @if($weekly) hidden @endif>
            <label class="form-label" for="{{ $id }}-fecha">Día</label>
            <input id="{{ $id }}-fecha" type="date" class="form-control" data-date name="{{ $field('fecha') }}" value="{{ $baseDate }}" @required(! $weekly) @disabled($weekly)>
        </div>
        <fieldset class="col-md-8" data-weekly @if(! $weekly) hidden disabled @endif>
            <legend class="visually-hidden">Periodo y días de la semana</legend>
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label" for="{{ $id }}-desde">Desde</label>
                    <input id="{{ $id }}-desde" type="date" class="form-control" data-from name="{{ $field('fecha_inicio_repeticion') }}" value="{{ old($oldKey.'fecha_inicio_repeticion', $baseDate) }}" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="{{ $id }}-hasta">Hasta</label>
                    <input id="{{ $id }}-hasta" type="date" class="form-control" data-until name="{{ $field('fecha_fin_repeticion') }}" value="{{ old($oldKey.'fecha_fin_repeticion') }}" required>
                </div>
                <div class="col-12">
                    <span class="form-label">Días de la semana (marca uno o varios)</span>
                    <div>
                        @foreach ([1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'] as $day => $label)
                            <label class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" data-weekday name="{{ $field('dias_semana') }}[]" value="{{ $day }}" @checked(in_array($day, $weekdays))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </fieldset>
    </div>
    <p class="small mt-2 mb-0" aria-live="polite" data-summary></p>
    <details class="small" data-preview hidden><summary>Ver fechas seleccionadas</summary><span data-list></span></details>
    <div class="alert alert-warning mt-2 mb-0" role="status" data-feedback hidden></div>
</div>
@once
    @push('scripts')
        @include('consultorios.partials.fechas-asignacion-script')
    @endpush
@endonce
