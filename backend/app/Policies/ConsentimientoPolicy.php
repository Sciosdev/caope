<?php

namespace App\Policies;

use App\Models\Consentimiento;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConsentimientoPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Consentimiento $consentimiento): bool
    {
        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        $expediente = $consentimiento->expediente;

        return $expediente !== null && $user->isAssignedToExpediente($expediente);
    }

    public function update(User $user, Consentimiento $consentimiento): bool
    {
        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        $expediente = $consentimiento->expediente;

        if (! $expediente) {
            return false;
        }

        if ($user->isCoordinatorOf($expediente)) {
            return true;
        }

        return $expediente->estado !== 'cerrado'
            && $user->isAssignedToExpediente($expediente);
    }

    public function upload(User $user, Consentimiento $consentimiento): bool
    {
        return $this->update($user, $consentimiento);
    }

    public function delete(User $user, Consentimiento $consentimiento): bool
    {
        return $this->update($user, $consentimiento);
    }
}
