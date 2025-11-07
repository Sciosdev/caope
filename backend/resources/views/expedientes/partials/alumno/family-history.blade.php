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

    <div class="d-grid gap-3">
        @foreach ($personalPathologicalConditions as $conditionKey => $conditionLabel)
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
            @endphp
            <div
                class="row g-3 align-items-center"
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
                <div class="col-12 col-md-7">
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
                <div class="col-12 col-md-5">
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
