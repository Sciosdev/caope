<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\SesionAdjunto;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SesionAdjuntoController extends Controller
{
    public function __invoke(
        Expediente $expediente,
        Sesion $sesion,
        SesionAdjunto $adjunto,
    ): StreamedResponse {
        $disk = $this->readableDisk($expediente, $sesion, $adjunto);

        return $disk->download($adjunto->ruta, $this->downloadFilename($adjunto), [
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(Expediente $expediente, Sesion $sesion, SesionAdjunto $adjunto): Response
    {
        $disk = $this->readableDisk($expediente, $sesion, $adjunto);
        $mimeType = $disk->mimeType($adjunto->ruta) ?: 'application/octet-stream';

        if (! in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            return response()->view('sesiones.adjuntos.preview-unavailable', compact('expediente', 'sesion', 'adjunto'), 200, [
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return $disk->response($adjunto->ruta, $this->downloadFilename($adjunto), [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self' data:",
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function readableDisk(Expediente $expediente, Sesion $sesion, SesionAdjunto $adjunto): FilesystemAdapter
    {
        abort_unless((int) $sesion->expediente_id === (int) $expediente->getKey(), 404);
        abort_unless((int) $adjunto->sesion_id === (int) $sesion->getKey(), 404);

        $this->authorize('view', $sesion);

        $disk = Storage::disk($adjunto->disk ?: 'public');
        abort_unless($adjunto->ruta && $disk->exists($adjunto->ruta), 404);

        return $disk;
    }

    private function downloadFilename(SesionAdjunto $adjunto): string
    {
        $filename = trim(str_replace(
            ['/', '\\'],
            '-',
            (string) preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $adjunto->nombre_original)
        ));

        return $filename === '' ? basename($adjunto->ruta) : $filename;
    }
}
