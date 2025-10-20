<?php

namespace App\Services;

use App\Models\Expediente;
use Illuminate\Support\Collection;

class ExpedienteStateValidator
{
    /**
     * Requisitos mínimos para cerrar un expediente:
     * - Debe existir al menos una sesión validada.
     * - No deben existir sesiones con observaciones pendientes de validar.
     * - Todos los consentimientos marcados como requeridos deben estar aceptados y contar con su archivo firmado.
     *
     * @return Collection<int, string> Mensajes de error si alguna regla no se cumple.
     */
    public function validateClosureRequirements(Expediente $expediente): Collection
    {
        $errores = collect();

        $tieneSesionValidada = $expediente
            ->sesiones()
            ->where('status_revision', 'validada')
            ->exists();

        if (! $tieneSesionValidada) {
            $errores->push('El expediente debe tener al menos una sesión validada antes de cerrarse.');
        }

        $tieneObservacionesAbiertas = $expediente
            ->sesiones()
            ->where('status_revision', 'observada')
            ->exists();

        if ($tieneObservacionesAbiertas) {
            $errores->push('No se puede cerrar el expediente con sesiones observadas pendientes de validación.');
        }

        $tieneConsentimientosPendientes = $expediente
            ->consentimientos()
            ->where('requerido', true)
            ->where(function ($query) {
                $query->where('aceptado', false)->orWhereNull('archivo_path');
            })
            ->exists();

        if ($tieneConsentimientosPendientes) {
            $errores->push('Todos los consentimientos requeridos deben estar aceptados y contar con su archivo firmado antes de cerrar el expediente.');
        }

        return $errores;
    }
}
