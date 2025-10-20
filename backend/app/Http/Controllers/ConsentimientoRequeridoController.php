<?php

namespace App\Http\Controllers;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTratamiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConsentimientoRequeridoController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:consentimientos.manage');
    }

    public function index()
    {
        $carreras = CatalogoCarrera::query()
            ->with(['tratamientosRequeridos' => fn ($query) => $query->select('catalogo_tratamientos.id')])
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $tratamientos = CatalogoTratamiento::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $requeridos = $carreras->mapWithKeys(function (CatalogoCarrera $carrera) {
            return [
                $carrera->id => $carrera->tratamientosRequeridos
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all(),
            ];
        })->toArray();

        return view('consentimientos.requeridos.index', [
            'carreras' => $carreras,
            'tratamientos' => $tratamientos,
            'requeridos' => $requeridos,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'requeridos' => ['nullable', 'array'],
            'requeridos.*' => ['array'],
            'requeridos.*.*' => ['integer', Rule::exists('catalogo_tratamientos', 'id')],
        ]);

        $carreras = CatalogoCarrera::query()
            ->orderBy('id')
            ->get();

        $tratamientosValidos = CatalogoTratamiento::pluck('id')->map(fn ($id) => (int) $id);

        $seleccionados = collect($validated['requeridos'] ?? [])
            ->mapWithKeys(fn ($ids, $carreraId) => [
                (string) $carreraId => collect($ids)
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $tratamientosValidos->contains($id))
                    ->unique()
                    ->values()
                    ->all(),
            ]);

        $carreraIds = $carreras->pluck('id')->map(fn ($id) => (string) $id);

        $seleccionados = $seleccionados->only($carreraIds->all());

        DB::transaction(function () use ($carreras, $seleccionados) {
            $carreras->each(function (CatalogoCarrera $carrera) use ($seleccionados) {
                $ids = collect($seleccionados->get((string) $carrera->id, []))
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                $syncData = [];
                foreach ($ids as $id) {
                    $syncData[$id] = ['obligatorio' => true];
                }

                $carrera->tratamientos()->sync($syncData);
            });
        });

        return redirect()
            ->route('consentimientos.requeridos.index')
            ->with('status', 'Los tratamientos requeridos se actualizaron correctamente.');
    }
}
