<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\Consentimiento;
use App\Models\Parametro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ConsentimientoUploadController extends Controller
{
    public function show(Consentimiento $consentimiento)
    {
        $this->authorize('view', $consentimiento);

        $disk = config('filesystems.private_default', 'private');

        if (! $consentimiento->archivo_path || ! Storage::disk($disk)->exists($consentimiento->archivo_path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($consentimiento->archivo_path);
    }

    public function store(Request $request, Consentimiento $consentimiento): RedirectResponse
    {
        $this->authorize('upload', $consentimiento);

        $errorBag = sprintf('consentimientoUpload-%s', $consentimiento->id);

        $mimes = (string) Parametro::obtener('uploads.consentimientos.mimes', 'pdf,jpg,jpeg');
        $max = (int) Parametro::obtener('uploads.consentimientos.max', 5120);

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

        return Storage::disk($disk)->response($path);
    }

    public function storeObservaciones(Request $request, Expediente $expediente): RedirectResponse
    {
        $this->authorize('update', $expediente);

        $mimes = (string) Parametro::obtener('uploads.consentimientos.mimes', 'pdf,jpg,jpeg');
        $max = (int) Parametro::obtener('uploads.consentimientos.max', 5120);

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

        $expediente->forceFill([
            'consentimientos_observaciones' => $validated['observaciones'],
            'consentimientos_observaciones_path' => $path,
            'tutor_id' => $validated['tutor_id'] ?? null,
            'contacto_emergencia_nombre' => $validated['contacto_emergencia_nombre'] ?: null,
        ])->save();

        return redirect()
            ->route('expedientes.show', ['expediente' => $expediente, 'tab' => 'consentimientos'])
            ->with('status', 'Observaciones del expediente actualizadas correctamente.');
    }
}
