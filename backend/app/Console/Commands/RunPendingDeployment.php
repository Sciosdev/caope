<?php

namespace App\Console\Commands;

use App\Models\DeploymentRun;
use App\Services\DeploymentAgentState;
use App\Services\DeveloperConsoleSettings;
use App\Services\ProductionSecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Throwable;

class RunPendingDeployment extends Command
{
    protected $signature = 'caope:deploy-pending';

    protected $description = 'Ejecuta una revisión autorizada o recupera un despliegue interrumpido';

    public function handle(
        DeveloperConsoleSettings $settings,
        ProductionSecurityAudit $securityAudit,
        DeploymentAgentState $state,
    ): int {
        $state->synchronizeResult();
        $marker = $state->read('expected');
        $recovery = $state->read('recovery');
        $unfinished = DeploymentRun::query()
            ->whereIn('agent_status', DeploymentRun::BLOCKING_AGENT_STATUSES)
            ->oldest('agent_started_at')->first();
        $recovering = is_file(storage_path('app/deployment/recovery.json')) || $unfinished !== null;
        $candidateRequestId = $recovering ? ($recovery['request_id'] ?? null) : ($marker['request_id'] ?? null);
        $requestId = is_string($candidateRequestId) ? $candidateRequestId : ($recovering ? ($unfinished?->request_id ?? '') : '');

        if (! $recovering && ! is_file(storage_path('app/deployment/expected.json'))) {
            return self::SUCCESS;
        }

        if (! $recovering && ! $this->hasValidAuthorization($marker)) {
            if (! $state->discardInvalidAuthorization($marker)) {
                return self::SUCCESS;
            }
            $this->recordFailure($requestId, null, 'ERROR: La autorización venció antes de que el agente pudiera instalar la versión.', '', 'failed');
            $this->warn('Se eliminó una autorización de despliegue inválida o vencida.');

            return self::FAILURE;
        }

        $deployment = DeploymentRun::query()->where('request_id', $requestId)->first();

        // A replayed callback must not start the same terminal local attempt twice.
        if (! $recovering && in_array($deployment?->agent_status, ['succeeded', 'failed', 'timed_out'], true)) {
            $state->removeAuthorization($requestId);

            return self::SUCCESS;
        }

        $repositoryRoot = dirname(base_path());
        $scriptPath = $settings->deployScript() ?: $repositoryRoot.'/scripts/deploy-scheduled.sh';

        if (! is_file($scriptPath)) {
            $this->recordFailure($requestId, null, 'ERROR: No se encontró el script de despliegue programado.', '', $recovering ? 'recovery_required' : 'failed');
            $state->removeAuthorization($requestId);
            $this->error('No se encontró el script de despliegue programado.');

            return self::FAILURE;
        }

        $securityProfile = $securityAudit->profileForCurrentEnvironment();
        $command = ['/bin/bash', $scriptPath];
        if ($recovering) {
            $command[] = '--recover-only';
        }

        $buffers = ['out' => '', 'err' => ''];
        $started = false;
        $recoveryNotNeeded = false;
        $terminalStatus = null;
        $process = null;
        $this->appendLog(sprintf('[%s] Inicio de intento %s%s'.PHP_EOL, now()->toIso8601String(), $requestId, $recovering ? ' (recuperación)' : ''));

        try {
            $process = Process::path($repositoryRoot)
                ->env([
                    'CAOPE_PHP_BIN' => PHP_BINARY,
                    'CAOPE_REQUIRE_CLEAN_CHECKOUT' => $securityProfile === ProductionSecurityAudit::PROFILE_PRODUCTION ? '1' : '0',
                    'CAOPE_SECURITY_PROFILE' => $securityProfile,
                    'GIT_TERMINAL_PROMPT' => '0',
                    // The shell rereads authorization under flock. It must not
                    // execute a replacement request against this row's progress.
                    'CAOPE_AGENT_REQUEST_ID' => $requestId,
                    'CAOPE_AGENT_EXPECTED_SHA' => $recovering ? '' : strtolower($marker['sha']),
                ])
                // The shell has 30 minutes plus six minutes for graceful recovery.
                // Record outer timeout before the existing 40-minute systemd limit.
                ->timeout(2250)
                ->start($command, function (string $type, string $output) use ($state, $requestId, &$buffers, &$started, &$recoveryNotNeeded, &$terminalStatus): void {
                    $this->appendLog($output);
                    $buffers[$type] .= $output;

                    while (($newline = strpos($buffers[$type], "\n")) !== false) {
                        $line = trim(substr($buffers[$type], 0, $newline));
                        $buffers[$type] = substr($buffers[$type], $newline + 1);

                        if ($type !== 'out') {
                            continue;
                        }

                        if (preg_match('/^CAOPE_STAGE=([a-z_]+)$/', $line, $matches) === 1
                            && array_key_exists($matches[1], DeploymentRun::AGENT_STAGES)) {
                            $started = true;
                            $this->safely(fn () => $state->progress($requestId, $matches[1]));
                        } elseif ($line === 'CAOPE_RECOVERY=not_needed') {
                            $recoveryNotNeeded = true;
                        } elseif (preg_match('/^CAOPE_RESULT=(succeeded|failed|recovery_required)$/', $line, $matches) === 1) {
                            $terminalStatus = $matches[1];
                        }
                    }

                    $buffers[$type] = substr($buffers[$type], -8192);
                });

            $lastHeartbeat = 0;
            while ($process->running()) {
                $process->ensureNotTimedOut();
                if ($started && time() - $lastHeartbeat >= 15) {
                    $this->safely(fn () => $state->heartbeat($requestId));
                    $lastHeartbeat = time();
                }
                usleep(250000);
            }
            $result = $process->wait();
        } catch (ProcessTimedOutException $exception) {
            $this->appendLog(PHP_EOL.'ERROR: Se agotó el tiempo máximo del agente.'.PHP_EOL);
            $this->recordFailure($requestId, $exception->result->exitCode(), $exception->result->output(),
                $exception->result->errorOutput().PHP_EOL.'ERROR: Se agotó el tiempo máximo. La recuperación se comprobará automáticamente en la siguiente ejecución.', 'interrupted');
            $state->removeAuthorization($requestId);
            $this->error('El intento excedió el tiempo máximo; quedó registrado para recuperación.');

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            if ($process !== null) {
                $this->safely(fn () => $process->stop(5));
            }
            $this->appendLog(PHP_EOL.'ERROR: El proceso del agente se interrumpió inesperadamente.'.PHP_EOL);
            $this->recordFailure($requestId, null, 'ERROR: No fue posible ejecutar o supervisar el agente. La siguiente ejecución comprobará su recuperación.', '', $process !== null || $recovering ? 'interrupted' : 'failed');
            $state->removeAuthorization($requestId);
            $this->error('No fue posible completar el proceso del agente. El diagnóstico quedó registrado.');

            return self::FAILURE;
        }

        $this->appendLog(sprintf(PHP_EOL.'[%s] Código de salida: %s'.PHP_EOL, now()->toIso8601String(), $result->exitCode() ?? 'desconocido'));

        // Another owner still has the OS lock: never change its outcome or marker.
        if ($result->exitCode() === 75) {
            if (str_contains($result->errorOutput(), 'ERROR:')) {
                DeploymentRun::query()->where('request_id', $requestId)
                    ->where(function ($query): void {
                        $query->whereNull('agent_status')->orWhere('agent_status', '!=', 'running');
                    })
                    ->update(['error_message' => 'Existe un bloqueo previo cuyo propietario no pudo verificarse. El agente conserva el bloqueo para evitar dos actualizaciones simultáneas.']);
            }

            return self::SUCCESS;
        }

        $outcome = $state->synchronizeResult();
        if (($outcome['request_id'] ?? null) === $requestId) {
            $terminalStatus = $outcome['status'];
        }

        if ($recovering && $result->successful() && $recoveryNotNeeded) {
            // Another invocation may have completed after we selected recovery
            // but before this shell acquired flock. Preserve its terminal result.
            DB::transaction(function () use ($requestId): void {
                $current = DeploymentRun::query()->where('request_id', $requestId)->lockForUpdate()->first();
                if (in_array($current?->agent_status, DeploymentRun::BLOCKING_AGENT_STATUSES, true)) {
                    $this->recordFailure($requestId, null, 'ERROR: El intento anterior se interrumpió. El agente comprobó que no hay una recuperación pendiente ni otro proceso ejecutándose.', '', 'failed');
                }
            });
            $state->removeAuthorization($requestId);

            return self::SUCCESS;
        }

        if ($result->failed() || in_array($terminalStatus, ['failed', 'recovery_required'], true)) {
            $status = $terminalStatus === 'recovery_required' || is_file(storage_path('app/deployment/recovery.json'))
                ? 'recovery_required' : 'failed';
            $this->recordFailure($requestId, $result->exitCode(), $result->output(), $result->errorOutput(), $status, ($outcome['restored'] ?? false) === true);
            $state->removeAuthorization($requestId);
            $this->error('El despliegue programado falló. Revisa el diagnóstico en la consola.');

            return $recovering && $status === 'failed' && $result->successful() ? self::SUCCESS : self::FAILURE;
        }

        if ($terminalStatus !== 'succeeded' || $recovering) {
            $this->recordFailure($requestId, $result->exitCode(), 'ERROR: El proceso terminó sin confirmar una versión instalada. El agente verificará el intento antes de permitir otro despliegue.', '', 'interrupted');
            $state->removeAuthorization($requestId);
            $this->error('No se recibió una confirmación verificable del despliegue.');

            return self::FAILURE;
        }

        DeploymentRun::query()->where('request_id', $requestId)->update([
            'agent_status' => 'succeeded',
            'agent_stage' => 'completed',
            'agent_finished_at' => now(),
            'agent_heartbeat_at' => now(),
            'error_message' => null,
        ]);
        $state->removeAuthorization($requestId);
        $this->info('El despliegue programado terminó correctamente.');

        return self::SUCCESS;
    }

    private function hasValidAuthorization(?array $marker): bool
    {
        return is_string($marker['sha'] ?? null)
            && preg_match('/^[a-f0-9]{40}$/iD', $marker['sha']) === 1
            && is_string($marker['request_id'] ?? null)
            && trim($marker['request_id']) !== ''
            && strlen($marker['request_id']) <= 100
            && preg_match('/[\x00-\x1F\x7F]/', $marker['request_id']) === 0
            && is_int($marker['expires_at'] ?? null)
            && $marker['expires_at'] > now()->timestamp;
    }

    private function appendLog(string $text): void
    {
        $this->safely(function () use ($text): void {
            $path = storage_path('logs/developer-deploy.log');
            File::ensureDirectoryExists(dirname($path));
            File::append($path, $text);
        });
    }

    private function safely(callable $action): void
    {
        try {
            $action();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function recordFailure(string $requestId, ?int $exitCode, string $output, string $errorOutput, string $status, bool $restored = false): void
    {
        $combined = $output.PHP_EOL.$errorOutput;
        preg_match_all('/^ERROR:\\s*(.+)$/mi', $combined, $matches);
        $details = collect($matches[1] ?? [])
            ->map(static fn (string $line): string => trim(preg_replace('/[\\x00-\\x1F\\x7F]+/', ' ', $line) ?? ''))
            ->filter()->unique()->take(-3)->implode(' ');
        $message = sprintf('El agente local no pudo completar el despliegue (código %s).', $exitCode ?? 'desconocido');
        if ($details !== '') {
            $message .= ' '.$details;
        }
        if ($restored || str_contains($combined, 'Rollback automático verificado') || str_contains($combined, 'Rollback automático completado')) {
            $message .= ' La versión anterior fue restaurada automáticamente.';
        }
        if ($status === 'recovery_required') {
            $message .= ' La recuperación no terminó; el agente volverá a intentarlo automáticamente.';
        }

        DeploymentRun::query()->where('request_id', $requestId)->update([
            'agent_status' => $status,
            'agent_finished_at' => in_array($status, ['interrupted', 'recovery_required'], true) ? null : now(),
            'agent_heartbeat_at' => now(),
            'error_message' => mb_substr($message, 0, 1500),
        ]);
    }
}
