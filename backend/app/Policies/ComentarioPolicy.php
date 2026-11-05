<?php

namespace App\Policies;

use App\Models\Comentario;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ComentarioPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Comentario $comentario): bool
    {
        $comentable = $comentario->comentable;

        if (! $comentable instanceof Model) {
            return false;
        }

        return $this->canInteractWithComentable($user, $comentable);
    }

    public function create(User $user, Model $comentable): bool
    {
        $expediente = $this->resolveExpediente($comentable);
        if ($expediente?->trashed()) {
            return false;
        }

        return $this->canInteractWithComentable($user, $comentable);
    }

    public function update(User $user, Comentario $comentario): bool
    {
        $comentable = $comentario->comentable;
        $expediente = $comentable instanceof Model
            ? $this->resolveExpediente($comentable)
            : null;

        if (! $comentable instanceof Model
            || ! $expediente
            || $expediente->trashed()
            || $expediente->retention_until?->isFuture()
            || ! $this->canInteractWithComentable($user, $comentable)) {
            return false;
        }

        if ($this->isManager($user, $comentable)) {
            return true;
        }

        return $comentario->user_id === $user->id;
    }

    public function delete(User $user, Comentario $comentario): bool
    {
        $comentable = $comentario->comentable;
        $expediente = $comentable instanceof Model
            ? $this->resolveExpediente($comentable)
            : null;

        if (! $comentable instanceof Model
            || ! $expediente
            || $expediente->trashed()
            || $expediente->retention_until?->isFuture()
            || ! $this->canInteractWithComentable($user, $comentable)) {
            return false;
        }

        if ($this->isManager($user, $comentable)) {
            return true;
        }

        return $comentario->user_id === $user->id;
    }

    private function canInteractWithComentable(User $user, Model $comentable): bool
    {
        return Gate::forUser($user)->check('view', $comentable);
    }

    private function isManager(User $user, Model $comentable): bool
    {
        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        $expediente = $comentable instanceof \App\Models\Expediente
            ? $comentable
            : ($comentable instanceof \App\Models\Sesion ? $comentable->expediente : null);

        return $expediente !== null && $user->isCoordinatorOf($expediente);
    }

    private function resolveExpediente(Model $comentable): ?\App\Models\Expediente
    {
        if ($comentable instanceof \App\Models\Expediente) {
            return $comentable;
        }

        return $comentable instanceof \App\Models\Sesion ? $comentable->expediente : null;
    }
}
