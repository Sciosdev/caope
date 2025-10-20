<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\Sesion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SesionController extends Controller
{
    public function index(Request $request, Expediente $expediente): View
    {
        $this->authorize('view', $expediente);

        $sesiones = $expediente->sesiones()
            ->with(['realizadaPor', 'validadaPor'])
            ->orderByDesc('fecha')
            ->paginate(10)
            ->withQueryString();

        return view('sesiones.index', [
            'expediente' => $expediente,
            'sesiones' => $sesiones,
        ]);
    }

    public function create(Request $request, Expediente $expediente): View
    {
        $this->authorize('create', [Sesion::class, $expediente]);

        $sesion = new Sesion([
            'fecha' => Carbon::today(),
        ]);

        return view('sesiones.create', [
            'expediente' => $expediente,
            'sesion' => $sesion,
        ]);
    }

    public function store(Request $request, Expediente $expediente): RedirectResponse
    {
        $this->authorize('create', [Sesion::class, $expediente]);

        $data = $this->validatedData($request);

        $sesion = $expediente->sesiones()->create($data + [
            'realizada_por' => $request->user()->id,
            'status_revision' => 'pendiente',
        ]);

        return redirect()
            ->route('expedientes.sesiones.show', [$expediente, $sesion])
            ->with('status', 'Sesión registrada correctamente.');
    }

    public function show(Expediente $expediente, Sesion $sesion): View
    {
        $this->ensureSesionBelongsToExpediente($expediente, $sesion);

        $this->authorize('view', $sesion);

        $sesion->load(['realizadaPor', 'validadaPor', 'expediente']);

        return view('sesiones.show', [
            'expediente' => $expediente,
            'sesion' => $sesion,
        ]);
    }

    public function edit(Expediente $expediente, Sesion $sesion): View
    {
        $this->ensureSesionBelongsToExpediente($expediente, $sesion);

        $this->authorize('update', $sesion);

        return view('sesiones.edit', [
            'expediente' => $expediente,
            'sesion' => $sesion,
        ]);
    }

    public function update(Request $request, Expediente $expediente, Sesion $sesion): RedirectResponse
    {
        $this->ensureSesionBelongsToExpediente($expediente, $sesion);

        $this->authorize('update', $sesion);

        $data = $this->validatedData($request);

        $sesion->fill($data);

        if ($sesion->status_revision !== 'pendiente') {
            $sesion->status_revision = 'pendiente';
            $sesion->validada_por = null;
        }

        $sesion->save();

        return redirect()
            ->route('expedientes.sesiones.show', [$expediente, $sesion])
            ->with('status', 'Sesión actualizada correctamente.');
    }

    public function destroy(Expediente $expediente, Sesion $sesion): RedirectResponse
    {
        $this->ensureSesionBelongsToExpediente($expediente, $sesion);

        $this->authorize('delete', $sesion);

        $sesion->delete();

        return redirect()
            ->route('expedientes.sesiones.index', $expediente)
            ->with('status', 'Sesión eliminada correctamente.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'fecha' => ['required', 'date'],
            'tipo' => ['required', 'string', 'max:60'],
            'referencia_externa' => ['nullable', 'string', 'max:120'],
            'nota' => ['required', 'string'],
        ]);
    }

    private function ensureSesionBelongsToExpediente(Expediente $expediente, Sesion $sesion): void
    {
        abort_if($sesion->expediente_id !== $expediente->id, 404);
    }
}
