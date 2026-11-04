@php
    $editing = isset($user);
    $selectedRoles = collect(old('roles', old('roles_present') ? [] : ($editing ? $user->roles->pluck('name')->all() : [])))
        ->map(fn ($role) => (string) $role)->all();
    $selectedStrategies = collect(old('estrategia_ids', old('estrategia_ids_present') ? [] : ($editing ? $user->estrategiasCoordinadas->modelKeys() : [])))
        ->map(fn ($id) => (string) $id)->all();
@endphp

<div class="mb-3">
    <label for="name" class="form-label">{{ __('Nombre') }}</label>
    <input type="text" id="name" name="name" value="{{ old('name', $editing ? $user->name : '') }}" class="form-control" autocomplete="off" required>
    @error('name')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="email" class="form-label">{{ __('Correo electrónico') }}</label>
    <input type="email" id="email" name="email" value="{{ old('email', $editing ? $user->email : '') }}" class="form-control" autocomplete="off" required>
    @error('email')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="row g-3">
    <div class="col-md-6">
        <label for="password" class="form-label">{{ __('Contraseña') }}</label>
        <x-password-input id="password" :required="! $editing" />
        @if ($editing)
            <small class="text-muted">{{ __('Déjalo en blanco para mantener la contraseña actual.') }}</small>
        @endif
        @error('password')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6">
        <label for="password_confirmation" class="form-label">{{ __('Confirmar contraseña') }}</label>
        <x-password-input
            id="password_confirmation"
            :required="! $editing"
            :show-label="__('Mostrar confirmación de contraseña')"
            :hide-label="__('Ocultar confirmación de contraseña')"
        />
    </div>
</div>

<fieldset class="mb-3" aria-describedby="roles_help">
    <legend class="form-label fs-6">{{ __('Roles asignados') }}</legend>
    <input type="hidden" name="roles_present" value="1">
    <div id="roles" class="border rounded p-3">
        @foreach ($roles as $roleValue => $roleLabel)
            <div class="form-check">
                <input
                    type="checkbox"
                    name="roles[]"
                    id="role_{{ $roleValue }}"
                    value="{{ $roleValue }}"
                    class="form-check-input"
                    @checked(in_array($roleValue, $selectedRoles, true))
                >
                <label class="form-check-label" for="role_{{ $roleValue }}">{{ ucfirst($roleLabel) }}</label>
            </div>
        @endforeach
    </div>
    <div id="roles_help" class="form-text">
        {{ __('Marca uno o varios roles. Por ejemplo, Coordinador y Estratega. Para agregar otro, conserva marcados los que debe mantener.') }}
    </div>
    @error('roles')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
    @error('roles.*')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</fieldset>

<fieldset class="mb-3" aria-describedby="estrategia_ids_help">
    <legend class="form-label fs-6">{{ __('Estrategias que coordina') }}</legend>
    <input type="hidden" name="estrategia_ids_present" value="1">
    <div id="estrategia_ids" class="border rounded p-3">
        @forelse ($estrategias as $estrategia)
            <div class="form-check">
                <input
                    type="checkbox"
                    name="estrategia_ids[]"
                    id="estrategia_{{ $estrategia->id }}"
                    value="{{ $estrategia->id }}"
                    class="form-check-input"
                    @checked(in_array((string) $estrategia->id, $selectedStrategies, true))
                >
                <label class="form-check-label" for="estrategia_{{ $estrategia->id }}">
                    {{ $estrategia->nombre }}{{ $estrategia->activo ? '' : ' (inactiva)' }}
                </label>
            </div>
        @empty
            <p class="text-muted mb-0">{{ __('No hay estrategias disponibles para asignar.') }}</p>
        @endforelse
    </div>
    <div id="estrategia_ids_help" class="form-text">
        {{ __('Marca todas las estrategias que coordina. Se aplica al rol Coordinador, aunque tenga otros roles. Su acceso como coordinador se limita a las estrategias marcadas. Sin estrategias asignadas no tendrá acceso como coordinador. Al quitar ese rol se eliminan sus estrategias.') }}
    </div>
    @error('estrategia_ids')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
    @error('estrategia_ids.*')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</fieldset>

<div class="mb-3">
    <label for="carrera" class="form-label">{{ __('Carrera (opcional)') }}</label>
    <select id="carrera" name="carrera" class="form-select">
        <option value="">{{ __('Selecciona una carrera') }}</option>
        @foreach ($carreras as $carrera)
            <option value="{{ $carrera }}" @selected(old('carrera', $editing ? $user->carrera : '') === $carrera)>{{ $carrera }}</option>
        @endforeach
    </select>
    @error('carrera')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="mb-4">
    <label for="turno" class="form-label">{{ __('Turno (opcional)') }}</label>
    <select id="turno" name="turno" class="form-select">
        <option value="">{{ __('Selecciona un turno') }}</option>
        @foreach ($turnos as $turno)
            <option value="{{ $turno }}" @selected(old('turno', $editing ? $user->turno : '') === $turno)>{{ $turno }}</option>
        @endforeach
    </select>
    @error('turno')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>


<div class="form-check form-switch mb-4">
    <input type="hidden" name="approved" value="0">
    <input class="form-check-input" type="checkbox" role="switch" id="approved" name="approved" value="1" @checked(old('approved', $editing ? (bool) $user->approved_at : false))>
    <label class="form-check-label" for="approved">{{ __('Usuario aprobado') }}</label>
    @error('approved')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="form-check form-switch mb-4">
    <input type="hidden" name="is_active" value="0">
    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $editing ? $user->is_active : true))>
    <label class="form-check-label" for="is_active">{{ __('Acceso habilitado') }}</label>
    @error('is_active')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="d-flex justify-content-end gap-2">
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">{{ __('Cancelar') }}</a>
    <button type="submit" class="btn btn-primary">
        {{ $editing ? __('Guardar cambios') : __('Crear usuario') }}
    </button>
</div>
