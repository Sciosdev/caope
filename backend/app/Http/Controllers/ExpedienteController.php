<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use Illuminate\Http\Request;

class ExpedienteController extends Controller
{
    public function index(Request $request)
    {
        $q       = $request->string('q')->toString();
        $estado  = $request->string('estado')->toString();
        $desde   = $request->date('desde');
        $hasta   = $request->date('hasta');

        $expedientes = Expediente::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function($qq) use ($q) {
                    $qq->where('numero','like',"%{$q}%")
                       ->orWhere('paciente','like',"%{$q}%");
                });
            })
            ->when($estado, fn($qr) => $qr->where('estado',$estado))
            ->when($desde && $hasta, fn($qr) => $qr->whereBetween('apertura', [$desde, $hasta]))
            ->orderByDesc('apertura')
            ->paginate(10)
            ->withQueryString();

        return view('expedientes.index', compact('expedientes', 'q', 'estado', 'desde', 'hasta'));
    }
}
