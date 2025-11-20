<div class="mt-4">
    <h6 class="mb-3">Diagnóstico Médico Odontológico</h6>
    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <label for="diagnostico" class="form-label">Diagnóstico Médico Odontológico</label>
            <textarea
                id="diagnostico"
                class="form-control"
                rows="3"
                readonly
                placeholder="Sin información registrada."
            >{{ $expediente->diagnostico ?? '' }}</textarea>
        </div>
        <div class="col-12 col-lg-6">
            <label for="dsm_tr" class="form-label">DSM y TR</label>
            <textarea
                id="dsm_tr"
                class="form-control"
                rows="3"
                readonly
                placeholder="Sin información registrada."
            >{{ $expediente->dsm_tr ?? '' }}</textarea>
        </div>
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
