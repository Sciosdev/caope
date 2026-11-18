<?php

namespace App\Http\Controllers;

use App\Models\DeploymentRun;
use App\Services\DeploymentAgentState;
use App\Services\DeveloperConsoleSettings;
use App\Services\DeveloperHealthService;
use App\Services\GitHubDeploymentService;
use App\Services\ProductionSecurityAudit;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class DeveloperConsoleController extends Controller
{
    public function index(
        Request $request,
        GitHubDeploymentService $github,
        DeveloperHealthService $health,
        DeveloperConsoleSettings $settings,
        DeploymentAgentState $agentState,
    ): View|JsonResponse {
        $agentState->synchronizeResult();
        $synchronizationWarning = $github->synchronize();
        $checks = $health->run();
        $activeDeployment = $this->activeDeployment();
        $configurationIssues = $github->configurationIssues();
        $deployDisabled = $configurationIssues !== [] || $activeDeployment !== null
            || collect($checks)->contains('status', 'error');

        $data = [
            'checks' => $checks,
            'deployments' => DeploymentRun::query()
                ->with('requestedBy:id,name,email')
                ->latest()
                ->limit(20)
                ->get(),
            'deploymentConfigurationIssues' => $configurationIssues,
            'synchronizationWarning' => $synchronizationWarning,
            'deploymentRef' => $settings->githubRef(),
            'deploymentTargetLabel' => $settings->targetLabel(),
            'canRotateCredentials' => $settings->hasEncryptedSettings(),
            'activeDeployment' => $activeDeployment,
            'deployDisabled' => $deployDisabled,
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'health' => view('developer.partials.health', $data)->render(),
                'progress' => view('developer.partials.progress', $data)->render(),
                'history' => view('developer.partials.history', $data)->render(),
                'deploy_disabled' => $deployDisabled,
                'active' => $activeDeployment !== null,
            ])->withHeaders(['Cache-Control' => 'no-store, private']);
        }

        return view('developer.index', $data);
    }

    public function deploy(
        Request $request,
        GitHubDeploymentService $github,
        DeveloperConsoleSettings $settings,
        ProductionSecurityAudit $securityAudit,
        DeploymentAgentState $agentState,
    ): RedirectResponse {
        $request->validate([
            'confirmation' => ['required', 'in:DESPLEGAR'],
        ], [
            'confirmation.in' => 'Escribe DESPLEGAR para confirmar la operación.',
        ]);

        $securityProfile = $securityAudit->profileForCurrentEnvironment();
        $securityErrors = $securityAudit->errors($securityProfile);

        if ($securityErrors !== []) {
            $labels = implode(', ', array_column($securityErrors, 'label'));

            return back()->withErrors([
                'deployment' => "No se solicitó el despliegue porque falló la configuración segura: {$labels}.",
            ]);
        }

        $lock = Cache::lock('developer-console:deployment-dispatch', 300);

        if (! $lock->get()) {
            return back()->withErrors([
                'deployment' => 'Ya se está procesando otra solicitud de despliegue.',
            ]);
        }

        try {
            $agentState->synchronizeResult();
            $github->synchronize();

            $activeDeployment = $this->activeDeployment();

            if ($activeDeployment) {
                return back()->withErrors([
                    'deployment' => 'Ya existe un despliegue activo o pendiente de aprobación.',
                ]);
            }

            $deployment = DeploymentRun::query()->create([
                'request_id' => (string) Str::uuid(),
                'requested_by' => $request->user()?->getAuthIdentifier(),
                'ref' => $settings->githubRef(),
                'status' => 'requested',
            ]);

            try {
                $github->trigger($deployment);
            } catch (Throwable $exception) {
                $uncertain = $exception instanceof ConnectionException
                    || ($exception instanceof RuntimeException && $exception->getCode() >= 500);
                $deployment->update([
                    'status' => $uncertain ? 'requested' : 'failed_to_dispatch',
                    'error_message' => $uncertain
                        ? 'No se pudo confirmar la respuesta de GitHub. Se comprobará si recibió la solicitud antes de permitir otro intento.'
                        : $exception->getMessage(),
                ]);

                report($exception);

                return back()->withErrors([
                    'deployment' => $uncertain
                        ? 'GitHub no confirmó la respuesta. Espera a que la consola compruebe la solicitud para evitar duplicados.'
                        : 'No fue posible solicitar el despliegue. Revisa la configuración y el registro de la aplicación.',
                ]);
            }

            $target = $settings->targetLabel();

            return back()->with('status', "Despliegue solicitado. GitHub validará la versión antes de actualizar {$target}.");
        } finally {
            $lock->release();
        }
    }

    private function activeDeployment(): ?DeploymentRun
    {
        return DeploymentRun::query()->blocking()->latest()->get()
            ->first(fn (DeploymentRun $deployment): bool => $deployment->isActive());
    }
}
