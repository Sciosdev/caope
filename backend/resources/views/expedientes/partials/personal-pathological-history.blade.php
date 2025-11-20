@php
    $personalHistory = $personalPathologicalHistory
        ?? $expediente->antecedentes_personales_patologicos
        ?? \App\Models\Expediente::defaultPersonalPathologicalHistory();

    $pathologicalConditions = $personalPathologicalConditions
        ?? \App\Models\Expediente::PERSONAL_PATHOLOGICAL_CONDITIONS;

    $selectedConditions = collect($pathologicalConditions)
        ->filter(fn (string $conditionLabel, string $conditionKey) =>
            (bool) ($personalHistory[$conditionKey]['padece'] ?? false)
        );

    $personalHistoryObservations = $personalPathologicalObservations
        ?? $expediente->antecedentes_personales_observaciones
        ?? '';

    $personalHistoryColumns = $selectedConditions
        ->chunk((int) max(1, ceil($selectedConditions->count() / 2)));
@endphp

<h6 class="mb-3">Antecedentes Personales Patológicos</h6>

@if ($selectedConditions->isEmpty())
    <p class="mb-0 text-muted fst-italic">Sin antecedentes seleccionados.</p>
@else
    <div class="row g-3">
        @foreach ($personalHistoryColumns as $column)
            <div class="col-12 col-lg-6">
                <div class="border rounded h-100">
                    <div class="row g-0 align-items-center bg-light text-muted small fw-semibold border-bottom px-3 py-2">
                        <div class="col-7">Padecimientos</div>
                        <div class="col-5 text-end">Fechas</div>
                    </div>
                    @foreach ($column as $conditionKey => $conditionLabel)
                        @php
                            $record = $personalHistory[$conditionKey] ?? [];
                            $diagnosisDate = $record['fecha'] ?? null;
                            $carbonDate = \Illuminate\Support\Carbon::make($diagnosisDate);
                            $displayDate = null;
                            if ($carbonDate) {
                                $displayDate = $carbonDate->format('Y-m-d');
                            } elseif (is_string($diagnosisDate) && $diagnosisDate !== '') {
                                $displayDate = $diagnosisDate;
                            }
                            $rowClasses = 'row g-0 align-items-center px-3 py-3';
                            if (! $loop->last) {
                                $rowClasses .= ' border-bottom';
                            }
                        @endphp
                        <div class="{{ $rowClasses }}">
                            <div class="col-7 pe-3">
                                <div class="form-check d-flex align-items-center gap-2 mb-0">
                                    <span class="form-check-input position-static bg-primary border-primary" role="presentation"></span>
                                    <span class="fw-semibold">{{ $conditionLabel }}</span>
                                </div>
                            </div>
                            <div class="col-5 ps-3 text-end">
                                @if ($displayDate)
                                    <span class="small">{{ $displayDate }}</span>
                                @else
                                    <span class="text-muted small fst-italic">Sin registro</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="mt-3">
    <span class="text-muted small d-block">Observaciones</span>
    @if (filled($personalHistoryObservations))
        <p class="mb-0">{!! nl2br(e($personalHistoryObservations)) !!}</p>
    @else
        <p class="mb-0 text-muted fst-italic">Sin observaciones registradas.</p>
    @endif
</div>
