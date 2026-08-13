<?php

namespace App\Services;

use App\Models\DeploymentRun;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GitHubDeploymentService
{
    /**
     * @return list<string>
     */
    public function configurationIssues(): array
    {
        $issues = [];

        if (trim((string) config('developer_console.github.token')) === '') {
            $issues[] = 'Falta DEVELOPER_CONSOLE_GITHUB_TOKEN.';
        }

        $repository = trim((string) config('developer_console.github.repository'));
        if ($repository === '' || substr_count($repository, '/') !== 1) {
            $issues[] = 'El repositorio de GitHub no tiene el formato propietario/repositorio.';
        }

        if (trim((string) config('developer_console.github.workflow')) === '') {
            $issues[] = 'Falta configurar el workflow de despliegue.';
        }

        if (trim((string) config('developer_console.github.ref')) === '') {
            $issues[] = 'Falta configurar la rama de despliegue.';
        }

        return $issues;
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
                'response' => mb_substr($response->body(), 0, 1000),
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
                    'response' => mb_substr($response->body(), 0, 1000),
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

                $deployment->update([
                    'status' => (string) ($run['status'] ?? $deployment->status),
                    'conclusion' => $run['conclusion'] ?? null,
                    'workflow_run_id' => isset($run['id']) ? (int) $run['id'] : null,
                    'workflow_url' => $run['html_url'] ?? null,
                    'commit_sha' => $run['head_sha'] ?? null,
                    'error_message' => null,
                ]);
            }

            return null;
        } catch (Throwable $exception) {
            report($exception);

            return 'No fue posible consultar GitHub en este momento.';
        }
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('developer_console.github.api_url'))
            ->withToken((string) config('developer_console.github.token'))
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
        $repository = trim((string) config('developer_console.github.repository'), '/');
        $workflow = rawurlencode((string) config('developer_console.github.workflow'));

        return "/repos/{$repository}/actions/workflows/{$workflow}{$suffix}";
    }
}
