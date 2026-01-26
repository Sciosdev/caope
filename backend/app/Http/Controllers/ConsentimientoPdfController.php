<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\Parametro;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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
        $firmaAutografa = $this->resolveFirmaAutografaPayload($expediente);

        return [
            'expediente' => $expediente,
            'consentimientos' => $consentimientos,
            'fechaEmision' => Carbon::now(),
            'logoPath' => $logoSources['logoPath'],
            'logoDataUri' => $logoSources['logoDataUri'],
            'logoSrc' => $logoSources['logoSrc'],
            'textoIntroduccion' => (string) Parametro::obtener('consentimientos.texto_introduccion', ''),
            'textoCierre' => (string) Parametro::obtener('consentimientos.texto_cierre', ''),
            'firmaAutografaDataUri' => $firmaAutografa['dataUri'],
            'firmaAutografaNombre' => $firmaAutografa['nombre'],
        ];
    }

    private function resolveFirmaAutografaPayload(Expediente $expediente): array
    {
        $path = $expediente->consentimientos_observaciones_path;

        if (! $path) {
            return ['dataUri' => '', 'nombre' => ''];
        }

        $disk = config('filesystems.private_default', 'private');
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return ['dataUri' => '', 'nombre' => basename($path)];
        }

        $mime = (string) ($storage->mimeType($path) ?: '');

        if (! str_starts_with($mime, 'image/')) {
            return ['dataUri' => '', 'nombre' => basename($path)];
        }

        $contents = $storage->get($path);

        if ($contents === false) {
            return ['dataUri' => '', 'nombre' => basename($path)];
        }

        return [
            'dataUri' => sprintf('data:%s;base64,%s', $mime, base64_encode($contents)),
            'nombre' => basename($path),
        ];
    }

    private function resolveLogoSources(): array
    {
        $logoConfigurado = (string) Parametro::obtener(
            'consentimientos.logo_path',
            'assets/images/consentimientos/escudo-unam.png',
        );
        $logoConfigurado = trim($logoConfigurado);

        $logoUrl = $this->normalizeLogoUrl($logoConfigurado);

        if ($logoUrl !== null) {
            $remoteDataUri = $this->resolveRemoteLogoDataUri($logoUrl);

            if ($remoteDataUri === '') {
                return $this->resolveLocalDefaultLogo();
            }

            return [
                'logoPath' => '',
                'logoDataUri' => $remoteDataUri,
                'logoSrc' => $remoteDataUri !== '' ? $remoteDataUri : $logoUrl,
            ];
        }

        return $this->resolveLocalLogoSources($logoConfigurado);
    }

    private function resolveLocalDefaultLogo(): array
    {
        $defaultPath = public_path('assets/images/consentimientos/escudo-unam.png');
        $defaultDataUri = $this->resolveLogoDataUri($defaultPath);

        return [
            'logoPath' => $defaultPath,
            'logoDataUri' => $defaultDataUri,
            'logoSrc' => $defaultDataUri !== ''
                ? $defaultDataUri
                : asset('assets/images/consentimientos/escudo-unam.png'),
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
        try {
            $response = Http::timeout(5)->get($url);
        } catch (\Throwable $exception) {
            return '';
        }

        if (! $response->successful()) {
            return '';
        }

        $mime = $response->header('Content-Type') ?: 'image/png';

        return sprintf('data:%s;base64,%s', $mime, base64_encode($response->body()));
    }

    private function normalizeLogoUrl(string $logoConfigurado): ?string
    {
        if ($logoConfigurado === '') {
            return null;
        }

        if (filter_var($logoConfigurado, FILTER_VALIDATE_URL)) {
            return $logoConfigurado;
        }

        if (str_contains($logoConfigurado, '://')) {
            return null;
        }

        if (! preg_match('/^[\\w.-]+(?::\\d+)?\\//', $logoConfigurado)) {
            return null;
        }

        $httpsUrl = 'https://' . $logoConfigurado;

        if (filter_var($httpsUrl, FILTER_VALIDATE_URL)) {
            return $httpsUrl;
        }

        $httpUrl = 'http://' . $logoConfigurado;

        return filter_var($httpUrl, FILTER_VALIDATE_URL) ? $httpUrl : null;
    }

    private function resolveLocalLogoSources(string $logoConfigurado): array
    {
        $logoConfigurado = ltrim($logoConfigurado, '/');

        if ($logoConfigurado === '') {
            return $this->resolveLocalDefaultLogo();
        }

        $candidates = [$logoConfigurado];

        if (str_starts_with($logoConfigurado, 'storage/')) {
            $candidates[] = ltrim(substr($logoConfigurado, strlen('storage/')), '/');
        }

        foreach ($candidates as $candidate) {
            $publicPath = public_path($candidate);

            if (is_file($publicPath)) {
                $dataUri = $this->resolveLogoDataUri($publicPath);

                return [
                    'logoPath' => $publicPath,
                    'logoDataUri' => $dataUri,
                    'logoSrc' => $dataUri !== '' ? $dataUri : asset($candidate),
                ];
            }

            $storagePath = storage_path('app/public/' . $candidate);

            if (is_file($storagePath)) {
                $dataUri = $this->resolveLogoDataUri($storagePath);
                $assetPath = str_starts_with($candidate, 'storage/')
                    ? $candidate
                    : 'storage/' . $candidate;

                return [
                    'logoPath' => $storagePath,
                    'logoDataUri' => $dataUri,
                    'logoSrc' => $dataUri !== '' ? $dataUri : asset($assetPath),
                ];
            }
        }

        return $this->resolveLocalDefaultLogo();
    }
}
