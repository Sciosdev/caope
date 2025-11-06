<div class="mt-4">
    <h6 class="mb-3">Antecedentes familiares</h6>
    <p class="text-muted small">Selecciona los familiares con antecedentes relevantes y agrega observaciones si es necesario.</p>

    <div class="row g-3">
        @php
            $familyHistory = old('antecedentes_familiares', $expediente->antecedentes_familiares ?? \App\Models\Expediente::defaultFamilyHistory());
        @endphp
        @foreach ($familyHistoryMembers as $memberKey => $memberLabel)
            <div class="col-12 col-md-4">
                <div class="form-check">
                    <input type="hidden" name="antecedentes_familiares[{{ $memberKey }}]" value="0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="antecedentes_familiares[{{ $memberKey }}]"
                        id="antecedente_{{ $memberKey }}"
                        value="1"
                        @checked((bool) data_get($familyHistory, $memberKey, false))
                    >
                    <label class="form-check-label" for="antecedente_{{ $memberKey }}">
                        {{ $memberLabel }}
                    </label>
                </div>
                @error("antecedentes_familiares.$memberKey")
                    <div class="text-danger small">{{ $message }}</div>
                @enderror
            </div>
        @endforeach
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
