<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Inicio') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ __('Consola del desarrollador') }}</li>
    @endsection

    @php
        $errorCount = collect($checks)->where('status', 'error')->count();
        $warningCount = collect($checks)->where('status', 'warning')->count();
        $overallStatus = $errorCount > 0 ? 'error' : ($warningCount > 0 ? 'warning' : 'ok');
        $overallClasses = [
            'ok' => ['alert-success', 'Operación normal'],
            'warning' => ['alert-warning', 'Requiere atención'],
            'error' => ['alert-danger', 'Se detectaron errores'],
        ][$overallStatus];
        $activeDeployment = $deployments->first(fn ($deployment) => $deployment->isActive());
        $deployDisabled = $deploymentConfigurationIssues !== [] || $activeDeployment !== null;
    @endphp

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

        @if ($synchronizationWarning)
            <div class="alert alert-warning mb-0">{{ $synchronizationWarning }}</div>
        @endif

        <div class="alert {{ $overallClasses[0] }} d-flex flex-wrap justify-content-between align-items-center gap-2 mb-0">
            <strong>{{ $overallClasses[1] }}</strong>
            <span>{{ count($checks) }} comprobaciones · {{ $errorCount }} errores · {{ $warningCount }} advertencias</span>
        </div>

        <div class="row g-3">
            @foreach ($checks as $check)
                @php
                    $statusPresentation = [
                        'ok' => ['text-bg-success', 'Correcto'],
                        'warning' => ['text-bg-warning', 'Advertencia'],
                        'error' => ['text-bg-danger', 'Error'],
                    ][$check['status']];
                @endphp
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                <h5 class="card-title mb-0">{{ $check['label'] }}</h5>
                                <span class="badge {{ $statusPresentation[0] }}">{{ $statusPresentation[1] }}</span>
                            </div>
                            <p class="mb-1">{{ $check['summary'] }}</p>
                            @if ($check['details'])
                                <p class="text-muted small mb-0 text-break">{{ $check['details'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card border-danger">
            <div class="card-header bg-danger-subtle d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-1">Desplegar producción</h5>
                    <p class="text-muted small mb-0">Rama fija: <code>{{ $deploymentRef }}</code></p>
                </div>
                @if ($activeDeployment)
                    <span class="badge {{ $activeDeployment->badgeClass() }}">{{ $activeDeployment->statusLabel() }}</span>
                @endif
            </div>
            <div class="card-body">
                <p>
                    GitHub ejecutará las pruebas, generará un respaldo y actualizará el servidor mediante el workflow protegido.
                    Esta pantalla no ejecuta comandos arbitrarios en el servidor.
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

                @if ($activeDeployment)
                    <div class="alert alert-info">Hay un despliegue activo. Espera a que termine antes de solicitar otro.</div>
                @endif

                <form method="POST" action="{{ route('developer.deploy') }}" class="row g-3 align-items-end">
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

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Historial de despliegues</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Solicitó</th>
                                <th>Referencia</th>
                                <th>Commit</th>
                                <th>Estado</th>
                                <th class="text-end">Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($deployments as $deployment)
                                <tr>
                                    <td>{{ $deployment->created_at?->format('d/m/Y H:i:s') }}</td>
                                    <td>
                                        {{ $deployment->requestedBy?->name ?? 'Usuario eliminado' }}
                                        @if ($deployment->requestedBy?->email)
                                            <div class="text-muted small">{{ $deployment->requestedBy->email }}</div>
                                        @endif
                                    </td>
                                    <td><code>{{ $deployment->ref }}</code></td>
                                    <td><code>{{ $deployment->commit_sha ? substr($deployment->commit_sha, 0, 8) : '—' }}</code></td>
                                    <td><span class="badge {{ $deployment->badgeClass() }}">{{ $deployment->statusLabel() }}</span></td>
                                    <td class="text-end">
                                        @if ($deployment->workflow_url)
                                            <a href="{{ $deployment->workflow_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm">
                                                Ver en GitHub
                                            </a>
                                        @else
                                            <span class="text-muted">Pendiente</span>
                                        @endif
                                    </td>
                                </tr>
                                @if ($deployment->error_message)
                                    <tr>
                                        <td colspan="6" class="small text-danger bg-danger-subtle">{{ $deployment->error_message }}</td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Todavía no hay despliegues registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
