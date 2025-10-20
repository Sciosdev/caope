<?php

namespace App\Services;

use App\Models\TimelineEvento;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class AuthEventTimelineRecorder
{
    protected bool $enabled;

    public function __construct()
    {
        $this->enabled = (bool) config('auth-events.timeline.enabled', false);
    }

    public function recordLogin(?Authenticatable $user, array $payload = []): void
    {
        $this->store($user, 'auth.login', $payload);
    }

    public function recordLogout(?Authenticatable $user, array $payload = []): void
    {
        $this->store($user, 'auth.logout', $payload);
    }

    public function recordFailed(?Authenticatable $user, array $payload = []): void
    {
        $this->store($user, 'auth.failed', $payload);
    }

    protected function store(?Authenticatable $user, string $evento, array $payload): void
    {
        if (! $this->enabled || ! $user instanceof User) {
            return;
        }

        $expedienteId = $payload['expediente_id'] ?? $this->resolveExpedienteId($user);

        if (! $expedienteId) {
            return;
        }

        TimelineEvento::create([
            'expediente_id' => $expedienteId,
            'actor_id' => $user->getAuthIdentifier(),
            'evento' => $evento,
            'payload' => $payload,
        ]);
    }

    protected function resolveExpedienteId(User $user): ?int
    {
        return $user->expedientesCreados()->value('id')
            ?? $user->expedientesTutorados()->value('id')
            ?? $user->expedientesCoordinados()->value('id');
    }
}
