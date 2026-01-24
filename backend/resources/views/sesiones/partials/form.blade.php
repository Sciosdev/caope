<div class="row g-3">
    <div class="col-md-6">
        <label for="fecha" class="form-label">Fecha</label>
        <input type="date" name="fecha" id="fecha" value="{{ old('fecha', optional($sesion->fecha)->format('Y-m-d')) }}"
            class="form-control @error('fecha') is-invalid @enderror">
        @error('fecha')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6">
        <label for="hora_atencion" class="form-label">Hora de atención</label>
        <input type="time" name="hora_atencion" id="hora_atencion" maxlength="5"
            value="{{ old('hora_atencion', $sesion->hora_atencion ? \Illuminate\Support\Carbon::parse($sesion->hora_atencion)->format('H:i') : '') }}"
            class="form-control @error('hora_atencion') is-invalid @enderror">
        @error('hora_atencion')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12">
        <label for="estrategia" class="form-label">Estrategia</label>
        <textarea name="estrategia" id="estrategia" maxlength="1000"
            class="form-control @error('estrategia') is-invalid @enderror" rows="3"
            placeholder="Describe la estrategia acordada">{{ old('estrategia', $sesion->estrategia) }}</textarea>
        @error('estrategia')
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
    <div class="col-12 border-top pt-3">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="interconsulta" class="form-label">Interconsulta</label>
                <input type="text" name="interconsulta" id="interconsulta" maxlength="120"
                    value="{{ old('interconsulta', $sesion->interconsulta) }}"
                    class="form-control @error('interconsulta') is-invalid @enderror" placeholder="Opcional">
                @error('interconsulta')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="especialidad_referida" class="form-label">Especialidad referida</label>
                <input type="text" name="especialidad_referida" id="especialidad_referida" maxlength="120"
                    value="{{ old('especialidad_referida', $sesion->especialidad_referida) }}"
                    class="form-control @error('especialidad_referida') is-invalid @enderror" placeholder="Opcional">
                @error('especialidad_referida')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="clinica" class="form-label">Clínica donde se realizó el tratamiento</label>
                <select name="clinica" id="clinica"
                    class="form-select @error('clinica') is-invalid @enderror">
                    @php
                        $clinicaSeleccionada = old('clinica', $sesion->clinica ?? 'Caope');
                    @endphp
                    <option value="Caope" @selected($clinicaSeleccionada === 'Caope')>Caope</option>
                    <option value="Otra" @selected($clinicaSeleccionada === 'Otra')>Otra</option>
                </select>
                @error('clinica')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="nombre_facilitador" class="form-label">Nombre del facilitador</label>
                <input type="text" name="nombre_facilitador" id="nombre_facilitador" maxlength="120"
                    value="{{ old('nombre_facilitador', $sesion->nombre_facilitador ?? $expediente->alumno?->name) }}"
                    class="form-control @error('nombre_facilitador') is-invalid @enderror" placeholder="Opcional" readonly>
                @error('nombre_facilitador')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="autorizacion_estratega" class="form-label">Autorización del responsable académico (Estratega)</label>
                <input type="text" name="autorizacion_estratega" id="autorizacion_estratega" maxlength="120"
                    value="{{ old('autorizacion_estratega', $sesion->autorizacion_estratega) }}"
                    class="form-control @error('autorizacion_estratega') is-invalid @enderror" placeholder="Opcional">
                @error('autorizacion_estratega')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-12">
                <label for="motivo_referencia" class="form-label">Motivo de referencia</label>
                <textarea name="motivo_referencia" id="motivo_referencia" maxlength="1000" rows="3"
                    class="form-control @error('motivo_referencia') is-invalid @enderror"
                    placeholder="Detalla el motivo de la referencia">{{ old('motivo_referencia', $sesion->motivo_referencia) }}</textarea>
                @error('motivo_referencia')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
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
