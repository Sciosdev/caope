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
        if ($this->isAdmin($user) || $this->canManage($user)) {
            return true;
        }

        $expediente = $anexo->expediente;

        if ($user->hasRole('docente') && $expediente && $expediente->tutor_id === $user->id) {
            return true;
        }

        if ($user->hasRole('alumno') && $expediente && $expediente->creado_por === $user->id) {
            return true;
        }

        return $user->id === $anexo->subido_por;
    }

    public function create(User $user, Expediente $expediente): bool
    {
        if ($this->isAdmin($user) || $this->canManage($user)) {
            return true;
        }

        if ($user->hasRole('docente') && $expediente->tutor_id === $user->id) {
            return true;
        }

        return $user->hasRole('alumno') && $expediente->creado_por === $user->id;
    }

    public function delete(User $user, Anexo $anexo): bool
    {
        if ($this->isAdmin($user) || $this->canManage($user)) {
            return true;
        }

        if ($user->hasRole('docente') && $anexo->expediente && $anexo->expediente->tutor_id === $user->id) {
            return true;
        }

        return $user->id === $anexo->subido_por;
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
