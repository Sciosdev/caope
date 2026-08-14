<?php

namespace App\Policies;

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExpedientePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $user->can('expedientes.view');
    }

    public function view(User $user, Expediente $expediente): bool
    {
        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        return $user->isAssignedToExpediente($expediente);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $user->hasRole('alumno');
    }

    public function update(User $user, Expediente $expediente): bool
    {
        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        if ($user->isCoordinatorOf($expediente)) {
            return true;
        }

        return $user->isAssignedToExpediente($expediente)
            && $expediente->estado !== 'cerrado';
    }

    public function delete(User $user, Expediente $expediente): bool
    {
        return $this->isAdmin($user);
    }

    public function changeState(User $user, Expediente $expediente): bool
    {
        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        return $user->isCoordinatorOf($expediente)
            || $user->isTutorOf($expediente);
    }

    public function viewFullName(User $user, Expediente $expediente): bool
    {
        return $this->view($user, $expediente);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('admin') || $user->isApprovedPaps();
    }
}
