<?php

namespace App\Http\Controllers\Admin;

use App\Models\CatalogoEstrategia;
use App\Models\ConsultorioReservaSolicitud;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class CatalogoEstrategiaController extends CatalogoController
{
    protected string $modelClass = CatalogoEstrategia::class;

    protected string $routePrefix = 'admin.catalogos.estrategias';

    protected string $resourceName = 'Estrategia';

    protected string $resourceNamePlural = 'Estrategias';

    public function update(Request $request, int|string $id): RedirectResponse
    {
        $item = $this->findModel($id);
        $newName = $request->input('nombre');

        if (is_string($newName) && $newName !== $item->nombre && $this->hasPendingRequests($item->nombre)) {
            return Redirect::route($this->routePrefix.'.edit', $item)->withInput()->withErrors([
                'nombre' => __('Primero atiende las solicitudes pendientes que proponen esta estrategia antes de cambiar su nombre.'),
            ]);
        }

        return parent::update($request, $id);
    }

    public function forceDestroy(int|string $id): RedirectResponse
    {
        $item = $this->findModel($id);

        if ($this->hasPendingRequests($item->nombre)) {
            return Redirect::route($this->routePrefix.'.index')->withErrors([
                'catalogo' => __('Primero atiende las solicitudes pendientes que proponen esta estrategia antes de eliminarla.'),
            ]);
        }

        return parent::forceDestroy($id);
    }

    private function hasPendingRequests(string $name): bool
    {
        return ConsultorioReservaSolicitud::query()
            ->where('status', 'pendiente')
            ->where('payload->estrategia', $name)
            ->exists();
    }
}
