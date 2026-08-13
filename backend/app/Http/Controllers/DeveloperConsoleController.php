<?php

namespace App\Http\Controllers;

use App\Models\DeploymentRun;
use App\Services\DeveloperHealthService;
use App\Services\GitHubDeploymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class DeveloperConsoleController extends Controller
{
    public function index(
        GitHubDeploymentService $github,
        DeveloperHealthService $health,
    ): View {
        $synchronizationWarning = $github->synchronize();

        return view('developer.index', [
            'checks' => $health->run(),
            'deployments' => DeploymentRun::query()
                ->with('requestedBy:id,name,email')
                ->latest()
                ->limit(20)
                ->get(),
            'deploymentConfigurationIssues' => $github->configurationIssues(),
            'synchronizationWarning' => $synchronizationWarning,
            'deploymentRef' => (string) config('developer_console.github.ref', 'main'),
        ]);
    }

    public function deploy(Request $request, GitHubDeploymentService $github): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', 'in:DESPLEGAR'],
        ], [
            'confirmation.in' => 'Escribe DESPLEGAR para confirmar la operación.',
        ]);

        $lock = Cache::lock('developer-console:deployment-dispatch', 15);

        if (! $lock->get()) {
            return back()->withErrors([
                'deployment' => 'Ya se está procesando otra solicitud de despliegue.',
            ]);
        }

        try {
            $github->synchronize();

            $activeDeployment = DeploymentRun::query()
                ->whereIn('status', ['requested', 'queued', 'in_progress', 'waiting'])
                ->where('created_at', '>=', now()->subHours(6))
                ->latest()
                ->first();

            if ($activeDeployment) {
                return back()->withErrors([
                    'deployment' => 'Ya existe un despliegue activo o pendiente de aprobación.',
                ]);
            }

            $deployment = DeploymentRun::query()->create([
                'request_id' => (string) Str::uuid(),
                'requested_by' => $request->user()?->getAuthIdentifier(),
                'ref' => (string) config('developer_console.github.ref', 'main'),
                'status' => 'requested',
            ]);

            try {
                $github->trigger($deployment);
            } catch (Throwable $exception) {
                $deployment->update([
                    'status' => 'failed_to_dispatch',
                    'error_message' => $exception->getMessage(),
                ]);

                report($exception);

                return back()->withErrors([
                    'deployment' => 'No fue posible solicitar el despliegue. Revisa la configuración y el registro de la aplicación.',
                ]);
            }

            return back()->with('status', 'Despliegue solicitado. GitHub validará la versión antes de actualizar producción.');
        } finally {
            $lock->release();
        }
    }
}
