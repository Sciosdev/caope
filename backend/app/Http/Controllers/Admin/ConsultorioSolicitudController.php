<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\Expediente;
use App\Services\ConsultorioReservationGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
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

        $identityColumns = ['id', 'no_control', 'paciente', 'facilitador_id', 'tutor_id', 'coordinador_id', 'estrategia_id'];
        $solicitudesPendientes = $this->hasSolicitudesTable()
            ? ConsultorioReservaSolicitud::query()
                ->with([
                    'reserva.expediente' => fn ($query) => $query->select($identityColumns),
                    'reserva.cubiculo:id,numero,nombre',
                    'requestedBy:id,name',
                ])
                ->where('status', 'pendiente')
                ->when(! $isAdmin, fn ($query) => $query->where('requested_by', $user?->id))
                ->latest()
                ->paginate(20)
            : collect();

        $solicitudes = $solicitudesPendientes instanceof LengthAwarePaginator
            ? $solicitudesPendientes->getCollection()
            : collect();
        $proposedIds = $solicitudes
            ->map(fn (ConsultorioReservaSolicitud $solicitud) => data_get($solicitud->payload, 'expediente_id'))
            ->filter(fn ($id) => (is_int($id) || is_string($id)) && filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()->values()->all();
        $expedientesPropuestos = $proposedIds === [] ? collect() : Expediente::query()
            ->visibleTo($user)
            ->whereKey($proposedIds)
            ->get($identityColumns)
            ->keyBy('id');

        return view('admin.consultorios.solicitudes.index', [
            'solicitudesPendientes' => $solicitudesPendientes,
            'expedientesPropuestos' => $expedientesPropuestos,
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
                ->findOrFail($locked->consultorio_reserva_id);

            if ($locked->tipo === 'baja') {
                $reserva = ConsultorioReserva::query()
                    ->lockForUpdate()
                    ->findOrFail($locked->consultorio_reserva_id);
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
                'expediente_id',
                'usuario_atendido_id',
                'estratega_id',
                'supervisor_id',
            ]);

            abort_if($changes === [], 422, 'La solicitud no contiene cambios válidos.');

            // Retain the original request payload as audit evidence, but apply
            // only its physical room selection, never a legacy sub-room pair.
            $changes['cubiculo_numero'] = $changes['consultorio_numero'] ?? null;

            $validator = Validator::make($changes, [
                'fecha' => ['required', 'date'],
                'hora_inicio' => ['required', 'date_format:H:i'],
                'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
                'consultorio_numero' => ['required', 'integer', Rule::exists('catalogo_consultorios', 'numero')->where('activo', true)],
                'cubiculo_numero' => ['required', 'integer', 'same:consultorio_numero'],
                'estrategia' => ['required', 'string', 'max:255', Rule::exists('catalogo_estrategias', 'nombre')->where('activo', true)],
                'expediente_id' => ['sometimes', 'nullable', 'integer', 'exists:expedientes,id'],
                'usuario_atendido_id' => ['nullable', 'integer', 'exists:users,id'],
                'estratega_id' => ['nullable', 'integer', 'exists:users,id'],
                'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            ]);

            $validator->after(function ($validator) use ($changes): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (date('w', strtotime((string) ($changes['fecha'] ?? ''))) === '0') {
                    $validator->errors()->add('fecha', 'Solo se permiten reservas de lunes a sábado.');
                }

                if (($changes['hora_inicio'] ?? '') < '07:00' || ($changes['hora_fin'] ?? '') > '22:00') {
                    $validator->errors()->add('hora_inicio', 'El horario permitido es de 07:00 a 22:00.');
                }
            });

            $validated = $validator->validate();
            $guard = app(ConsultorioReservationGuard::class);
            $guard->lockConsultorio((int) $validated['consultorio_numero']);
            $reserva = ConsultorioReserva::query()
                ->lockForUpdate()
                ->findOrFail($locked->consultorio_reserva_id);
            $guard->assertAvailable(
                (int) $validated['consultorio_numero'],
                [$validated['fecha']],
                $validated['hora_inicio'],
                $validated['hora_fin'],
                (int) $reserva->getKey(),
            );

            $reserva->update($validated);
            $locked->update(['status' => 'atendida']);
        }, 3);

        return redirect()->route('admin.consultorios.solicitudes.index')->with('status', 'Solicitud aprobada correctamente.');
    }
}
