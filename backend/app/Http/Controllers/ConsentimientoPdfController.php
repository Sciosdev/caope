<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\Parametro;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
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

        $expediente->loadMissing(['facilitador', 'tutor', 'coordinador']);
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

        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
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

        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return '';
        }

        return sprintf('data:%s;base64,%s', $mime, base64_encode($contents));
    }

    private function resolveLocalLogoSources(string $logoConfigurado): array
    {
        $logoConfigurado = ltrim($logoConfigurado, '/');

        if ($logoConfigurado === '') {
            return $this->resolveLocalDefaultLogo();
        }

        $allowedRoot = realpath(public_path('assets/images/consentimientos'));
        $resolvedPath = realpath(public_path($logoConfigurado));

        if (is_string($allowedRoot)
            && is_string($resolvedPath)
            && is_file($resolvedPath)
            && ($resolvedPath === $allowedRoot
                || str_starts_with($resolvedPath, $allowedRoot.DIRECTORY_SEPARATOR))) {
            $dataUri = $this->resolveLogoDataUri($resolvedPath);

            if ($dataUri !== '') {
                return [
                    'logoPath' => $resolvedPath,
                    'logoDataUri' => $dataUri,
                    'logoSrc' => $dataUri,
                ];
            }
        }

        return $this->resolveLocalDefaultLogo();
    }
}
