@php
    $editing = isset($user);
    $selectedRoles = collect(old('roles', $editing ? $user->roles->pluck('name')->all() : []))->map(fn ($role) => (string) $role)->all();
@endphp

<div class="mb-3">
    <label for="name" class="form-label">{{ __('Nombre') }}</label>
    <input type="text" id="name" name="name" value="{{ old('name', $editing ? $user->name : '') }}" class="form-control" required>
    @error('name')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="email" class="form-label">{{ __('Correo electrónico') }}</label>
    <input type="email" id="email" name="email" value="{{ old('email', $editing ? $user->email : '') }}" class="form-control" required>
    @error('email')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="row g-3">
    <div class="col-md-6">
        <label for="password" class="form-label">{{ __('Contraseña') }}</label>
        <input type="password" id="password" name="password" class="form-control" {{ $editing ? '' : 'required' }}>
        @if ($editing)
            <small class="text-muted">{{ __('Déjalo en blanco para mantener la contraseña actual.') }}</small>
        @endif
        @error('password')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6">
        <label for="password_confirmation" class="form-label">{{ __('Confirmar contraseña') }}</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" {{ $editing ? '' : 'required' }}>
    </div>
</div>

<div class="mb-3">
    <label for="roles" class="form-label">{{ __('Roles asignados') }}</label>
    <select name="roles[]" id="roles" class="form-select" multiple required>
        @foreach ($roles as $roleValue => $roleLabel)
            <option value="{{ $roleValue }}" @selected(in_array($roleValue, $selectedRoles, true))>
                {{ ucfirst($roleLabel) }}
            </option>
        @endforeach
    </select>
    @error('roles')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
    @error('roles.*')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="carrera" class="form-label">{{ __('Carrera (opcional)') }}</label>
    <input type="text" id="carrera" name="carrera" value="{{ old('carrera', $editing ? $user->carrera : '') }}" class="form-control">
    @error('carrera')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="mb-4">
    <label for="turno" class="form-label">{{ __('Turno (opcional)') }}</label>
    <input type="text" id="turno" name="turno" value="{{ old('turno', $editing ? $user->turno : '') }}" class="form-control">
    @error('turno')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="d-flex justify-content-end gap-2">
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">{{ __('Cancelar') }}</a>
    <button type="submit" class="btn btn-primary">
        {{ $editing ? __('Guardar cambios') : __('Crear usuario') }}
    </button>
</div>
