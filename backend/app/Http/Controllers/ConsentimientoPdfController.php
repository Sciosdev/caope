<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\Parametro;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

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

        $logoSources = $this->resolveLogoSources();

        return [
            'expediente' => $expediente,
            'consentimientos' => $consentimientos,
            'fechaEmision' => Carbon::now(),
            'logoPath' => $logoSources['logoPath'],
            'logoDataUri' => $logoSources['logoDataUri'],
            'logoSrc' => $logoSources['logoSrc'],
            'textoIntroduccion' => (string) Parametro::obtener('consentimientos.texto_introduccion', ''),
            'textoCierre' => (string) Parametro::obtener('consentimientos.texto_cierre', ''),
        ];
    }

    private function resolveLogoSources(): array
    {
        $logoConfigurado = (string) Parametro::obtener(
            'consentimientos.logo_path',
            'assets/images/consentimientos/escudo-unam.png',
        );
        $logoConfigurado = trim($logoConfigurado);

        if ($logoConfigurado !== '' && filter_var($logoConfigurado, FILTER_VALIDATE_URL)) {
            $remoteDataUri = $this->resolveRemoteLogoDataUri($logoConfigurado);

            return [
                'logoPath' => '',
                'logoDataUri' => $remoteDataUri,
                'logoSrc' => $remoteDataUri !== '' ? $remoteDataUri : $logoConfigurado,
            ];
        }

        $logoConfigurado = ltrim($logoConfigurado, '/');
        $logoPath = public_path($logoConfigurado);
        $logoDataUri = $this->resolveLogoDataUri($logoPath);

        if ($logoDataUri === '') {
            $assetSrc = asset($logoConfigurado);

            return [
                'logoPath' => $logoPath,
                'logoDataUri' => '',
                'logoSrc' => $assetSrc,
            ];
        }

        return [
            'logoPath' => $logoPath,
            'logoDataUri' => $logoDataUri,
            'logoSrc' => $logoDataUri,
        ];
    }

    private function resolveLogoDataUri(string $logoPath): string
    {
        if (! is_file($logoPath)) {
            return '';
        }

        $contents = file_get_contents($logoPath);

        if ($contents === false) {
            return '';
        }

        $mime = mime_content_type($logoPath) ?: 'image/png';

        if (! str_starts_with($mime, 'image/')) {
            return '';
        }

        return sprintf('data:%s;base64,%s', $mime, base64_encode($contents));
    }

    private function resolveRemoteLogoDataUri(string $url): string
    {
        $response = Http::timeout(5)->get($url);

        if (! $response->successful()) {
            return '';
        }

        $mime = $response->header('Content-Type') ?: 'image/png';

        return sprintf('data:%s;base64,%s', $mime, base64_encode($response->body()));
    }
}
