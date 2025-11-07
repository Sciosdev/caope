@php
    $clinicalHistory = old(
        'antecedentes_clinicos',
        $expediente->antecedentes_clinicos ?? \App\Models\Expediente::defaultClinicalHistory()
    );
@endphp

<div class="mt-4" x-data="clinicalHistory({
    conditions: @js($clinicalHistoryConditions),
    members: @js($familyHistoryMembers),
    initialState: @js($clinicalHistory),
})">
    <h6 class="mb-3">Antecedentes clínicos</h6>
    <p class="text-muted small">Marca los familiares que presentan cada padecimiento y agrega observaciones si aplica.</p>

    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th class="w-25">Padecimiento</th>
                    @foreach ($familyHistoryMembers as $memberLabel)
                        <th class="text-center">{{ $memberLabel }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($clinicalHistoryConditions as $conditionKey => $conditionLabel)
                    <tr>
                        <td>{{ $conditionLabel }}</td>
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
                                        class="form-check-label visually-hidden"
                                        :for="checkboxId('{{ $conditionKey }}', '{{ $memberKey }}')"
                                    >
                                        {{ $conditionLabel }} – {{ $memberLabel }}
                                    </label>
                                </div>
                                @error("antecedentes_clinicos.$conditionKey.$memberKey")
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="row g-3 mt-3">
        <div class="col-12 col-md-6">
            <label for="antecedentes_clinicos_otros" class="form-label">Otros</label>
            <input
                type="text"
                name="antecedentes_clinicos_otros"
                id="antecedentes_clinicos_otros"
                class="form-control @error('antecedentes_clinicos_otros') is-invalid @enderror"
                maxlength="120"
                value="{{ old('antecedentes_clinicos_otros', $expediente->antecedentes_clinicos_otros ?? '') }}"
            >
            <div class="form-text">Especifica otros antecedentes clínicos relevantes.</div>
            @error('antecedentes_clinicos_otros')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-12">
            <label for="antecedentes_clinicos_observaciones" class="form-label">Observaciones</label>
            <textarea
                name="antecedentes_clinicos_observaciones"
                id="antecedentes_clinicos_observaciones"
                rows="3"
                maxlength="500"
                class="form-control @error('antecedentes_clinicos_observaciones') is-invalid @enderror"
            >{{ old('antecedentes_clinicos_observaciones', $expediente->antecedentes_clinicos_observaciones ?? '') }}</textarea>
            <div class="form-text">Máximo 500 caracteres.</div>
            @error('antecedentes_clinicos_observaciones')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>
