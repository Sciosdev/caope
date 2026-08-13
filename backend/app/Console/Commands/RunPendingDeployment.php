<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class RunPendingDeployment extends Command
{
    protected $signature = 'caope:deploy-pending';

    protected $description = 'Ejecuta una revisión de staging previamente autorizada por GitHub';

    public function handle(): int
    {
        $markerPath = storage_path('app/deployment/expected.json');

        if (! is_readable($markerPath)) {
            return self::SUCCESS;
        }

        $marker = json_decode((string) File::get($markerPath), true);
        $expiresAt = is_array($marker) ? (int) ($marker['expires_at'] ?? 0) : 0;

        if ($expiresAt < now()->timestamp) {
            File::delete($markerPath);
            $this->warn('Se eliminó una autorización de despliegue vencida o inválida.');

            return self::FAILURE;
        }

        $repositoryRoot = dirname(base_path());
        $defaultScriptPath = $repositoryRoot.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'deploy-cpanel-staging.sh';
        $scriptPath = trim((string) config('developer_console.deploy_script')) ?: $defaultScriptPath;

        if (! is_file($scriptPath)) {
            $this->error('No se encontró el script de despliegue de staging.');

            return self::FAILURE;
        }

        $result = Process::path($repositoryRoot)
            ->env(['GIT_TERMINAL_PROMPT' => '0'])
            ->timeout(1800)
            ->run(['/bin/bash', $scriptPath]);

        $this->appendDeploymentLog($result->exitCode(), $result->output(), $result->errorOutput());

        if ($result->failed()) {
            $this->error('El despliegue programado falló. Revisa storage/logs/developer-deploy.log.');

            return self::FAILURE;
        }

        $this->info('El despliegue programado terminó correctamente.');

        return self::SUCCESS;
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
}
