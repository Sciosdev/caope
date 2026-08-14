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
        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        $expediente = $anexo->expediente;

        if ($expediente && $user->isAssignedToExpediente($expediente)) {
            return true;
        }

        return false;
    }

    public function create(User $user, Expediente $expediente): bool
    {
        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        return $user->isAssignedToExpediente($expediente);
    }

    public function delete(User $user, Anexo $anexo): bool
    {
        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        $expediente = $anexo->expediente;

        if (! $expediente || ! $user->isAssignedToExpediente($expediente)) {
            return false;
        }

        return $user->isCoordinatorOf($expediente)
            || $user->isTutorOf($expediente)
            || ($user->isFacilitatorOf($expediente)
                && (int) $user->id === (int) $anexo->subido_por);
    }
}
