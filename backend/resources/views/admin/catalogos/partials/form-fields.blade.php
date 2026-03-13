@php
    $nombre = old('nombre', $editing && $item ? $item->nombre : '');
    $activo = old('activo', $editing && $item ? (int) $item->activo : 1);
@endphp

<div class="mb-3">
    <label for="nombre" class="form-label">{{ __('Nombre') }}</label>
    <input type="text" id="nombre" name="nombre" value="{{ $nombre }}" class="form-control" required>
    @error('nombre')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>


@if (in_array($routePrefix, ['admin.catalogos.consultorios', 'admin.catalogos.cubiculos'], true))
    <div class="mb-3">
        <label for="numero" class="form-label">{{ __('Número') }}</label>
        <input type="number" id="numero" name="numero" value="{{ old('numero', $editing && $item ? $item->numero : '') }}" min="1" max="99" class="form-control" required>
        @error('numero')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>
@endif

<div class="form-check form-switch mb-4">
    <input type="hidden" name="activo" value="0">
    <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1" @checked((int) $activo === 1)>
    <label class="form-check-label" for="activo">{{ __('Activo') }}</label>
</div>

<div class="d-flex justify-content-end gap-2">
    <a href="{{ route($routePrefix . '.index') }}" class="btn btn-outline-secondary">{{ __('Cancelar') }}</a>
    <button type="submit" class="btn btn-primary">
        {{ $editing ? __('Guardar cambios') : __('Crear :resource', ['resource' => strtolower($resourceName)]) }}
    </button>
</div>
