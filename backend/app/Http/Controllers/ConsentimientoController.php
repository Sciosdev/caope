<?php

namespace App\Http\Controllers;

use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\Parametro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ConsentimientoController extends Controller
{
    public function store(Request $request, Expediente $expediente): RedirectResponse
    {
        $this->authorize('update', $expediente);

        $mimes = (string) Parametro::obtener('uploads.consentimientos.mimes', 'pdf,jpg,jpeg');
        $max = (int) Parametro::obtener('uploads.consentimientos.max', 5120);

        $validated = $request->validate([
            'tipo' => ['required', 'string', 'max:120'],
            'requerido' => ['required', 'boolean'],
            'aceptado' => ['required', 'boolean'],
            'fecha' => ['nullable', 'date'],
            'archivo' => ['nullable', 'file', 'mimes:'.$mimes, 'max:'.$max],
            'contacto_emergencia_nombre' => ['nullable', 'string', 'max:150'],
        ]);

        $consentimiento = new Consentimiento();
        $consentimiento->expediente()->associate($expediente);

        $this->updateExpedienteTestigo($expediente, $validated);
        $this->fillConsentimiento($consentimiento, $validated, $request);

        return redirect()
            ->route('expedientes.show', ['expediente' => $expediente, 'tab' => 'consentimientos'])
            ->with('status', 'Consentimiento guardado correctamente.');
    }

    public function update(Request $request, Expediente $expediente, Consentimiento $consentimiento): RedirectResponse
    {
        if ($consentimiento->expediente_id !== $expediente->id) {
            abort(404);
        }

        $this->authorize('update', $consentimiento);

        $errorBag = sprintf('consentimientoEdit-%s', $consentimiento->id);

        $mimes = (string) Parametro::obtener('uploads.consentimientos.mimes', 'pdf,jpg,jpeg');
        $max = (int) Parametro::obtener('uploads.consentimientos.max', 5120);

        $validated = $request->validateWithBag($errorBag, [
            'tipo' => ['required', 'string', 'max:120'],
            'requerido' => ['required', 'boolean'],
            'aceptado' => ['required', 'boolean'],
            'fecha' => ['nullable', 'date'],
            'archivo' => ['nullable', 'file', 'mimes:'.$mimes, 'max:'.$max],
            'contacto_emergencia_nombre' => ['nullable', 'string', 'max:150'],
        ]);

        $this->updateExpedienteTestigo($expediente, $validated);
        $this->fillConsentimiento($consentimiento, $validated, $request);

        return redirect()
            ->route('expedientes.show', ['expediente' => $expediente, 'tab' => 'consentimientos'])
            ->with('status', 'Consentimiento actualizado correctamente.');
    }

    public function destroy(Expediente $expediente, Consentimiento $consentimiento): RedirectResponse
    {
        if ($consentimiento->expediente_id !== $expediente->id) {
            abort(404);
        }

        $this->authorize('delete', $consentimiento);

        $disk = config('filesystems.private_default', 'private');

        if ($consentimiento->archivo_path && Storage::disk($disk)->exists($consentimiento->archivo_path)) {
            Storage::disk($disk)->delete($consentimiento->archivo_path);
        }

        $consentimiento->delete();

        return redirect()
            ->route('expedientes.show', ['expediente' => $expediente, 'tab' => 'consentimientos'])
            ->with('status', 'Consentimiento eliminado correctamente.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fillConsentimiento(Consentimiento $consentimiento, array $validated, Request $request): void
    {
        $disk = config('filesystems.private_default', 'private');
        $file = Arr::get($validated, 'archivo');

        $fecha = $validated['fecha'] ?? null;
        $fecha = $fecha ? Carbon::parse($fecha)->startOfDay() : null;

        if ($validated['aceptado'] && ! $fecha) {
            $fecha = now()->startOfDay();
        }

        if ($file) {
            $directory = sprintf('expedientes/%s/consentimientos', $consentimiento->expediente_id ?? 'generales');
            $filename = sprintf('%s-%s.%s', $consentimiento->id ?? 'nuevo', now()->format('YmdHis'), $file->getClientOriginalExtension());

            if ($consentimiento->archivo_path && Storage::disk($disk)->exists($consentimiento->archivo_path)) {
                Storage::disk($disk)->delete($consentimiento->archivo_path);
            }

            $storedPath = $file->storeAs($directory, $filename, $disk);

            $consentimiento->archivo_path = $storedPath;
            $consentimiento->subido_por = $request->user()?->id;
        }

        $consentimiento->fill([
            'tratamiento' => $validated['tipo'],
            'requerido' => (bool) $validated['requerido'],
            'aceptado' => (bool) $validated['aceptado'],
            'fecha' => $fecha,
        ])->save();
    }

    private function updateExpedienteTestigo(Expediente $expediente, array $validated): void
    {
        if (! array_key_exists('contacto_emergencia_nombre', $validated)) {
            return;
        }

        $expediente->forceFill([
            'contacto_emergencia_nombre' => $validated['contacto_emergencia_nombre'] ?: null,
        ])->save();
    }
}
