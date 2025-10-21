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
        return $this->canInteractWithComentable($user, $comentable);
    }

    public function update(User $user, Comentario $comentario): bool
    {
        $comentable = $comentario->comentable;

        if (! $comentable instanceof Model) {
            return false;
        }

        if (! $this->canInteractWithComentable($user, $comentable)) {
            return false;
        }

        if ($this->isAdminOrManager($user)) {
            return true;
        }

        return $comentario->user_id === $user->id;
    }

    public function delete(User $user, Comentario $comentario): bool
    {
        $comentable = $comentario->comentable;

        if (! $comentable instanceof Model) {
            return false;
        }

        if (! $this->canInteractWithComentable($user, $comentable)) {
            return false;
        }

        if ($this->isAdminOrManager($user)) {
            return true;
        }

        return $comentario->user_id === $user->id;
    }

    private function canInteractWithComentable(User $user, Model $comentable): bool
    {
        return Gate::forUser($user)->check('view', $comentable);
    }

    private function isAdminOrManager(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->can('expedientes.manage');
    }
}
