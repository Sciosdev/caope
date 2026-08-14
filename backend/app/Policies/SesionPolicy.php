<?php

namespace App\Policies;

use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SesionPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Sesion $sesion): bool
    {
        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        $expediente = $sesion->expediente;

        if ($expediente && $user->isCoordinatorOf($expediente)) {
            return true;
        }

        if ($this->isTutor($user, $expediente)) {
            return true;
        }

        if ($user->hasRole('alumno')
            && $expediente
            && $user->isFacilitatorOf($expediente)
            && (int) $sesion->realizada_por === (int) $user->id) {
            return true;
        }

        return false;
    }

    public function create(User $user, Expediente $expediente): bool
    {
        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        if ($user->isCoordinatorOf($expediente)) {
            return true;
        }

        if ($user->hasRole('docente') && $this->isTutor($user, $expediente)) {
            return true;
        }

        return $user->isFacilitatorOf($expediente)
            && $expediente->estado !== 'cerrado';
    }

    public function update(User $user, Sesion $sesion): bool
    {
        if ($sesion->status_revision === 'validada') {
            return $user->hasGlobalExpedienteAccess()
                || ($sesion->expediente && $user->isCoordinatorOf($sesion->expediente));
        }

        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        if ($sesion->expediente && $user->isCoordinatorOf($sesion->expediente)) {
            return true;
        }

        if ($this->isTutor($user, $sesion->expediente)) {
            return true;
        }

        return $user->hasRole('alumno')
            && $sesion->expediente
            && $user->isFacilitatorOf($sesion->expediente)
            && (int) $sesion->realizada_por === (int) $user->id
            && $sesion->status_revision !== 'validada';
    }

    public function delete(User $user, Sesion $sesion): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if ($user->hasRole('alumno')
            && $sesion->expediente
            && $user->isFacilitatorOf($sesion->expediente)
            && (int) $sesion->realizada_por === (int) $user->id) {
            return $sesion->status_revision === 'pendiente';
        }

        return false;
    }

    public function validate(User $user, Sesion $sesion): bool
    {
        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        if ($sesion->expediente && $user->isCoordinatorOf($sesion->expediente)) {
            return true;
        }

        return $this->isTutor($user, $sesion->expediente);
    }

    public function observe(User $user, Sesion $sesion): bool
    {
        if ($user->hasGlobalExpedienteAccess()) {
            return true;
        }

        if ($sesion->expediente && $user->isCoordinatorOf($sesion->expediente)) {
            return true;
        }

        return $this->isTutor($user, $sesion->expediente);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('admin') || $user->isApprovedPaps();
    }

    private function isTutor(User $user, ?Expediente $expediente): bool
    {
        return $expediente !== null
            && $user->isTutorOf($expediente);
    }
}
