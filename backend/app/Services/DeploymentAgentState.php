<?php

namespace App\Services;

use App\Models\DeploymentRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class DeploymentAgentState
{
    public function read(string $name): ?array
    {
        if (! in_array($name, ['expected', 'recovery', 'result', 'version'], true)) {
            return null;
        }

        try {
            $path = storage_path('app/deployment/'.$name.'.json');
            clearstatcache(true, $path);
            if (! is_file($path) || ! is_readable($path) || filesize($path) > 65536) {
                return null;
            }

            $value = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

            return is_array($value) ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function removeAuthorization(string $requestId): bool
    {
        return $this->withAuthorizationLock(function () use ($requestId): bool {
            $marker = $this->read('expected');

            return is_string($marker['request_id'] ?? null)
                && hash_equals($requestId, $marker['request_id'])
                && File::delete(storage_path('app/deployment/expected.json'));
        });
    }

    public function discardInvalidAuthorization(?array $snapshot): bool
    {
        return $this->withAuthorizationLock(function () use ($snapshot): bool {
            $marker = $this->read('expected');

            if ($marker !== $snapshot || $this->hasValidAuthorization($marker)) {
                return false;
            }

            return File::delete(storage_path('app/deployment/expected.json'));
        });
    }

    private function withAuthorizationLock(callable $action): bool
    {
        // Use the same mutex as the preparation callback, then reread the file.
        // A renewed or replaced authorization must survive a delayed cleanup.
        $lock = Cache::lock('developer-console:deployment-marker', 30);
        if (! $lock->get()) {
            return false;
        }

        try {
            return $action();
        } finally {
            $lock->release();
        }
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

    public function progress(string $requestId, string $stage): void
    {
        if (! array_key_exists($stage, DeploymentRun::AGENT_STAGES)) {
            return;
        }

        DB::transaction(function () use ($requestId, $stage): void {
            $deployment = DeploymentRun::query()->where('request_id', $requestId)->lockForUpdate()->first();

            if ($deployment) {
                // The web request may import result.json before this process
                // drains the shell's final stdout. Do not revive that finished
                // attempt; only its matching recovery journal permits progress.
                $recovery = $this->read('recovery');
                $recovering = ($recovery['schema'] ?? null) === 1
                    && is_string($recovery['request_id'] ?? null)
                    && hash_equals($requestId, $recovery['request_id']);

                if ($deployment->agent_result_key !== null && ! $recovering) {
                    return;
                }

                $deployment->update([
                    'agent_status' => 'running',
                    'agent_stage' => $stage,
                    'agent_started_at' => $deployment->agent_started_at ?? now(),
                    'agent_finished_at' => null,
                    'agent_heartbeat_at' => now(),
                ]);
            }
        });
    }

    public function heartbeat(string $requestId): void
    {
        DeploymentRun::query()->where('request_id', $requestId)
            ->where('agent_status', 'running')
            ->update(['agent_heartbeat_at' => now()]);
    }

    /** Import the shell's durable outcome, including recovery before Laravel boots. */
    public function synchronizeResult(): ?array
    {
        $result = $this->read('result');
        $status = $result['status'] ?? null;

        if (($result['schema'] ?? null) !== 1
            || ! is_string($result['request_id'] ?? null)
            || trim($result['request_id']) === '' || strlen($result['request_id']) > 100
            || preg_match('/[\x00-\x1F\x7F]/', $result['request_id']) !== 0
            || ! is_string($result['finished_at'] ?? null)
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $result['finished_at']) !== 1
            || ! in_array($status, ['succeeded', 'failed', 'recovery_required'], true)
            || ! is_string($result['stage'] ?? null)
            || ! array_key_exists($result['stage'], DeploymentRun::AGENT_STAGES)
            || ! is_bool($result['restored'] ?? null)
            || ($status === 'succeeded' && ($result['stage'] !== 'completed' || $result['restored']))
            || (isset($result['interrupted']) && ! is_bool($result['interrupted']))
            || (isset($result['sha']) && (! is_string($result['sha']) || preg_match('/^[a-f0-9]{40}$/iD', $result['sha']) !== 1))) {
            return null;
        }

        try {
            $finishedAt = Carbon::parse($result['finished_at']);
        } catch (Throwable) {
            return null;
        }

        $resultKey = hash('sha256', json_encode([
            'schema' => 1,
            'request_id' => $result['request_id'],
            'status' => $status,
            'stage' => $result['stage'],
            'finished_at' => $finishedAt->copy()->utc()->toIso8601String(),
            'restored' => $result['restored'],
            'interrupted' => $result['interrupted'] ?? false,
            'sha' => isset($result['sha']) ? strtolower($result['sha']) : null,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($result, $resultKey, $status, $finishedAt): ?array {
            $deployment = DeploymentRun::query()->where('request_id', $result['request_id'])->lockForUpdate()->first();

            if (! $deployment || $finishedAt->gt(now()->addMinute())
                || $finishedAt->lt($deployment->created_at->copy()->subMinute())
                || $deployment->agent_result_key === $resultKey
                || ($deployment->agent_result_at && $deployment->agent_result_at->gt($finishedAt))
                || ($deployment->agent_finished_at && $deployment->agent_finished_at->gt($finishedAt))) {
                return null;
            }

            $message = null;
            if ($status !== 'succeeded') {
                $message = $deployment->error_message ?: 'El agente no pudo completar el despliegue. Revisa la etapa y el diagnóstico del intento.';
                if ($result['restored']) {
                    $message = str_replace([
                        ' La recuperación no terminó. CAOPE conserva el mantenimiento; el agente volverá a intentarlo automáticamente.',
                        ' La recuperación no terminó; el agente volverá a intentarlo automáticamente.',
                    ], '', $message);
                }
                if ($result['restored'] && ! str_contains($message, 'restaurada automáticamente')) {
                    $message .= ' La versión anterior fue restaurada automáticamente.';
                }
                if ($status === 'recovery_required' && ! str_contains($message, 'recuperación no terminó')) {
                    $message .= ' La recuperación no terminó. CAOPE conserva el mantenimiento; el agente volverá a intentarlo automáticamente.';
                }
            }

            $deployment->update([
                'agent_status' => $status,
                'agent_stage' => $result['stage'],
                'agent_finished_at' => $finishedAt,
                'agent_heartbeat_at' => $finishedAt,
                'agent_result_key' => $resultKey,
                'agent_result_at' => $finishedAt,
                'error_message' => $message === null ? null : mb_substr($message, 0, 1500),
            ]);

            return $result;
        });
    }
}
