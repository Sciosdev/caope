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
        $this->authorize('view', $expediente);

        $expediente->loadMissing(['alumno', 'tutor', 'coordinador']);
        $consentimientos = $expediente
            ->consentimientos()
            ->orderByDesc('requerido')
            ->orderBy('tratamiento')
            ->get();

        $logoPath = $this->resolveLogoPath();

        $pdf = Pdf::loadView('consentimientos.pdf', [
            'expediente' => $expediente,
            'consentimientos' => $consentimientos,
            'fechaEmision' => Carbon::now(),
            'logoPath' => $logoPath,
            'textoIntroduccion' => (string) Parametro::obtener('consentimientos.texto_introduccion', ''),
            'textoCierre' => (string) Parametro::obtener('consentimientos.texto_cierre', ''),
        ])->setPaper('letter');

        return $pdf->stream(sprintf('expediente-%s-consentimientos.pdf', $expediente->no_control));
    }

    private function resolveLogoPath(): string
    {
        $logoConfigurado = (string) Parametro::obtener(
            'consentimientos.logo_path',
            'assets/images/others/logo-placeholder.png',
        );
        $logoConfigurado = ltrim($logoConfigurado, '/');
        $logoPath = public_path($logoConfigurado);

        if (! is_file($logoPath)) {
            return public_path('assets/images/others/logo-placeholder.png');
        }

        return $logoPath;
    }
}
