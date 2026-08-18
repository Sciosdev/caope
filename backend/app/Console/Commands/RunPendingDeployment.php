<?php

namespace App\Console\Commands;

use App\Models\DeploymentRun;
use App\Services\DeveloperConsoleSettings;
use App\Services\ProductionSecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class RunPendingDeployment extends Command
{
    protected $signature = 'caope:deploy-pending';

    protected $description = 'Ejecuta una revisión previamente autorizada por GitHub';

    public function handle(
        DeveloperConsoleSettings $settings,
        ProductionSecurityAudit $securityAudit,
    ): int {
        $markerPath = storage_path('app/deployment/expected.json');

        if (! is_readable($markerPath)) {
            return self::SUCCESS;
        }

        $marker = json_decode((string) File::get($markerPath), true);

        if (! $this->hasValidAuthorization($marker)) {
            File::delete($markerPath);
            $this->warn('Se eliminó una autorización de despliegue inválida o vencida.');

            return self::FAILURE;
        }

        $repositoryRoot = dirname(base_path());
        $defaultScriptPath = $repositoryRoot.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'deploy-scheduled.sh';
        $scriptPath = $settings->deployScript() ?: $defaultScriptPath;

        if (! is_file($scriptPath)) {
            $this->recordFailure(
                (string) $marker['request_id'],
                null,
                'No se encontró el script de despliegue programado.',
                '',
            );
            $this->error('No se encontró el script de despliegue programado.');

            return self::FAILURE;
        }

        $securityProfile = $securityAudit->profileForCurrentEnvironment();
        $isProduction = $securityProfile === ProductionSecurityAudit::PROFILE_PRODUCTION;

        $result = Process::path($repositoryRoot)
            ->env([
                'CAOPE_PHP_BIN' => PHP_BINARY,
                'CAOPE_REQUIRE_CLEAN_CHECKOUT' => $isProduction ? '1' : '0',
                'CAOPE_SECURITY_PROFILE' => $securityProfile,
                'GIT_TERMINAL_PROMPT' => '0',
            ])
            ->timeout(1800)
            ->run(['/bin/bash', $scriptPath]);

        $this->appendDeploymentLog($result->exitCode(), $result->output(), $result->errorOutput());

        if ($result->failed()) {
            $this->recordFailure(
                (string) $marker['request_id'],
                $result->exitCode(),
                $result->output(),
                $result->errorOutput(),
            );
            $this->error('El despliegue programado falló. Revisa storage/logs/developer-deploy.log.');

            return self::FAILURE;
        }

        $this->info('El despliegue programado terminó correctamente.');
        DeploymentRun::query()
            ->where('request_id', (string) $marker['request_id'])
            ->update(['error_message' => null]);

        return self::SUCCESS;
    }

    private function hasValidAuthorization(mixed $marker): bool
    {
        if (! is_array($marker)) {
            return false;
        }

        $sha = $marker['sha'] ?? null;
        $requestId = $marker['request_id'] ?? null;
        $expiresAt = $marker['expires_at'] ?? null;

        return is_string($sha)
            && preg_match('/^[a-f0-9]{40}$/i', $sha) === 1
            && is_string($requestId)
            && trim($requestId) !== ''
            && strlen($requestId) <= 100
            && is_int($expiresAt)
            && $expiresAt > now()->timestamp;
    }

    private function appendDeploymentLog(?int $exitCode, string $output, string $errorOutput): void
    {
        $logPath = storage_path('logs/developer-deploy.log');
        File::ensureDirectoryExists(dirname($logPath));

        File::append($logPath, implode(PHP_EOL, [
            sprintf('[%s] Inicio de despliegue programado', now()->toIso8601String()),
            trim($output),
            trim($errorOutput),
            sprintf('[%s] Código de salida: %s', now()->toIso8601String(), $exitCode ?? 'desconocido'),
            '',
        ]));
    }

    private function recordFailure(
        string $requestId,
        ?int $exitCode,
        string $output,
        string $errorOutput,
    ): void {
        $combined = $output.PHP_EOL.$errorOutput;
        preg_match_all('/^ERROR:\s*(.+)$/mi', $combined, $matches);

        $details = collect($matches[1] ?? [])
            ->map(static fn (string $line): string => trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $line) ?? ''))
            ->filter()
            ->unique()
            ->take(-3)
            ->implode(' ');

        $message = sprintf(
            'El agente local no pudo completar el despliegue (código %s).',
            $exitCode ?? 'desconocido',
        );

        if ($details !== '') {
            $message .= ' '.$details;
        }

        if (str_contains($combined, 'Rollback automático completado')) {
            $message .= ' La versión anterior fue restaurada automáticamente.';
        }

        DeploymentRun::query()
            ->where('request_id', $requestId)
            ->update(['error_message' => mb_substr($message, 0, 1500)]);
    }
}
