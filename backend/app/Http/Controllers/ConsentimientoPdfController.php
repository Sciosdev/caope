<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class ConsentimientoPdfController extends Controller
{
    /**
     * Genera un archivo PDF con los consentimientos registrados para un expediente.
     */
    public function __invoke(Expediente $expediente): Response
    {
        $this->authorize('view', $expediente);

        $expediente->load([
            'consentimientos' => fn ($query) => $query
                ->orderByDesc('requerido')
                ->orderBy('tratamiento'),
            'tutor',
            'coordinador',
        ]);

        $data = [
            'expediente' => $expediente,
            'consentimientos' => $expediente->consentimientos,
            'fechaEmision' => Carbon::now(),
        ];

        $pdf = Pdf::loadView('consentimientos.pdf', $data)
            ->setPaper('letter');

        $filename = sprintf(
            'expediente-%s-consentimientos.pdf',
            $expediente->no_control ?: $expediente->getKey()
        );

        return $pdf->stream($filename);
    }
}
