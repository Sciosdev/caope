<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ExpedienteController extends Controller
{
    public function index(Request $request)
    {
        // 1) Leer filtros con valores por defecto
        $busqueda = (string) $request->input('q', '');
        $estado   = (string) $request->input('estado', '');
        $desde    = $request->input('desde') ? Carbon::parse($request->input('desde')) : null;
        $hasta    = $request->input('hasta') ? Carbon::parse($request->input('hasta')) : null;

        // 2) Construir query
        $query = Expediente::query()
            ->when($busqueda, function ($q) use ($busqueda) {
                $q->where(function ($w) use ($busqueda) {
                    // OJO: si tu columna se llama 'no' en la BD, cambia 'numero' por 'no'
                    $w->where('numero', 'like', "%{$busqueda}%")
                      ->orWhere('paciente', 'like', "%{$busqueda}%");
                });
            })
            ->when($estado, fn($q) => $q->where('estado', $estado))
            ->when($desde,  fn($q) => $q->whereDate('apertura', '>=', $desde))
            ->when($hasta,  fn($q) => $q->whereDate('apertura', '<=', $hasta))
            ->orderByDesc('apertura');

        // 3) Paginación (la vista ya desactiva el paging de DataTables)
        $expedientes = $query->paginate(10)->withQueryString();

        // 4) Enviar a la vista también los filtros para que no truene
        return view('expedientes.index', [
            'expedientes' => $expedientes,
            'q'      => $busqueda,
            'estado' => $estado,
            'desde'  => $desde,
            'hasta'  => $hasta,
        ]);
    }
}
