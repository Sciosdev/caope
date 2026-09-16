<?php

namespace App\Listeners;

use App\Services\QueueWorkerHeartbeat;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\Looping;

class QueueWorkerHeartbeatSubscriber
{
    public function __construct(private QueueWorkerHeartbeat $heartbeat) {}

    public function onLooping(Looping $event): void
    {
        $this->heartbeat->record((string) $event->connectionName, (string) $event->queue);
    }

    public function onProcessed(JobProcessed $event): void
    {
        // Synchronous jobs execute in the web request and are not evidence of
        // an independent worker, even when emitted by a failover connection.
        if ($event->job instanceof \Illuminate\Queue\Jobs\SyncJob) {
            return;
        }

        $this->heartbeat->record((string) $event->connectionName, (string) $event->job->getQueue());
    }

    public function subscribe(Dispatcher $events): void
    {
        // Retain this subscriber instance so the in-process throttle survives
        // between iterations of a long-running worker.
        $events->listen(Looping::class, [$this, 'onLooping']);
        $events->listen(JobProcessed::class, [$this, 'onProcessed']);
    }
}
