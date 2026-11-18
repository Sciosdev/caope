<?php

namespace App\Http\Controllers;

use App\Models\CatalogoConsultorio;
use App\Models\ConsultorioReserva;
use App\Services\ConsultorioSerieService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsultorioSerieController extends Controller
{
    public function edit(Request $request, ConsultorioReserva $reserva, ConsultorioSerieService $service): View|RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
        if (! $service->available()) {
            return redirect()->route('consultorios.index')->withErrors(['reserva' => 'La gestión de series estará disponible cuando termine la actualización.']);
        }
        if ($reserva->expediente?->trashed() || $reserva->solicitudes()->where('status', 'pendiente')->exists()) {
            return redirect()->route('consultorios.index')->withErrors(['reserva' => 'Esta reserva tiene una solicitud pendiente o está vinculada a un expediente archivado; no puede cambiarse desde aquí.']);
        }
        abort_unless($reserva->hasNotStarted(), 422, 'Esta reserva ya comenzó; no puede cambiarse desde aquí.');

        return view('consultorios.serie', $service->preview($reserva) + [
            'reserva' => $reserva,
            'cubiculosActivos' => CatalogoConsultorio::activos()->sortBy('numero')->values(),
        ]);
    }

    public function apply(Request $request, ConsultorioReserva $reserva, ConsultorioSerieService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
        $data = $request->validate([
            'accion' => ['required', 'in:reprogramar,cancelar'],
            'alcance' => ['required', 'in:esta,siguientes'],
            'revision' => ['required', 'string', 'size:64'],
            'motivo' => ['required', 'string', 'max:300'],
            'confirmar' => ['required', 'accepted'],
            'fecha' => ['exclude_unless:accion,reprogramar', 'required', 'date_format:Y-m-d'],
            'hora_inicio' => ['exclude_unless:accion,reprogramar', 'required', 'date_format:H:i', 'after_or_equal:07:00'],
            'hora_fin' => ['exclude_unless:accion,reprogramar', 'required', 'date_format:H:i', 'after:hora_inicio', 'before_or_equal:22:00'],
            'consultorio_numero' => ['exclude_unless:accion,reprogramar', 'required', 'integer', 'min:1'],
            'serie_uuid' => ['missing'],
            'expediente_id' => ['missing'],
            'usuario_atendido_id' => ['missing'],
            'estratega_id' => ['missing'],
            'supervisor_id' => ['missing'],
            'reservas' => ['missing'],
        ]);

        $count = $service->apply($reserva, $data, $request->user());
        $message = $data['accion'] === 'cancelar' ? 'Cancelación de espacio completada.' : 'Reprogramación completada.';

        return redirect()->route('consultorios.index')->with('status', $message.' Reservas afectadas: '.$count.'.');
    }
}
