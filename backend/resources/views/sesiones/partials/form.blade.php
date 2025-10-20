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
        <input type="hidden" name="nota" id="nota" value="{{ old('nota', $sesion->nota ?? '') }}">
        <trix-editor input="nota" class="form-control @error('nota') is-invalid @enderror"
            placeholder="Describe las actividades, acuerdos y observaciones relevantes"></trix-editor>
        @error('nota')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12">
        <label for="adjuntos" class="form-label">Adjuntos</label>
        <input type="file" name="adjuntos[]" id="adjuntos"
            class="form-control @error('adjuntos') is-invalid @enderror @error('adjuntos.*') is-invalid @enderror" multiple>
        <div class="form-text">Puedes adjuntar archivos relevantes a la sesión (PDF, imágenes, documentos).</div>
        @error('adjuntos')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        @error('adjuntos.*')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
    @if ($sesion->exists && $sesion->adjuntos->isNotEmpty())
        <div class="col-12">
            <label class="form-label">Adjuntos cargados</label>
            <ul class="list-group list-group-flush">
                @foreach ($sesion->adjuntos as $adjunto)
                    <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <a href="{{ $adjunto->url }}" target="_blank" rel="noopener"
                                class="fw-semibold">{{ $adjunto->nombre_original }}</a>
                            <div class="text-muted small">
                                {{ number_format($adjunto->tamano / 1024, 1) }} KB ·
                                {{ $adjunto->subidoPor?->name ?? 'Desconocido' }} ·
                                {{ optional($adjunto->created_at)->format('Y-m-d H:i') }}
                            </div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="{{ $adjunto->id }}"
                                id="adjunto-eliminar-{{ $adjunto->id }}" name="adjuntos_eliminar[]"
                                @checked(in_array($adjunto->id, old('adjuntos_eliminar', [])))>
                            <label class="form-check-label" for="adjunto-eliminar-{{ $adjunto->id }}">Eliminar</label>
                        </div>
                    </li>
                @endforeach
            </ul>
            @error('adjuntos_eliminar')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            @error('adjuntos_eliminar.*')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    @endif
</div>
