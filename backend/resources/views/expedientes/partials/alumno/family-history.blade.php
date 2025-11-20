@php
    $rawHereditaryHistory = old(
        'antecedentes_familiares',
        $expediente->antecedentes_familiares ?? \App\Models\Expediente::defaultFamilyHistory()
    );

    $hereditaryHistory = collect(\App\Models\Expediente::defaultFamilyHistory())
        ->mapWithKeys(function (array $members, string $condition) use ($rawHereditaryHistory) {
            $providedMembers = is_array($rawHereditaryHistory[$condition] ?? null)
                ? $rawHereditaryHistory[$condition]
                : [];

            $normalizedMembers = collect($members)
                ->mapWithKeys(function (bool $default, string $member) use ($providedMembers) {
                    $value = $providedMembers[$member] ?? $default;
                    $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                    if ($normalized === null) {
                        return [$member => (bool) $value];
                    }

                    return [$member => $normalized];
                })
                ->all();

            return [$condition => $normalizedMembers];
        })
        ->all();
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
                                    @php
                                        $isChecked = $hereditaryHistory[$conditionKey][$memberKey] ?? false;
                                        $inputId = "antecedentes_familiares_{$conditionKey}_{$memberKey}";
                                    @endphp
                                    <input
                                        type="hidden"
                                        name="antecedentes_familiares[{{ $conditionKey }}][{{ $memberKey }}]"
                                        value="{{ $isChecked ? '1' : '0' }}"
                                        :value="isChecked('{{ $conditionKey }}', '{{ $memberKey }}') ? '1' : '0'"
                                    >
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        id="{{ $inputId }}"
                                        value="1"
                                        @checked($isChecked)
                                        :checked="isChecked('{{ $conditionKey }}', '{{ $memberKey }}')"
                                        @change="toggle('{{ $conditionKey }}', '{{ $memberKey }}', $event.target.checked)"
                                    >
                                    <label
                                        class="visually-hidden"
                                        for="{{ $inputId }}"
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

@php
    $systemsReviewSections = $systemsReviewSections ?? \App\Models\Expediente::SYSTEMS_REVIEW_SECTIONS;

    if ($systemsReviewSections instanceof \Illuminate\Support\Collection) {
        $systemsReviewSections = $systemsReviewSections->toArray();
    }
    $systemsReviewValues = old('aparatos_sistemas', $expediente->aparatos_sistemas ?? []);
@endphp

<div class="mt-5">
    <h6 class="mb-3">Antecedente y Padecimiento Actual</h6>
    <p class="text-muted small mb-3">
        Describe brevemente el motivo de atención, los síntomas actuales y cualquier antecedente inmediato relevante.
    </p>

    <div class="row g-3">
        @foreach ($systemsReviewSections as $section => $label)
            @php
                $fieldName = "aparatos_sistemas[$section]";
                $fieldId = 'aparatos_sistemas_' . $section;
                $fieldValue = $systemsReviewValues[$section] ?? '';
            @endphp
            <div class="col-12 col-lg-4">
                <label for="{{ $fieldId }}" class="form-label">{{ $label }}</label>
                <textarea
                    name="{{ $fieldName }}"
                    id="{{ $fieldId }}"
                    class="form-control @error("aparatos_sistemas.$section") is-invalid @enderror"
                    rows="4"
                    maxlength="1000"
                >{{ old("aparatos_sistemas.$section", $fieldValue) }}</textarea>
                <div class="form-text">Máximo 1000 caracteres.</div>
                @error("aparatos_sistemas.$section")
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endforeach
    </div>
</div>

<div class="mt-5">
    <h6 class="mb-3">Plan de acción</h6>
    <p class="text-muted small mb-3">
        Describe el plan de intervención acordado, incluyendo actividades, responsables y tiempos de seguimiento.
    </p>

    <div class="mb-3">
        <label for="plan_accion" class="form-label">Plan de Acción</label>
        <textarea
            name="plan_accion"
            id="plan_accion"
            class="form-control @error('plan_accion') is-invalid @enderror"
            rows="4"
            maxlength="1000"
        >{{ old('plan_accion', $expediente->plan_accion ?? '') }}</textarea>
        <div class="form-text">Máximo 1000 caracteres.</div>
        @error('plan_accion')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mt-5">
    <h6 class="mb-3">Diagnóstico Médico Odontológico</h6>
    <p class="text-muted small mb-3">
        Registra los diagnósticos clínicos relevantes y cualquier observación complementaria.
    </p>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <label for="diagnostico" class="form-label">Diagnostico</label>
            <textarea
                name="diagnostico"
                id="diagnostico"
                class="form-control @error('diagnostico') is-invalid @enderror"
                rows="4"
                maxlength="1000"
            >{{ old('diagnostico', $expediente->diagnostico ?? '') }}</textarea>
            <div class="form-text">Máximo 1000 caracteres.</div>
            @error('diagnostico')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12 col-lg-4">
            <label for="dsm_tr" class="form-label">DSM y TR</label>
            <textarea
                name="dsm_tr"
                id="dsm_tr"
                class="form-control @error('dsm_tr') is-invalid @enderror"
                rows="4"
                maxlength="255"
            >{{ old('dsm_tr', $expediente->dsm_tr ?? '') }}</textarea>
            <div class="form-text">Máximo 255 caracteres.</div>
            @error('dsm_tr')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12 col-lg-4">
            <label for="observaciones_relevantes" class="form-label">Observaciones relevantes</label>
            <textarea
                name="observaciones_relevantes"
                id="observaciones_relevantes"
                class="form-control @error('observaciones_relevantes') is-invalid @enderror"
                rows="4"
                maxlength="1000"
            >{{ old('observaciones_relevantes', $expediente->observaciones_relevantes ?? '') }}</textarea>
            <div class="form-text">Máximo 1000 caracteres.</div>
            @error('observaciones_relevantes')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>
