@php
    $latestDeployment = $activeDeployment ?? $deployments->first();
    $installedVersion = collect($checks)->firstWhere('id', 'version');
@endphp

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <span class="text-muted small d-block">Versión instalada</span>
        <code>{{ $installedVersion['summary'] ?? 'No identificada' }}</code>
    </div>
    <div class="col-md-6">
        <span class="text-muted small d-block">Última versión solicitada</span>
        <code>{{ $latestDeployment?->commit_sha ? substr($latestDeployment->commit_sha, 0, 12) : ($latestDeployment ? 'Pendiente de validación en GitHub' : 'Todavía no se ha solicitado una versión') }}</code>
    </div>
</div>

@if ($latestDeployment)
    <div class="alert {{ in_array($latestDeployment->agent_status, ['recovery_required', 'interrupted'], true) ? 'alert-danger' : 'alert-info' }}">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <strong>{{ $latestDeployment->stageLabel() }}</strong>
            <span class="badge {{ $latestDeployment->badgeClass() }}">{{ $latestDeployment->statusLabel() }}</span>
        </div>
        @if (in_array($latestDeployment->agent_status, ['recovery_required', 'interrupted'], true))
            <p class="mb-1">El servidor necesita comprobar la recuperación antes de aceptar otro despliegue. Consulta el diagnóstico del historial.</p>
        @elseif ($activeDeployment)
            <p class="mb-1">Hay un despliegue activo. Espera a que termine antes de solicitar otro.</p>
        @elseif ($latestDeployment->conclusion !== 'success' || in_array($latestDeployment->agent_status, ['failed', 'timed_out'], true))
            <p class="mb-1">El intento terminó. Revisa el diagnóstico y corrige la causa antes de volver a solicitarlo.</p>
        @endif
        @if ($latestDeployment->agent_heartbeat_at)
            <div class="small">Última señal del servidor: {{ $latestDeployment->agent_heartbeat_at->format('d/m/Y H:i:s') }}</div>
        @endif
        <div class="small text-muted text-break">Solicitud: {{ $latestDeployment->request_id }}</div>
    </div>
@endif
