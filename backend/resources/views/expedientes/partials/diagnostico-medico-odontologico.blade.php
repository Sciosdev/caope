@php($mostrarDSM = $mostrarDSM ?? true)

<div class="mt-4">
    <h6 class="mb-3">Motivo de Consulta y Posibles Diagnosticos</h6>
    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <label for="diagnostico" class="form-label">Motivo de Consulta y Posibles Diagnosticos</label>
            <textarea
                id="diagnostico"
                class="form-control"
                rows="3"
                readonly
                placeholder="Sin información registrada."
            >{{ $expediente->diagnostico ?? '' }}</textarea>
        </div>
        @if ($mostrarDSM)
            <div class="col-12 col-lg-6">
                <label for="dsm_tr" class="form-label">Posibles Diagnosticos</label>
                <textarea
                    id="dsm_tr"
                    class="form-control"
                    rows="3"
                    readonly
                    placeholder="Sin información registrada."
                >{{ $expediente->dsm_tr ?? '' }}</textarea>
            </div>
        @endif
        <div class="col-12">
            <label for="observaciones_relevantes" class="form-label">Observaciones relevantes</label>
            <textarea
                id="observaciones_relevantes"
                class="form-control"
                rows="3"
                readonly
                placeholder="Sin información registrada."
            >{{ $expediente->observaciones_relevantes ?? '' }}</textarea>
        </div>
    </div>
</div>
