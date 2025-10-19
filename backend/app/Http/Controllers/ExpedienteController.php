<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExpedienteController extends Controller
{
    public function index(Request $request)
    {
        $busqueda = (string) $request->input('q', '');
        $estado = (string) $request->input('estado', '');
        $desde = $request->input('desde') ? Carbon::parse($request->input('desde')) : null;
        $hasta = $request->input('hasta') ? Carbon::parse($request->input('hasta')) : null;

        $query = Expediente::query()
            ->when($busqueda, function ($q) use ($busqueda) {
                $q->where(function ($w) use ($busqueda) {
                    $w->where('no', 'like', "%{$busqueda}%")
                        ->orWhere('paciente', 'like', "%{$busqueda}%");
                });
            })
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($desde, fn ($q) => $q->whereDate('apertura', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('apertura', '<=', $hasta))
            ->orderByDesc('apertura');

        $expedientes = $query->paginate(10)->withQueryString();

        return view('expedientes.index', [
            'expedientes' => $expedientes,
            'q' => $busqueda,
            'estado' => $estado,
            'desde' => $desde,
            'hasta' => $hasta,
        ]);
    }
}
