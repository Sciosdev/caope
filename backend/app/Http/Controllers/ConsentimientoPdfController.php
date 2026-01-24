<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\Parametro;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

class ConsentimientoPdfController extends Controller
{
    /**
     * Devuelve el documento de consentimiento generado para un expediente.
     */
    public function __invoke(Expediente $expediente)
    {
        $payload = $this->buildPayload($expediente);
        $payload['showActions'] = true;
        $payload['forcePrintStyles'] = false;

        return view('consentimientos.pdf', $payload);
    }

    public function download(Expediente $expediente)
    {
        $payload = $this->buildPayload($expediente);
        $payload['showActions'] = false;
        $payload['forcePrintStyles'] = true;

        $nombreArchivo = sprintf('consentimientos-%s.pdf', $expediente->no_control ?? $expediente->id);

        return Pdf::loadView('consentimientos.pdf', $payload)->download($nombreArchivo);
    }

    private function buildPayload(Expediente $expediente): array
    {
        $this->authorize('view', $expediente);

        $expediente->loadMissing(['alumno', 'tutor', 'coordinador']);
        $consentimientos = $expediente
            ->consentimientos()
            ->orderByDesc('requerido')
            ->orderBy('tratamiento')
            ->get();

        $logoPath = $this->resolveLogoPath();

        return [
            'expediente' => $expediente,
            'consentimientos' => $consentimientos,
            'fechaEmision' => Carbon::now(),
            'logoPath' => $logoPath,
            'textoIntroduccion' => (string) Parametro::obtener('consentimientos.texto_introduccion', ''),
            'textoCierre' => (string) Parametro::obtener('consentimientos.texto_cierre', ''),
        ];
    }

    private function resolveLogoPath(): string
    {
        $logoConfigurado = (string) Parametro::obtener(
            'consentimientos.logo_path',
            'assets/images/logo-mini-dark.png',
        );
        $logoConfigurado = ltrim($logoConfigurado, '/');
        $logoPath = public_path($logoConfigurado);

        if (! is_file($logoPath)) {
            return public_path('assets/images/logo-mini-dark.png');
        }

        return $logoPath;
    }
}
