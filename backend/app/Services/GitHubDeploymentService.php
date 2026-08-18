<?php

namespace App\Services;

use App\Models\DeploymentRun;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GitHubDeploymentService
{
    public function __construct(private DeveloperConsoleSettings $settings) {}

    /**
     * @return list<string>
     */
    public function configurationIssues(): array
    {
        $issues = [];

        if ($this->settings->githubToken() === '') {
            $issues[] = 'Falta activar las credenciales de GitHub.';
        }

        $repository = $this->settings->githubRepository();
        if ($repository === '' || substr_count($repository, '/') !== 1) {
            $issues[] = 'El repositorio de GitHub no tiene el formato propietario/repositorio.';
        }

        if ($this->settings->githubWorkflow() === '') {
            $issues[] = 'Falta configurar el workflow de despliegue.';
        }

        if ($this->settings->githubRef() === '') {
            $issues[] = 'Falta configurar la rama de despliegue.';
        }

        return $issues;
    }

    public function validateActivationToken(string $token): int
    {
        $token = trim($token);

        if ($token === '') {
            throw new RuntimeException('El token de GitHub es obligatorio.');
        }

        $workflowEndpoint = '/repos/'.DeveloperConsoleSettings::PRODUCTION_REPOSITORY
            .'/actions/workflows/'.rawurlencode(DeveloperConsoleSettings::PRODUCTION_WORKFLOW);
        $client = $this->client($token, 'https://api.github.com');
        $workflow = $client->get($workflowEndpoint);

        if (! $workflow->successful()
            || (string) $workflow->json('state') !== 'active'
            || (int) $workflow->json('id') < 1
            || ! str_ends_with((string) $workflow->json('path'), '/'.DeveloperConsoleSettings::PRODUCTION_WORKFLOW)) {
            throw new RuntimeException('El token no permite consultar el workflow de producción.');
        }

        $permissionCheck = $client->post($workflowEndpoint.'/dispatches', [
            'ref' => 'codex-credential-check-'.Str::uuid(),
            'inputs' => [
                'ref' => DeveloperConsoleSettings::PRODUCTION_REF,
                'request_id' => 'manual',
            ],
        ]);

        // GitHub returns 422 for a non-existent ref only after authorizing
        // workflow dispatch. A read-only token is rejected with 403.
        if ($permissionCheck->status() !== 422) {
            throw new RuntimeException('El token necesita permiso Actions: lectura y escritura.');
        }

        return (int) $workflow->json('id');
    }

    public function trigger(DeploymentRun $deployment): void
    {
        $issues = $this->configurationIssues();

        if ($issues !== []) {
            throw new RuntimeException(implode(' ', $issues));
        }

        $response = $this->client()->post($this->workflowEndpoint('/dispatches'), [
            'ref' => $deployment->ref,
            'inputs' => [
                'ref' => $deployment->ref,
                'request_id' => $deployment->request_id,
            ],
        ]);

        if ($response->status() !== 204) {
            Log::error('GitHub rejected a deployment dispatch.', [
                'deployment_id' => $deployment->getKey(),
                'status' => $response->status(),
            ]);

            throw new RuntimeException('GitHub no aceptó la solicitud de despliegue.');
        }
    }

    public function synchronize(): ?string
    {
        if ($this->configurationIssues() !== []) {
            return null;
        }

        try {
            $response = $this->client()->get($this->workflowEndpoint('/runs'), [
                'event' => 'workflow_dispatch',
                'per_page' => 30,
            ]);

            if (! $response->successful()) {
                Log::warning('Unable to synchronize GitHub deployment runs.', [
                    'status' => $response->status(),
                ]);

                return 'GitHub no permitió actualizar el estado de los despliegues.';
            }

            foreach ((array) data_get($response->json(), 'workflow_runs', []) as $run) {
                $displayTitle = (string) ($run['display_title'] ?? '');

                if (preg_match('/\[([0-9a-f-]{36})\]/i', $displayTitle, $matches) !== 1) {
                    continue;
                }

                $deployment = DeploymentRun::query()
                    ->where('request_id', $matches[1])
                    ->first();

                if (! $deployment) {
                    continue;
                }

                $conclusion = $run['conclusion'] ?? null;

                $deployment->update([
                    'status' => (string) ($run['status'] ?? $deployment->status),
                    'conclusion' => $conclusion,
                    'workflow_run_id' => isset($run['id']) ? (int) $run['id'] : null,
                    'workflow_url' => $run['html_url'] ?? null,
                    'commit_sha' => $run['head_sha'] ?? null,
                    'error_message' => $conclusion === 'success'
                        ? null
                        : $deployment->error_message,
                ]);
            }

            return null;
        } catch (Throwable $exception) {
            report($exception);

            return 'No fue posible consultar GitHub en este momento.';
        }
    }

    /**
     * @return array{workflow_url: string, commit_sha: string}
     */
    public function attestProductionRun(
        DeploymentRun $deployment,
        string $sha,
        int $runId,
        int $runAttempt
    ): array {
        $workflowId = $this->settings->githubWorkflowId();

        if ($workflowId === null) {
            throw new RuntimeException('La activación web no contiene el identificador del workflow.', 503);
        }

        $runResponse = $this->client()->get(
            '/repos/'.$this->settings->githubRepository().'/actions/runs/'.$runId
        );

        if (! $runResponse->successful()) {
            throw new RuntimeException('GitHub no permitió comprobar la ejecución de despliegue.', 503);
        }

        $run = (array) $runResponse->json();
        $expectedTitle = sprintf(
            'Deploy production %s [%s]',
            DeveloperConsoleSettings::PRODUCTION_REF,
            $deployment->request_id
        );

        $validRun = (int) ($run['id'] ?? 0) === $runId
            && (int) ($run['workflow_id'] ?? 0) === $workflowId
            && (string) ($run['event'] ?? '') === 'workflow_dispatch'
            && (string) ($run['head_branch'] ?? '') === DeveloperConsoleSettings::PRODUCTION_REF
            && hash_equals(strtolower($sha), strtolower((string) ($run['head_sha'] ?? '')))
            && (int) ($run['run_attempt'] ?? 0) === $runAttempt
            && (string) ($run['display_title'] ?? '') === $expectedTitle
            && (string) ($run['path'] ?? '') === '.github/workflows/'.DeveloperConsoleSettings::PRODUCTION_WORKFLOW
            && in_array((string) ($run['status'] ?? ''), ['queued', 'in_progress'], true)
            && ($run['conclusion'] ?? null) === null;

        if (! $validRun) {
            throw new RuntimeException('La ejecución de GitHub no coincide con la solicitud auditada.', 403);
        }

        $jobsResponse = $this->client()->get(
            '/repos/'.$this->settings->githubRepository().'/actions/runs/'.$runId
                .'/attempts/'.$runAttempt.'/jobs',
            ['filter' => 'latest', 'per_page' => 100]
        );

        if (! $jobsResponse->successful()) {
            throw new RuntimeException('GitHub no permitió comprobar las pruebas del despliegue.', 503);
        }

        $jobs = collect((array) $jobsResponse->json('jobs'));
        $validationPassed = $jobs->contains(fn (array $job): bool => ($job['name'] ?? null) === 'Validate release'
            && ($job['status'] ?? null) === 'completed'
            && ($job['conclusion'] ?? null) === 'success');
        $deploymentIsRunning = $jobs->contains(fn (array $job): bool => ($job['name'] ?? null) === 'Deploy to production'
            && in_array(($job['status'] ?? null), ['queued', 'in_progress'], true)
            && ($job['conclusion'] ?? null) === null);

        if (! $validationPassed || ! $deploymentIsRunning) {
            throw new RuntimeException('GitHub todavía no acredita una validación exitosa de esta versión.', 403);
        }

        return [
            'workflow_url' => (string) ($run['html_url'] ?? ''),
            'commit_sha' => strtolower($sha),
        ];
    }

    private function client(?string $token = null, ?string $apiUrl = null): PendingRequest
    {
        return Http::baseUrl($apiUrl ?? $this->settings->githubApiUrl())
            ->withToken($token ?? $this->settings->githubToken())
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'CAOPE-Developer-Console',
            ])
            ->connectTimeout(5)
            ->timeout(15);
    }

    private function workflowEndpoint(string $suffix): string
    {
        $repository = trim($this->settings->githubRepository(), '/');
        $workflow = rawurlencode($this->settings->githubWorkflow());

        return "/repos/{$repository}/actions/workflows/{$workflow}{$suffix}";
    }
}
