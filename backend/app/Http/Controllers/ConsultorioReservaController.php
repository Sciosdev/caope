<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConsultorioReservaRequest;
use App\Http\Requests\UpdateConsultorioReservaRequest;
use App\Models\ConsultorioReserva;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsultorioReservaController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'coordinador']), 403);

        $fecha = $request->string('fecha')->toString() ?: now()->toDateString();
        $consultorioSeleccionado = max(1, min(14, (int) $request->integer('consultorio_numero', 1)));

        $reservas = ConsultorioReserva::query()
            ->with(['usuarioAtendido', 'estratega', 'supervisor', 'creadoPor'])
            ->when($fecha, fn ($query) => $query->whereDate('fecha', $fecha))
            ->orderBy('fecha')
            ->orderBy('consultorio_numero')
            ->orderBy('cubiculo_numero')
            ->orderBy('hora_inicio')
            ->paginate(25)
            ->withQueryString();

        $ocupacionPorCubiculo = ConsultorioReserva::query()
            ->with(['usuarioAtendido', 'estratega', 'supervisor'])
            ->whereDate('fecha', $fecha)
            ->where('consultorio_numero', $consultorioSeleccionado)
            ->orderBy('cubiculo_numero')
            ->orderBy('hora_inicio')
            ->get()
            ->groupBy('cubiculo_numero');

        $usuariosActivos = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $docentes = User::role('docente')->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('consultorios.index', [
            'reservas' => $reservas,
            'ocupacionPorCubiculo' => $ocupacionPorCubiculo,
            'fechaFiltro' => $fecha,
            'consultorioSeleccionado' => $consultorioSeleccionado,
            'usuarios' => $usuariosActivos,
            'docentes' => $docentes,
        ]);
    }

    public function edit(Request $request, ConsultorioReserva $reserva): View
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'coordinador']), 403);

        return view('consultorios.edit', [
            'reserva' => $reserva->load(['usuarioAtendido', 'estratega', 'supervisor']),
            'usuarios' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'docentes' => User::role('docente')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreConsultorioReservaRequest $request): RedirectResponse
    {
        ConsultorioReserva::query()->create($request->validated() + [
            'creado_por' => $request->user()->id,
        ]);

        return redirect()->route('consultorios.index')->with('status', 'Reserva registrada correctamente.');
    }

    public function update(UpdateConsultorioReservaRequest $request, ConsultorioReserva $reserva): RedirectResponse
    {
        $reserva->update($request->validated());

        return redirect()->route('consultorios.index')->with('status', 'Reserva actualizada correctamente.');
    }

    public function destroy(Request $request, ConsultorioReserva $reserva): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'coordinador']), 403);

        $reserva->delete();

        return redirect()->route('consultorios.index')->with('status', 'Reserva eliminada correctamente.');
    }
}
