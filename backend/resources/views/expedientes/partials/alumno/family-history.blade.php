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
