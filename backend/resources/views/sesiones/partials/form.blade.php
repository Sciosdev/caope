<div class="row g-3">
    <div class="col-md-4">
        <label for="fecha" class="form-label">Fecha</label>
        <input type="date" name="fecha" id="fecha" value="{{ old('fecha', optional($sesion->fecha)->format('Y-m-d')) }}"
            class="form-control @error('fecha') is-invalid @enderror">
        @error('fecha')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4">
        <label for="tipo" class="form-label">Tipo de sesión</label>
        <input type="text" name="tipo" id="tipo" maxlength="60" value="{{ old('tipo', $sesion->tipo) }}"
            class="form-control @error('tipo') is-invalid @enderror" placeholder="Ej. Seguimiento">
        @error('tipo')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4">
        <label for="referencia_externa" class="form-label">Referencia externa</label>
        <input type="text" name="referencia_externa" id="referencia_externa" maxlength="120"
            value="{{ old('referencia_externa', $sesion->referencia_externa) }}"
            class="form-control @error('referencia_externa') is-invalid @enderror" placeholder="Opcional">
        @error('referencia_externa')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12">
        <label for="nota" class="form-label">Notas de la sesión</label>
        <textarea name="nota" id="nota" rows="6" class="form-control @error('nota') is-invalid @enderror"
            placeholder="Describe las actividades, acuerdos y observaciones relevantes">{{ old('nota', $sesion->nota) }}</textarea>
        @error('nota')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
