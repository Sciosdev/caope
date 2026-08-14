<?php

namespace App\Http\Controllers;

use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Support\Uploads\ConsentimientoUploadOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ConsentimientoUploadController extends Controller
{
    public function show(Consentimiento $consentimiento)
    {
        $this->authorize('view', $consentimiento);

        $disk = config('filesystems.private_default', 'private');

        if (! $consentimiento->archivo_path || ! Storage::disk($disk)->exists($consentimiento->archivo_path)) {
            abort(404);
        }

        return $this->protectedInlineResponse($disk, $consentimiento->archivo_path);
    }

    public function store(Request $request, Consentimiento $consentimiento): RedirectResponse
    {
        $this->authorize('upload', $consentimiento);

        $errorBag = sprintf('consentimientoUpload-%s', $consentimiento->id);

        $mimes = ConsentimientoUploadOptions::allowedExtensionsString();
        $max = ConsentimientoUploadOptions::maxKilobytes();

        $validated = $request->validateWithBag($errorBag, [
            'archivo' => ['required', 'file', 'mimes:'.$mimes, 'max:'.$max],
            'aceptado' => ['required', 'boolean'],
            'fecha' => ['nullable', 'date'],
        ]);

        $disk = config('filesystems.private_default', 'private');
        $file = $validated['archivo'];
        $directory = sprintf('expedientes/%s/consentimientos', $consentimiento->expediente_id ?? 'generales');
        $filename = sprintf('%s-%s.%s', $consentimiento->id, now()->format('YmdHis'), $file->getClientOriginalExtension());

        if ($consentimiento->archivo_path && Storage::disk($disk)->exists($consentimiento->archivo_path)) {
            Storage::disk($disk)->delete($consentimiento->archivo_path);
        }

        $storedPath = $file->storeAs($directory, $filename, $disk);

        $fecha = $validated['fecha']
            ? Carbon::parse($validated['fecha'])->startOfDay()
            : ($validated['aceptado'] ? now()->startOfDay() : null);

        $consentimiento->fill([
            'archivo_path' => $storedPath,
            'aceptado' => (bool) $validated['aceptado'],
            'fecha' => $fecha,
            'subido_por' => $request->user()->id,
        ])->save();

        $consentimiento->loadMissing('expediente');

        return redirect()
            ->route('expedientes.show', $consentimiento->expediente)
            ->with('status', 'Consentimiento actualizado correctamente.');
    }

    public function showObservaciones(Expediente $expediente)
    {
        $this->authorize('view', $expediente);

        $disk = config('filesystems.private_default', 'private');
        $path = $expediente->consentimientos_observaciones_path;

        if (! $path || ! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return $this->protectedInlineResponse($disk, $path);
    }

    public function storeObservaciones(Request $request, Expediente $expediente): RedirectResponse
    {
        $this->authorize('update', $expediente);

        $mimes = ConsentimientoUploadOptions::allowedExtensionsString();
        $max = ConsentimientoUploadOptions::maxKilobytes();

        $validated = $request->validate([
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'tutor_id' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'contacto_emergencia_nombre' => ['nullable', 'string', 'max:150'],
            'observaciones_archivo' => ['nullable', 'file', 'mimes:'.$mimes, 'max:'.$max],
            'observaciones_archivo_eliminar' => ['nullable', 'boolean'],
        ]);

        $disk = config('filesystems.private_default', 'private');
        $path = $expediente->consentimientos_observaciones_path;
        $file = $validated['observaciones_archivo'] ?? null;
        $deleteFile = (bool) ($validated['observaciones_archivo_eliminar'] ?? false);

        if ($deleteFile && $path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
            $path = null;
        }

        if ($file) {
            if ($path && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }

            $directory = sprintf('expedientes/%s/consentimientos/observaciones', $expediente->id ?? 'generales');
            $filename = sprintf('observaciones-%s.%s', now()->format('YmdHis'), $file->getClientOriginalExtension());
            $path = $file->storeAs($directory, $filename, $disk);
        }

        $updates = [
            'consentimientos_observaciones' => $validated['observaciones'] ?? null,
            'consentimientos_observaciones_path' => $path,
            'contacto_emergencia_nombre' => ($validated['contacto_emergencia_nombre'] ?? null) ?: null,
        ];

        $user = $request->user();
        if ($user?->hasGlobalExpedienteAccess() || $user?->isCoordinatorOf($expediente)) {
            $updates['tutor_id'] = $validated['tutor_id'] ?? null;
        }

        $expediente->forceFill($updates)->save();

        return redirect()
            ->route('expedientes.show', ['expediente' => $expediente, 'tab' => 'consentimientos'])
            ->with('status', 'Observaciones del expediente actualizadas correctamente.');
    }

    private function protectedInlineResponse(string $disk, string $path)
    {
        $storage = Storage::disk($disk);
        $mime = (string) ($storage->mimeType($path) ?: 'application/octet-stream');
        $name = basename($path);
        $inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);

        return $storage->response($path, $name, [
            'Content-Type' => $mime,
            'Content-Disposition' => HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $name),
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
