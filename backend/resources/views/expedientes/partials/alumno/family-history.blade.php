@php
    $hereditaryHistory = old(
        'antecedentes_familiares',
        $expediente->antecedentes_familiares ?? \App\Models\Expediente::defaultFamilyHistory()
    );
@endphp

<div class="mt-4" x-data="hereditaryHistory({
    conditions: @js($hereditaryHistoryConditions),
    members: @js($familyHistoryMembers),
    initialState: @js($hereditaryHistory),
})">
    <h6 class="mb-3">Antecedentes familiares hereditarios</h6>
    <p class="text-muted small mb-3">
        Selecciona los familiares que presentan cada padecimiento hereditario. Puedes agregar observaciones generales al final.
    </p>

    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th class="w-30">Padecimientos</th>
                    @foreach ($familyHistoryMembers as $memberLabel)
                        <th class="text-center">{{ $memberLabel }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($hereditaryHistoryConditions as $conditionKey => $conditionLabel)
                    <tr>
                        <td class="fw-semibold">{{ $conditionLabel }}</td>
                        @foreach ($familyHistoryMembers as $memberKey => $memberLabel)
                            <td class="text-center">
                                <div class="form-check d-inline-flex justify-content-center align-items-center">
                                    <input
                                        type="hidden"
                                        :name="inputName('{{ $conditionKey }}', '{{ $memberKey }}')"
                                        :value="isChecked('{{ $conditionKey }}', '{{ $memberKey }}') ? '1' : '0'"
                                    >
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        :id="checkboxId('{{ $conditionKey }}', '{{ $memberKey }}')"
                                        value="1"
                                        :checked="isChecked('{{ $conditionKey }}', '{{ $memberKey }}')"
                                        @change="toggle('{{ $conditionKey }}', '{{ $memberKey }}', $event.target.checked)"
                                    >
                                    <label
                                        class="visually-hidden"
                                        :for="checkboxId('{{ $conditionKey }}', '{{ $memberKey }}')"
                                    >
                                        {{ $conditionLabel }} – {{ $memberLabel }}
                                    </label>
                                </div>
                                @error("antecedentes_familiares.$conditionKey.$memberKey")
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        <label for="antecedentes_observaciones" class="form-label">Observaciones</label>
        <textarea
            name="antecedentes_observaciones"
            id="antecedentes_observaciones"
            class="form-control @error('antecedentes_observaciones') is-invalid @enderror"
            rows="3"
            maxlength="500"
        >{{ old('antecedentes_observaciones', $expediente->antecedentes_observaciones ?? '') }}</textarea>
        <div class="form-text">Máximo 500 caracteres.</div>
        @error('antecedentes_observaciones')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

@php
    $personalHistory = old(
        'antecedentes_personales_patologicos',
        $expediente->antecedentes_personales_patologicos ?? \App\Models\Expediente::defaultPersonalPathologicalHistory()
    );
@endphp

<div class="mt-5">
    <h6 class="mb-3">Antecedentes personales patológicos</h6>
    <p class="text-muted small mb-3">
        Registra los padecimientos que el alumno ha presentado, la fecha de diagnóstico conocida y agrega observaciones generales si es necesario.
    </p>

    @php
        $personalPathologicalColumns = collect($personalPathologicalConditions)
            ->chunk((int) ceil(count($personalPathologicalConditions) / 2));
    @endphp

    <div class="row g-3">
        @foreach ($personalPathologicalColumns as $column)
            <div class="col-12 col-lg-6">
                <div class="border rounded h-100">
                    <div class="row g-0 align-items-center bg-light text-muted small fw-semibold border-bottom px-3 py-2">
                        <div class="col-7">Padecimientos</div>
                        <div class="col-5 text-end">Fechas</div>
                    </div>
                    @foreach ($column as $conditionKey => $conditionLabel)
                        @php
                            $current = $personalHistory[$conditionKey] ?? [];
                            $hasCondition = filter_var($current['padece'] ?? false, FILTER_VALIDATE_BOOLEAN);
                            $diagnosisDate = $current['fecha'] ?? null;
                            $diagnosisDateValue = '';
                            if ($diagnosisDate !== null) {
                                $carbonDate = \Illuminate\Support\Carbon::make($diagnosisDate);
                                if ($carbonDate) {
                                    $diagnosisDateValue = $carbonDate->format('Y-m-d');
                                } elseif (is_string($diagnosisDate)) {
                                    $diagnosisDateValue = $diagnosisDate;
                                }
                            }
                            $inputId = "antecedentes_personales_{$conditionKey}";
                            $rowClasses = 'row g-0 align-items-center px-3 py-3';
                            if (! $loop->last) {
                                $rowClasses .= ' border-bottom';
                            }
                        @endphp
                        <div
                            class="{{ $rowClasses }}"
                            x-data="{
                                checked: @json($hasCondition),
                                dateValue: @js($diagnosisDateValue),
                                toggle(checked) {
                                    this.checked = Boolean(checked);
                                    if (!this.checked) {
                                        this.dateValue = '';
                                    }
                                },
                            }"
                            x-init="toggle(checked)"
                        >
                            <div class="col-7 pe-3">
                                <div class="form-check d-flex align-items-center gap-2 mb-0">
                                    <input
                                        type="hidden"
                                        name="antecedentes_personales_patologicos[{{ $conditionKey }}][padece]"
                                        value="0"
                                    >
                                    <input
                                        type="checkbox"
                                        class="form-check-input me-2 @error("antecedentes_personales_patologicos.$conditionKey.padece") is-invalid @enderror"
                                        id="{{ $inputId }}"
                                        name="antecedentes_personales_patologicos[{{ $conditionKey }}][padece]"
                                        value="1"
                                        :checked="checked"
                                        @change="toggle($event.target.checked)"
                                    >
                                    <label class="form-check-label fw-semibold mb-0" for="{{ $inputId }}">
                                        {{ $conditionLabel }}
                                    </label>
                                </div>
                                @error("antecedentes_personales_patologicos.$conditionKey.padece")
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-5 ps-3">
                                <label class="form-label small mb-1" for="{{ $inputId }}_fecha">Fecha de diagnóstico</label>
                                <input
                                    type="date"
                                    name="antecedentes_personales_patologicos[{{ $conditionKey }}][fecha]"
                                    id="{{ $inputId }}_fecha"
                                    class="form-control form-control-sm @error("antecedentes_personales_patologicos.$conditionKey.fecha") is-invalid @enderror"
                                    x-model="dateValue"
                                    value="{{ $diagnosisDateValue }}"
                                    max="{{ now()->format('Y-m-d') }}"
                                    :disabled="!checked"
                                >
                                @error("antecedentes_personales_patologicos.$conditionKey.fecha")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-3">
        <label for="antecedentes_personales_observaciones" class="form-label">Observaciones</label>
        <textarea
            name="antecedentes_personales_observaciones"
            id="antecedentes_personales_observaciones"
            class="form-control @error('antecedentes_personales_observaciones') is-invalid @enderror"
            rows="3"
            maxlength="500"
        >{{ old('antecedentes_personales_observaciones', $expediente->antecedentes_personales_observaciones ?? '') }}</textarea>
        <div class="form-text">Máximo 500 caracteres.</div>
        @error('antecedentes_personales_observaciones')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mt-5">
    <h6 class="mb-3">Antecedente y Padecimiento Actual</h6>
    <p class="text-muted small mb-3">
        Describe brevemente el motivo de atención, los síntomas actuales y cualquier antecedente inmediato relevante.
    </p>

    <div>
        <label for="antecedente_padecimiento_actual" class="form-label">Resumen clínico actual</label>
        <textarea
            name="antecedente_padecimiento_actual"
            id="antecedente_padecimiento_actual"
            class="form-control @error('antecedente_padecimiento_actual') is-invalid @enderror"
            rows="4"
            maxlength="1000"
        >{{ old('antecedente_padecimiento_actual', $expediente->antecedente_padecimiento_actual ?? '') }}</textarea>
        <div class="form-text">Máximo 1000 caracteres.</div>
        @error('antecedente_padecimiento_actual')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

@php
    $systemsReviewState = old(
        'aparatos_sistemas',
        $expediente->aparatos_sistemas ?? \App\Models\Expediente::defaultSystemsReview()
    );
@endphp

<div class="mt-5">
    <h6 class="mb-3">Aparatos y sistemas</h6>
    <p class="text-muted small mb-3">
        Registra hallazgos relevantes por apartado para complementar la valoración psicológica del paciente.
    </p>

    <div class="row g-3">
        @foreach (\App\Models\Expediente::SYSTEMS_REVIEW_SECTIONS as $sectionKey => $sectionLabel)
            <div class="col-12 col-lg-4">
                <label class="form-label" for="aparatos_sistemas_{{ $sectionKey }}">{{ $sectionLabel }}</label>
                <textarea
                    name="aparatos_sistemas[{{ $sectionKey }}]"
                    id="aparatos_sistemas_{{ $sectionKey }}"
                    class="form-control @error("aparatos_sistemas.$sectionKey") is-invalid @enderror"
                    rows="4"
                    maxlength="1000"
                >{{ old("aparatos_sistemas.$sectionKey", $systemsReviewState[$sectionKey] ?? '') }}</textarea>
                <div class="form-text">Máximo 1000 caracteres.</div>
                @error("aparatos_sistemas.$sectionKey")
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endforeach
    </div>
</div>
