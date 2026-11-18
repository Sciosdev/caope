<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class QueueWorkerHeartbeat
{
    /** @var array<string, int> */
    private array $lastRecorded = [];

    public function record(string $connection, string $queues): void
    {
        foreach (explode(',', $queues) as $queue) {
            $queue = trim($queue);
            if ($queue === '') {
                continue;
            }

            $key = $this->key($connection, $queue);
            $timestamp = now()->getTimestamp();

            // A busy or idle worker can loop many times per second. Keep this
            // diagnostic lightweight, including while the cache is unavailable.
            if (isset($this->lastRecorded[$key]) && $timestamp - $this->lastRecorded[$key] < 30) {
                continue;
            }

            $this->lastRecorded[$key] = $timestamp;

            try {
                Cache::put($key, now()->toIso8601String(), now()->addDay());
            } catch (Throwable $exception) {
                // A diagnostic must never stop the worker or fail its job.
                report($exception);
            }
        }
    }

    public function lastSeen(string $connection, string $queue): ?Carbon
    {
        $lastSeen = Cache::get($this->key($connection, $queue));

        return is_string($lastSeen) ? Carbon::parse($lastSeen) : null;
    }

    private function key(string $connection, string $queue): string
    {
        return 'system.queue.worker.last_run:'.hash('sha256', $connection."\0".$queue);
    }
}
