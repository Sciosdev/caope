@php
    $errorBag = "consentimientoUpload-{$consentimiento->id}";
@endphp
<form action="{{ route('consentimientos.upload', $consentimiento) }}" method="post" enctype="multipart/form-data" class="d-flex flex-column gap-2">
    @csrf
    <div class="d-flex flex-wrap gap-2 align-items-start">
        <div class="flex-grow-1">
            <label for="archivo-consentimiento-{{ $consentimiento->id }}" class="form-label small mb-1">Archivo</label>
            <input
                type="file"
                name="archivo"
                id="archivo-consentimiento-{{ $consentimiento->id }}"
                accept=".pdf,.jpg,.jpeg"
                class="form-control form-control-sm @error('archivo', $errorBag) is-invalid @enderror"
                required
            >
            @error('archivo', $errorBag)
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="fecha-consentimiento-{{ $consentimiento->id }}" class="form-label small mb-1">Fecha</label>
            <input
                type="date"
                name="fecha"
                id="fecha-consentimiento-{{ $consentimiento->id }}"
                class="form-control form-control-sm @error('fecha', $errorBag) is-invalid @enderror"
                value="{{ old('fecha', optional($consentimiento->fecha)->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
            >
            @error('fecha', $errorBag)
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="align-self-center">
            <div class="form-check form-switch">
                <input type="hidden" name="aceptado" value="0">
                <input
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                    id="aceptado-consentimiento-{{ $consentimiento->id }}"
                    name="aceptado"
                    value="1"
                    @checked((bool) old('aceptado', $consentimiento->aceptado))
                >
                <label class="form-check-label small" for="aceptado-consentimiento-{{ $consentimiento->id }}">Aceptado</label>
            </div>
            @error('aceptado', $errorBag)
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="align-self-end">
            <button type="submit" class="btn btn-outline-primary btn-sm">Subir</button>
        </div>
    </div>
    <p class="text-muted small mb-0">Formatos permitidos: PDF y JPG.</p>
</form>
