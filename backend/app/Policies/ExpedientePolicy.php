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
        return $user->can('expedientes.view');
    }

    public function view(User $user, Expediente $expediente): bool
    {
        if ($this->isAdmin($user) || $this->canManage($user)) {
            return true;
        }

        if ($user->hasRole('docente') && $expediente->tutor_id === $user->id) {
            return true;
        }

        if ($user->hasRole('alumno') && $expediente->creado_por === $user->id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $user->hasRole('alumno');
    }

    public function update(User $user, Expediente $expediente): bool
    {
        if ($this->isAdmin($user) || $this->canManage($user)) {
            return true;
        }

        if ($user->hasRole('docente') && $expediente->tutor_id === $user->id) {
            return $expediente->estado !== 'cerrado';
        }

        if ($user->hasRole('alumno') && $expediente->creado_por === $user->id) {
            return $expediente->estado !== 'cerrado';
        }

        return false;
    }

    public function delete(User $user, Expediente $expediente): bool
    {
        return $this->isAdmin($user);
    }

    public function changeState(User $user, Expediente $expediente): bool
    {
        if ($this->isAdmin($user) || $this->canManage($user)) {
            return true;
        }

        if ($user->hasRole('docente') && $expediente->tutor_id === $user->id) {
            return true;
        }

        return false;
    }

    public function viewFullName(User $user, Expediente $expediente): bool
    {
        return $this->view($user, $expediente);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }

    private function canManage(User $user): bool
    {
        return $user->can('expedientes.manage');
    }
}
