<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class CatalogoCubiculoController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin|paps');
    }

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.catalogos.consultorios.index');
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.catalogos.consultorios.create');
    }

    public function edit(int|string $id): RedirectResponse
    {
        // An old cubiculo ID can identify a different canonical room. Never alias it.
        return redirect()->route('admin.catalogos.consultorios.index')
            ->with('status', 'Los espacios se unificaron en Cubículos. Selecciona el espacio por su nombre para editarlo.');
    }

    public function store(): never
    {
        $this->obsoleteForm();
    }

    public function update(int|string $id): never
    {
        $this->obsoleteForm();
    }

    public function destroy(int|string $id): never
    {
        $this->obsoleteForm();
    }

    public function toggleActive(int|string $id): never
    {
        $this->obsoleteForm();
    }

    public function forceDestroy(int|string $id): never
    {
        $this->obsoleteForm();
    }

    private function obsoleteForm(): never
    {
        abort(409, 'Este formulario pertenece al catálogo anterior. Abre Administración → Cubículos y vuelve a seleccionar el espacio; no se guardaron cambios.');
    }
}
