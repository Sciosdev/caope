<?php

namespace App\Policies;

use App\Models\Anexo;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AnexoPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Anexo $anexo): bool
    {
        $expediente = $anexo->expediente;
        if (! $expediente) {
            return false;
        }

        if ($expediente->trashed()) {
            return $this->isAdmin($user);
        }

        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        if ($user->isAssignedToExpediente($expediente)) {
            return true;
        }

        return false;
    }

    public function create(User $user, Expediente $expediente): bool
    {
        if ($expediente->trashed()) {
            return false;
        }

        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        return $user->isAssignedToExpediente($expediente);
    }

    public function delete(User $user, Anexo $anexo): bool
    {
        $expediente = $anexo->expediente;
        if (! $expediente || $expediente->trashed() || $expediente->retention_until?->isFuture()) {
            return false;
        }

        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        if (! $user->isAssignedToExpediente($expediente)) {
            return false;
        }

        return $user->isCoordinatorOf($expediente)
            || $user->isTutorOf($expediente)
            || ($user->isFacilitatorOf($expediente)
                && (int) $user->id === (int) $anexo->subido_por);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('admin') || $user->isApprovedPaps();
    }
}
