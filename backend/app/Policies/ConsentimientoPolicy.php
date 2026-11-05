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
        $expediente = $consentimiento->expediente;
        if (! $expediente) {
            return false;
        }

        if ($expediente->trashed()) {
            return $this->isAdmin($user);
        }

        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        return $user->isAssignedToExpediente($expediente);
    }

    public function update(User $user, Consentimiento $consentimiento): bool
    {
        $expediente = $consentimiento->expediente;
        if (! $expediente || $expediente->trashed()) {
            return false;
        }

        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        if ($user->isCoordinatorOf($expediente)) {
            return true;
        }

        return $expediente->estado !== 'cerrado'
            && $user->isAssignedToExpediente($expediente);
    }

    public function upload(User $user, Consentimiento $consentimiento): bool
    {
        if ($consentimiento->expediente?->retention_until?->isFuture() && filled($consentimiento->archivo_path)) {
            return false;
        }

        return $this->update($user, $consentimiento);
    }

    public function delete(User $user, Consentimiento $consentimiento): bool
    {
        if ($consentimiento->expediente?->retention_until?->isFuture()) {
            return false;
        }

        return $this->update($user, $consentimiento);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('admin') || $user->isApprovedPaps();
    }
}
