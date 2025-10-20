<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExpedienteController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:expedientes.view')->only('index');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Expediente::class);

        $busqueda = (string) $request->input('q', '');
        $estado = (string) $request->input('estado', '');
        $desde = $request->input('desde') ? Carbon::parse($request->input('desde')) : null;
        $hasta = $request->input('hasta') ? Carbon::parse($request->input('hasta')) : null;

        $user = $request->user();

        $query = Expediente::query()
            ->when(! $user->can('expedientes.manage'), function ($q) use ($user) {
                if ($user->hasRole('docente')) {
                    $q->where('tutor_id', $user->id);
                } elseif ($user->hasRole('alumno')) {
                    $q->where('creado_por', $user->id);
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
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
