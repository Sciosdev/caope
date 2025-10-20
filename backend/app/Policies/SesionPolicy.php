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
        if ($this->isAdmin($user) || $this->canManage($user)) {
            return true;
        }

        $expediente = $sesion->expediente;

        if ($this->isTutor($user, $expediente)) {
            return true;
        }

        if ($user->hasRole('alumno') && $sesion->realizada_por === $user->id) {
            return true;
        }

        return false;
    }

    public function create(User $user, Expediente $expediente): bool
    {
        if ($this->isAdmin($user) || $this->canManage($user)) {
            return true;
        }

        if ($user->hasRole('docente') && $this->isTutor($user, $expediente)) {
            return true;
        }

        return $user->hasRole('alumno')
            && $expediente->creado_por === $user->id
            && $expediente->estado !== 'cerrado';
    }

    public function update(User $user, Sesion $sesion): bool
    {
        if ($this->isAdmin($user) || $this->canManage($user)) {
            return true;
        }

        if ($this->isTutor($user, $sesion->expediente)) {
            return true;
        }

        return $user->hasRole('alumno')
            && $sesion->realizada_por === $user->id
            && $sesion->status_revision !== 'validada';
    }

    public function delete(User $user, Sesion $sesion): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if ($user->hasRole('alumno') && $sesion->realizada_por === $user->id) {
            return $sesion->status_revision === 'pendiente';
        }

        return false;
    }

    public function validate(User $user, Sesion $sesion): bool
    {
        if ($this->isAdmin($user) || $this->canManage($user)) {
            return true;
        }

        return $this->isTutor($user, $sesion->expediente);
    }

    public function observe(User $user, Sesion $sesion): bool
    {
        if ($this->isAdmin($user) || $this->canManage($user)) {
            return true;
        }

        return $this->isTutor($user, $sesion->expediente);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }

    private function canManage(User $user): bool
    {
        return $user->can('expedientes.manage');
    }

    private function isTutor(User $user, ?Expediente $expediente): bool
    {
        return $expediente !== null && $expediente->tutor_id === $user->id;
    }
}
