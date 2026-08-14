<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\SesionAdjunto;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SesionAdjuntoController extends Controller
{
    public function __invoke(
        Expediente $expediente,
        Sesion $sesion,
        SesionAdjunto $adjunto,
    ): StreamedResponse {
        abort_unless((int) $sesion->expediente_id === (int) $expediente->getKey(), 404);
        abort_unless((int) $adjunto->sesion_id === (int) $sesion->getKey(), 404);

        $this->authorize('view', $sesion);

        $disk = $adjunto->disk ?: 'public';
        abort_unless(Storage::disk($disk)->exists($adjunto->ruta), 404);

        return Storage::disk($disk)->download($adjunto->ruta, $adjunto->nombre_original);
    }
}
