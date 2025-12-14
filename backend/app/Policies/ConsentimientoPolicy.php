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
        if ($this->isAdmin($user) || $this->canManage($user)) {
            return true;
        }

        $expediente = $consentimiento->expediente;

        if ($user->hasRole('docente') && $expediente && $expediente->tutor_id === $user->id) {
            return true;
        }

        return $user->hasRole('alumno') && $expediente && $expediente->creado_por === $user->id;
    }

    public function update(User $user, Consentimiento $consentimiento): bool
    {
        if ($this->isAdmin($user) || $this->canManage($user)) {
            return true;
        }

        $expediente = $consentimiento->expediente;

        if (! $expediente || $expediente->estado === 'cerrado') {
            return false;
        }

        if ($user->hasRole('docente') && $expediente && $expediente->tutor_id === $user->id) {
            return true;
        }

        return $user->hasRole('alumno') && $expediente && $expediente->creado_por === $user->id;
    }

    public function upload(User $user, Consentimiento $consentimiento): bool
    {
        return $this->update($user, $consentimiento);
    }

    public function delete(User $user, Consentimiento $consentimiento): bool
    {
        return $this->update($user, $consentimiento);
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
