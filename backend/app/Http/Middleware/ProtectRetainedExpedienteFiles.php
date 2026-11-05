<?php

namespace App\Http\Middleware;

use App\Models\Anexo;
use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\Sesion;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProtectRetainedExpedienteFiles
{
    public function handle(Request $request, Closure $next): Response
    {
        $expediente = $this->resolveExpediente($request);
        if (! $expediente?->retention_until?->isFuture()) {
            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();
        $consentimiento = $request->route('consentimiento');

        if (
            $routeName === 'expedientes.consentimientos.update'
            && $consentimiento instanceof Consentimiento
            && filled($consentimiento->archivo_path)
            && $request->hasFile('archivo')
        ) {
            $this->deny('archivo', $expediente);
        }

        if (
            $routeName === 'consentimientos.upload'
            && $consentimiento instanceof Consentimiento
            && filled($consentimiento->archivo_path)
        ) {
            $this->deny('archivo', $expediente);
        }

        if (
            $routeName === 'expedientes.consentimientos.observaciones'
            && filled($expediente->consentimientos_observaciones_path)
            && ($request->hasFile('observaciones_archivo') || $request->boolean('observaciones_archivo_eliminar'))
        ) {
            $this->deny('observaciones_archivo', $expediente);
        }

        if (
            $routeName === 'expedientes.sesiones.update'
            && collect($request->input('adjuntos_eliminar', []))->filter()->isNotEmpty()
        ) {
            $this->deny('adjuntos_eliminar', $expediente);
        }

        return $next($request);
    }

    private function resolveExpediente(Request $request): ?Expediente
    {
        $expediente = $request->route('expediente');
        if ($expediente instanceof Expediente) {
            return $expediente;
        }

        $consentimiento = $request->route('consentimiento');
        if ($consentimiento instanceof Consentimiento) {
            return $consentimiento->expediente;
        }

        $anexo = $request->route('anexo');
        if ($anexo instanceof Anexo) {
            return $anexo->expediente;
        }

        $sesion = $request->route('sesion');

        return $sesion instanceof Sesion ? $sesion->expediente : null;
    }

    private function deny(string $field, Expediente $expediente): never
    {
        throw ValidationException::withMessages([
            $field => sprintf(
                'Este archivo forma parte de la retención obligatoria del expediente hasta %s y no puede reemplazarse ni eliminarse.',
                $expediente->retention_until->format('d/m/Y H:i')
            ),
        ]);
    }
}
