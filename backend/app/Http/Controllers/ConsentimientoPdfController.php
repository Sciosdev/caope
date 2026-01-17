<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use Illuminate\Support\Facades\Storage;

class ConsentimientoPdfController extends Controller
{
    /**
     * Devuelve el documento de consentimiento cargado para un expediente.
     */
    public function __invoke(Expediente $expediente)
    {
        $this->authorize('view', $expediente);

        $disk = config('filesystems.private_default', 'private');
        $path = $expediente->consentimientos_observaciones_path;

        if (! $path || ! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($path);
    }
}
