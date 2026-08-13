<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
        <li class="breadcrumb-item active" aria-current="page">Activar despliegues</li>
    @endsection

    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card border-primary">
                <div class="card-header bg-primary-subtle">
                    <h2 class="h4 mb-1">Activar despliegues</h2>
                    <p class="text-muted mb-0">Configuración única, sin terminal ni cambios en el servidor.</p>
                </div>
                <div class="card-body">
                    @if ($recovering)
                        <div class="alert alert-warning">
                            <strong>Recuperación:</strong> la configuración anterior no puede descifrarse.
                            El token nuevo reemplazará el archivo dañado; esta operación quedará asociada a tu cuenta.
                        </div>
                    @endif

                    @if ($errors->has('activation'))
                        <div class="alert alert-danger">{{ $errors->first('activation') }}</div>
                    @endif

                    <ol class="mb-4">
                        <li>Crea un token fine-grained para <strong>Sciosdev/caope</strong>.</li>
                        <li>Concede <strong>Actions: Read and write</strong>. Metadata se agrega automáticamente.</li>
                        <li>Pégalo aquí y elige la cuenta que realizará despliegues.</li>
                    </ol>

                    <p>
                        <a
                            href="https://github.com/settings/personal-access-tokens/new"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-outline-secondary btn-sm"
                        >Abrir creación de token en GitHub</a>
                    </p>

                    <form method="POST" action="{{ route('developer.activation.store') }}" autocomplete="off">
                        @csrf

                        <div class="mb-3">
                            <label for="developer_user_id" class="form-label">Cuenta desarrolladora</label>
                            <select
                                id="developer_user_id"
                                name="developer_user_id"
                                class="form-select @error('developer_user_id') is-invalid @enderror"
                                required
                            >
                                @foreach ($users as $user)
                                    <option
                                        value="{{ $user->id }}"
                                        @selected((int) old('developer_user_id', auth()->id()) === $user->id)
                                    >
                                        {{ $user->name }} · {{ $user->email }}
                                    </option>
                                @endforeach
                            </select>
                            @error('developer_user_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="github_token" class="form-label">Token de GitHub</label>
                            <input
                                id="github_token"
                                name="github_token"
                                type="password"
                                class="form-control @error('github_token') is-invalid @enderror"
                                autocomplete="new-password"
                                required
                            >
                            @error('github_token')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Se validará y se guardará cifrado con la llave de CAOPE. No volverá a mostrarse.</div>
                        </div>

                        <div class="alert alert-info">
                            Al activar no se solicitan host, SSH, rutas, Composer ni secretos del servidor de FESI.
                        </div>

                        @if ($recovering)
                            <div class="form-check mb-3">
                                <input
                                    id="confirm_recovery"
                                    name="confirm_recovery"
                                    type="checkbox"
                                    value="1"
                                    class="form-check-input @error('confirm_recovery') is-invalid @enderror"
                                    required
                                >
                                <label for="confirm_recovery" class="form-check-label">
                                    Confirmo que deseo reemplazar la configuración cifrada anterior.
                                </label>
                                @error('confirm_recovery')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Validar y activar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
