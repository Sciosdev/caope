<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConsultorioSolicitudController extends Controller
{
    private function hasSolicitudesTable(): bool
    {
        return Schema::hasTable('consultorio_reserva_solicitudes');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user?->hasRole('admin') ?? false;
        $isApprovedPaps = ($user?->hasRole('paps') ?? false) && ! is_null($user?->approved_at);

        abort_unless($isAdmin || $isApprovedPaps, 403);

        $solicitudesPendientes = $this->hasSolicitudesTable()
            ? ConsultorioReservaSolicitud::query()
                ->with(['reserva', 'requestedBy'])
                ->where('status', 'pendiente')
                ->when(! $isAdmin, fn ($query) => $query->where('requested_by', $user?->id))
                ->latest()
                ->paginate(20)
            : collect();

        return view('admin.consultorios.solicitudes.index', [
            'solicitudesPendientes' => $solicitudesPendientes,
        ]);
    }

    public function approve(Request $request, ConsultorioReservaSolicitud $solicitud): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
        DB::transaction(function () use ($solicitud): void {
            $locked = ConsultorioReservaSolicitud::query()
                ->lockForUpdate()
                ->findOrFail($solicitud->getKey());

            abort_if($locked->status !== 'pendiente', 422, 'Esta solicitud ya fue atendida.');

            $reserva = ConsultorioReserva::query()
                ->lockForUpdate()
                ->findOrFail($locked->consultorio_reserva_id);

            if ($locked->tipo === 'baja') {
                // Conserva la solicitud como evidencia de quién pidió la baja y cuándo fue atendida.
                $locked->update(['status' => 'atendida']);
                $reserva->delete();

                return;
            }

            abort_unless($locked->tipo === 'edicion' && is_array($locked->payload), 422, 'La solicitud no contiene cambios válidos.');

            $changes = Arr::only($locked->payload, [
                'fecha',
                'hora_inicio',
                'hora_fin',
                'consultorio_numero',
                'cubiculo_numero',
                'estrategia',
                'usuario_atendido_id',
                'estratega_id',
                'supervisor_id',
            ]);

            abort_if($changes === [], 422, 'La solicitud no contiene cambios válidos.');

            $validator = Validator::make($changes, [
                'fecha' => ['required', 'date'],
                'hora_inicio' => ['required', 'date_format:H:i'],
                'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
                'consultorio_numero' => ['required', 'integer', Rule::exists('catalogo_consultorios', 'numero')->where('activo', true)],
                'cubiculo_numero' => ['required', 'integer', Rule::exists('catalogo_cubiculos', 'numero')->where('activo', true)],
                'estrategia' => ['required', 'string', 'max:255', Rule::exists('catalogo_estrategias', 'nombre')->where('activo', true)],
                'usuario_atendido_id' => ['nullable', 'integer', 'exists:users,id'],
                'estratega_id' => ['nullable', 'integer', 'exists:users,id'],
                'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            ]);

            $validator->after(function ($validator) use ($changes, $reserva): void {
                if (date('w', strtotime((string) ($changes['fecha'] ?? ''))) === '0') {
                    $validator->errors()->add('fecha', 'Solo se permiten reservas de lunes a sábado.');
                }

                if (($changes['hora_inicio'] ?? '') < '07:00' || ($changes['hora_fin'] ?? '') > '22:00') {
                    $validator->errors()->add('hora_inicio', 'El horario permitido es de 07:00 a 22:00.');
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $horaInicio = Carbon::createFromFormat('H:i', $changes['hora_inicio'])->format('H:i:s');
                $horaFin = Carbon::createFromFormat('H:i', $changes['hora_fin'])->format('H:i:s');
                $overlap = ConsultorioReserva::query()
                    ->whereKeyNot($reserva->getKey())
                    ->whereDate('fecha', $changes['fecha'])
                    ->where('consultorio_numero', $changes['consultorio_numero'])
                    ->where('cubiculo_numero', $changes['cubiculo_numero'])
                    ->where('hora_inicio', '<', $horaFin)
                    ->where('hora_fin', '>', $horaInicio)
                    ->exists();

                if ($overlap) {
                    $validator->errors()->add('hora_inicio', 'Ese consultorio ya está reservado en el bloque seleccionado.');
                }
            });

            $reserva->update($validator->validate());
            $locked->update(['status' => 'atendida']);
        });

        return redirect()->route('admin.consultorios.solicitudes.index')->with('status', 'Solicitud aprobada correctamente.');
    }
}
