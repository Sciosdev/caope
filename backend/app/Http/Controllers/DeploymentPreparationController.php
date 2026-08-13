<?php

namespace App\Http\Controllers;

use App\Models\DeploymentRun;
use App\Services\DeveloperConsoleSettings;
use App\Services\GitHubDeploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class DeploymentPreparationController extends Controller
{
    public function __invoke(
        Request $request,
        DeveloperConsoleSettings $settings,
        GitHubDeploymentService $github,
    ): JsonResponse {
        if (! $settings->hasEncryptedSettings()) {
            $legacyToken = $settings->deployWebhookToken();

            if ($legacyToken !== '') {
                $lock = Cache::lock('developer-console:deployment-marker', 30);

                if (! $lock->get()) {
                    return response()->json(['message' => 'La autorización ya está siendo comprobada.'], 409);
                }

                try {
                    return $this->authorizeLegacyDeployment($request, $legacyToken);
                } finally {
                    $lock->release();
                }
            }
        }

        abort_unless($settings->hasEncryptedSettings() && $settings->enabled(), 404);

        $validated = $request->validate([
            'sha' => ['required', 'regex:/^[a-f0-9]{40}$/i'],
            'request_id' => ['required', 'uuid'],
            'run_id' => ['required', 'integer', 'min:1'],
            'run_attempt' => ['required', 'integer', 'min:1'],
        ]);

        $requestId = strtolower((string) $validated['request_id']);
        $runId = (int) $validated['run_id'];
        $runAttempt = (int) $validated['run_attempt'];
        $sha = strtolower((string) $validated['sha']);
        $lock = Cache::lock('developer-console:deployment-marker', 30);

        if (! $lock->get()) {
            return response()->json(['message' => 'La autorización ya está siendo comprobada.'], 409);
        }

        try {
            $deployment = DeploymentRun::query()
                ->where('request_id', $requestId)
                ->where('ref', DeveloperConsoleSettings::PRODUCTION_REF)
                ->whereIn('status', ['requested', 'queued', 'in_progress', 'waiting'])
                ->where('created_at', '>=', now()->subHours(2))
                ->first();

            abort_unless($deployment, 403);
            abort_if($deployment->workflow_run_id !== null && $deployment->workflow_run_id !== $runId, 403);

            try {
                $attestation = $github->attestProductionRun($deployment, $sha, $runId, $runAttempt);
            } catch (RuntimeException $exception) {
                report($exception);

                return response()->json([
                    'message' => 'No fue posible acreditar esta ejecución de GitHub.',
                ], $exception->getCode() === 503 ? 503 : 403);
            } catch (Throwable $exception) {
                report($exception);

                return response()->json([
                    'message' => 'GitHub no está disponible para acreditar el despliegue.',
                ], 503);
            }

            try {
                DB::transaction(function () use ($deployment, $runId, $attestation, $sha, $requestId): void {
                    $locked = DeploymentRun::query()->lockForUpdate()->findOrFail($deployment->getKey());

                    abort_unless($locked->isActive(), 409);
                    abort_if($locked->workflow_run_id !== null && $locked->workflow_run_id !== $runId, 409);

                    $locked->update([
                        'status' => 'in_progress',
                        'workflow_run_id' => $runId,
                        'workflow_url' => $attestation['workflow_url'],
                        'commit_sha' => $attestation['commit_sha'],
                        'error_message' => null,
                    ]);

                    if (! $this->versionIsAlreadyPublished($sha, $requestId)) {
                        $this->writeMarker($sha, $requestId);
                    }
                });
            } catch (Throwable $exception) {
                $this->deleteMarkerIfOwnedBy($requestId);
                report($exception);

                return response()->json([
                    'message' => 'No fue posible registrar la autorización de despliegue.',
                ], 503);
            }

            return $this->accepted();
        } finally {
            $lock->release();
        }
    }

    private function authorizeLegacyDeployment(Request $request, string $configuredToken): JsonResponse
    {
        $providedToken = (string) $request->bearerToken();
        abort_unless($providedToken !== '' && hash_equals($configuredToken, $providedToken), 403);

        $validated = $request->validate([
            'sha' => ['required', 'regex:/^[a-f0-9]{40}$/i'],
            'request_id' => ['required', 'string', 'max:100'],
        ]);

        $this->writeMarker(
            strtolower((string) $validated['sha']),
            (string) $validated['request_id'],
        );

        return $this->accepted();
    }

    private function writeMarker(string $sha, string $requestId): void
    {
        $markerPath = storage_path('app/deployment/expected.json');
        File::ensureDirectoryExists(dirname($markerPath));
        File::replace($markerPath, json_encode([
            'sha' => $sha,
            'request_id' => $requestId,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ], JSON_THROW_ON_ERROR), 0600);
        @chmod($markerPath, 0600);
    }

    private function accepted(): JsonResponse
    {
        return response()->json(['accepted' => true], 202)
            ->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    private function versionIsAlreadyPublished(string $sha, string $requestId): bool
    {
        $versionPath = storage_path('app/deployment/version.json');

        if (! is_readable($versionPath)) {
            return false;
        }

        try {
            $version = json_decode((string) File::get($versionPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        return is_array($version)
            && hash_equals($sha, strtolower((string) ($version['sha'] ?? '')))
            && hash_equals($requestId, strtolower((string) ($version['request_id'] ?? '')));
    }

    private function deleteMarkerIfOwnedBy(string $requestId): void
    {
        $markerPath = storage_path('app/deployment/expected.json');

        try {
            $marker = json_decode((string) File::get($markerPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return;
        }

        if (is_array($marker)
            && hash_equals($requestId, strtolower((string) ($marker['request_id'] ?? '')))) {
            File::delete($markerPath);
        }
    }
}
