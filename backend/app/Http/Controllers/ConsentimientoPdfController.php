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

        $expediente->loadMissing(['tutor', 'coordinador']);
        $consentimientos = $expediente
            ->consentimientos()
            ->orderByDesc('requerido')
            ->orderBy('tratamiento')
            ->get();

        $pdf = Pdf::loadView('consentimientos.pdf', [
            'expediente' => $expediente,
            'consentimientos' => $consentimientos,
            'fechaEmision' => Carbon::now(),
            'textoIntroduccion' => (string) Parametro::obtener('consentimientos.texto_introduccion', ''),
            'textoCierre' => (string) Parametro::obtener('consentimientos.texto_cierre', ''),
        ])->setPaper('letter');

        return $pdf->stream(sprintf('expediente-%s-consentimientos.pdf', $expediente->no_control));
    }
}
