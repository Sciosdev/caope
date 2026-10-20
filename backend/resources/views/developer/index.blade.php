<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Inicio') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ __('Consola del desarrollador') }}</li>
    @endsection

    <div class="d-flex flex-column gap-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <h2 class="mb-1">Consola del desarrollador</h2>
                <p class="text-muted mb-0">Diagnóstico técnico y despliegues auditados de CAOPE.</p>
            </div>
            <a href="{{ route('developer.index') }}" class="btn btn-outline-primary btn-sm">Actualizar comprobaciones</a>
        </div>

        @if (session('status'))
            <div class="alert alert-success mb-0">{{ session('status') }}</div>
        @endif

        @if ($errors->has('deployment'))
            <div class="alert alert-danger mb-0">{{ $errors->first('deployment') }}</div>
        @endif

        @if ($errors->has('credentials'))
            <div class="alert alert-danger mb-0">{{ $errors->first('credentials') }}</div>
        @endif

        <div id="deployment-health" class="d-flex flex-column gap-3">
            @include('developer.partials.health')
        </div>

        <div class="card border-danger">
            <div class="card-header bg-danger-subtle d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-1">Desplegar {{ $deploymentTargetLabel }}</h5>
                    <p class="text-muted small mb-0">Rama fija: <code>{{ $deploymentRef }}</code></p>
                </div>
            </div>
            <div class="card-body">
                <p>
                    GitHub ejecutará las pruebas, generará un respaldo y actualizará {{ $deploymentTargetLabel }} mediante el workflow protegido.
                </p>

                @if ($deploymentConfigurationIssues !== [])
                    <div class="alert alert-warning">
                        <strong>Configuración pendiente:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($deploymentConfigurationIssues as $issue)
                                <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div id="deployment-progress" aria-live="polite">
                    @include('developer.partials.progress')
                </div>
                <p id="deployment-refresh-status" class="small text-muted" role="status">El estado se actualiza automáticamente.</p>

                <form method="POST" action="{{ route('developer.deploy') }}" class="row g-3 align-items-end" id="deployment-form">
                    @csrf
                    <div class="col-md-8">
                        <label for="confirmation" class="form-label">Escribe <strong>DESPLEGAR</strong> para confirmar</label>
                        <input
                            id="confirmation"
                            name="confirmation"
                            type="text"
                            autocomplete="off"
                            class="form-control @error('confirmation') is-invalid @enderror"
                            @disabled($deployDisabled)
                        >
                        @error('confirmation')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4 d-grid">
                        <button type="submit" class="btn btn-danger" @disabled($deployDisabled)>
                            Solicitar despliegue
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="deployment-history">
            @include('developer.partials.history')
        </div>

        @if ($canRotateCredentials)
            <details class="card">
                <summary class="card-header fw-semibold">Renovar token de GitHub</summary>
                <div class="card-body">
                    <p class="text-muted">
                        Usa esta opción sólo cuando el token haya vencido o vaya a revocarse. El token anterior no se muestra.
                    </p>
                    <form method="POST" action="{{ route('developer.credentials.rotate') }}" class="row g-3 align-items-end" autocomplete="off">
                        @csrf
                        <div class="col-md-8">
                            <label for="github_token" class="form-label">Token nuevo</label>
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
                        </div>
                        <div class="col-md-4 d-grid">
                            <button type="submit" class="btn btn-outline-primary">Validar y reemplazar</button>
                        </div>
                    </form>
                </div>
            </details>
        @endif
    </div>
    @push('scripts')
        <script>
            (() => {
                const status = document.getElementById('deployment-refresh-status');
                const form = document.getElementById('deployment-form');
                let active = @json($activeDeployment !== null);
                let stopped = false;

                const schedule = () => {
                    if (!stopped) window.setTimeout(refresh, active ? 15000 : 60000);
                };

                async function refresh() {
                    if (document.hidden) {
                        schedule();
                        return;
                    }

                    try {
                        const response = await fetch(@json(route('developer.index')), {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                            cache: 'no-store',
                        });

                        if (response.redirected || response.status === 401 || response.status === 403) {
                            stopped = true;
                            form.querySelector('button[type="submit"]').disabled = true;
                            status.textContent = 'La sesión requiere confirmación. Usa Actualizar comprobaciones para continuar.';
                            return;
                        }
                        if (!response.ok) throw new Error('Estado temporalmente no disponible');

                        const data = await response.json();
                        for (const section of ['health', 'progress', 'history']) {
                            document.getElementById(`deployment-${section}`).innerHTML = data[section];
                        }
                        // Only replace diagnostic fragments. Passwords, CSRF
                        // fields and the typed confirmation stay in the form.
                        form.querySelector('#confirmation').disabled = data.deploy_disabled;
                        form.querySelector('button[type="submit"]').disabled = data.deploy_disabled;
                        active = data.active;
                        status.textContent = `Actualizado a las ${new Date().toLocaleTimeString('es-MX')}.`;
                    } catch (error) {
                        form.querySelector('button[type="submit"]').disabled = true;
                        status.textContent = 'No se pudo actualizar el estado. Se reintentará automáticamente; durante el mantenimiento puede tardar unos minutos.';
                    } finally {
                        schedule();
                    }
                }

                schedule();
            })();
        </script>
    @endpush
</x-app-layout>
