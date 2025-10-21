<?php

namespace App\Http\Controllers;

use App\Models\Consentimiento;
use App\Models\Parametro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ConsentimientoUploadController extends Controller
{
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
}
