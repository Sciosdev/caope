<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class DeveloperConsoleSettings
{
    public const PRODUCTION_REPOSITORY = 'Sciosdev/caope';

    public const PRODUCTION_WORKFLOW = 'deploy.yml';

    public const PRODUCTION_REF = 'main';

    public const PRODUCTION_TARGET = 'producción';

    /**
     * @return array<string, mixed>|null
     */
    public function encrypted(): ?array
    {
        $path = $this->path();

        if (! is_readable($path)) {
            return null;
        }

        try {
            $payload = json_decode(
                Crypt::decryptString((string) File::get($path)),
                true,
                flags: JSON_THROW_ON_ERROR
            );

            if (! is_array($payload) || ($payload['schema_version'] ?? null) !== 1) {
                return null;
            }

            $github = $payload['github'] ?? null;

            if (! is_array($github)
                || trim((string) ($github['token'] ?? '')) === ''
                || trim((string) ($github['repository'] ?? '')) === ''
                || trim((string) ($github['workflow'] ?? '')) === ''
                || trim((string) ($github['ref'] ?? '')) === '') {
                return null;
            }

            return $payload;
        } catch (Throwable) {
            return null;
        }
    }

    public function encryptedFileExists(): bool
    {
        return is_file($this->path());
    }

    public function encryptedFileIsInvalid(): bool
    {
        return $this->encryptedFileExists() && $this->encrypted() === null;
    }

    public function hasEncryptedSettings(): bool
    {
        return $this->encrypted() !== null;
    }

    public function hasLegacySettings(): bool
    {
        return (bool) config('developer_console.enabled')
            && trim((string) config('developer_console.github.token')) !== '';
    }

    public function canActivate(): bool
    {
        return ! $this->hasEncryptedSettings() && ! $this->hasLegacySettings();
    }

    public function enabled(): bool
    {
        $settings = $this->encrypted();

        if ($settings !== null) {
            return (bool) ($settings['enabled'] ?? false);
        }

        if ($this->encryptedFileExists()) {
            return false;
        }

        return (bool) config('developer_console.enabled');
    }

    public function targetLabel(): string
    {
        return (string) ($this->encrypted()['target_label'] ?? config('developer_console.target_label', self::PRODUCTION_TARGET));
    }

    public function deployWebhookToken(): string
    {
        return trim((string) config('developer_console.deploy_webhook_token', ''));
    }

    public function deployScript(): string
    {
        return trim((string) config('developer_console.deploy_script', ''));
    }

    public function githubApiUrl(): string
    {
        return rtrim((string) ($this->encrypted()['github']['api_url'] ?? config('developer_console.github.api_url')), '/');
    }

    public function githubRepository(): string
    {
        return trim((string) ($this->encrypted()['github']['repository'] ?? config('developer_console.github.repository')));
    }

    public function githubWorkflow(): string
    {
        return trim((string) ($this->encrypted()['github']['workflow'] ?? config('developer_console.github.workflow')));
    }

    public function githubRef(): string
    {
        return trim((string) ($this->encrypted()['github']['ref'] ?? config('developer_console.github.ref')));
    }

    public function githubToken(): string
    {
        return trim((string) ($this->encrypted()['github']['token'] ?? config('developer_console.github.token')));
    }

    public function githubWorkflowId(): ?int
    {
        $workflowId = data_get($this->encrypted(), 'github.workflow_id');

        return is_int($workflowId) && $workflowId > 0 ? $workflowId : null;
    }

    public function storeProduction(string $githubToken, int $workflowId, int $activatedBy): void
    {
        $payload = [
            'schema_version' => 1,
            'enabled' => true,
            'target_label' => self::PRODUCTION_TARGET,
            'github' => [
                'api_url' => 'https://api.github.com',
                'repository' => self::PRODUCTION_REPOSITORY,
                'workflow' => self::PRODUCTION_WORKFLOW,
                'ref' => self::PRODUCTION_REF,
                'token' => trim($githubToken),
                'workflow_id' => $workflowId,
            ],
            'activated_by' => $activatedBy,
            'activated_at' => now()->toIso8601String(),
        ];

        $this->write($payload);
    }

    public function rotateGithubToken(string $githubToken, int $workflowId, int $rotatedBy): void
    {
        $payload = $this->encrypted();

        if ($payload === null) {
            throw new RuntimeException('No existe una configuración web válida que pueda actualizarse.');
        }

        $payload['github']['token'] = trim($githubToken);
        $payload['github']['workflow_id'] = $workflowId;
        $payload['rotated_by'] = $rotatedBy;
        $payload['rotated_at'] = now()->toIso8601String();

        $this->write($payload);
    }

    private function path(): string
    {
        return (string) config('developer_console.settings_path');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function write(array $payload): void
    {
        $path = $this->path();
        $directory = dirname($path);

        try {
            File::ensureDirectoryExists($directory, 0700, true);
            @chmod($directory, 0700);

            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            File::replace($path, Crypt::encryptString($json), 0600);
            @chmod($path, 0600);
        } catch (Throwable $exception) {
            throw new RuntimeException('No fue posible guardar la configuración cifrada.', previous: $exception);
        }
    }
}
