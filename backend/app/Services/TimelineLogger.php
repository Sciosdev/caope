<?php

namespace App\Services;

use App\Models\Expediente;
use App\Models\TimelineEvento;
use Illuminate\Contracts\Auth\Authenticatable;

class TimelineLogger
{
    public function log(Expediente $expediente, string $evento, ?Authenticatable $actor, array $payload = []): void
    {
        TimelineEvento::create([
            'expediente_id' => $expediente->getKey(),
            'actor_id' => $actor?->getAuthIdentifier(),
            'evento' => $evento,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
