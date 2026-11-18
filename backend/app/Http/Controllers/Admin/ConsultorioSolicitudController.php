<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogoConsultorio;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\Expediente;
use App\Services\ConsultorioReservationUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConsultorioSolicitudController extends Controller
{
    public function __construct(private ConsultorioReservationUpdateService $reservationUpdater) {}

    private function hasSolicitudesTable(): bool
    {
        return Schema::hasTable('consultorio_reserva_solicitudes');
    }

    private function resolutionsAvailable(): bool
    {
        return Schema::hasColumn('consultorio_reserva_solicitudes', 'resolucion');
    }

    private function requireResolutionAudit(): void
    {
        if (! $this->resolutionsAvailable()) {
            throw ValidationException::withMessages(['solicitud' => 'Las decisiones están temporalmente en pausa hasta que termine la actualización. La reserva y la solicitud no han cambiado.']);
        }
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
        $proposedRoomNumbers = $solicitudes
            ->map(fn (ConsultorioReservaSolicitud $solicitud) => data_get($solicitud->payload, 'consultorio_numero'))
            ->filter(fn ($numero): bool => is_numeric($numero) && (int) $numero > 0)
            ->map(fn ($numero): int => (int) $numero)
            ->unique()
            ->values()
            ->all();
        $cubiculosPropuestos = $proposedRoomNumbers === [] ? collect() : CatalogoConsultorio::query()
            ->whereIn('numero', $proposedRoomNumbers)
            ->get(['id', 'numero', 'nombre'])
            ->keyBy('numero');

        $resolutionsAvailable = $this->resolutionsAvailable();

        return view('admin.consultorios.solicitudes.index', [
            'solicitudesPendientes' => $solicitudesPendientes,
            'expedientesPropuestos' => $expedientesPropuestos,
            'cubiculosPropuestos' => $cubiculosPropuestos,
            'accionesDisponibles' => $resolutionsAvailable,
            'cubiculosActivos' => $isAdmin && $resolutionsAvailable ? CatalogoConsultorio::query()->where('activo', true)->orderBy('numero')->get(['numero', 'nombre']) : collect(),
            'resueltas' => $resolutionsAvailable
                ? ConsultorioReservaSolicitud::query()->where('status', 'atendida')->whereNotNull('resolucion')
                    ->when(! $isAdmin, fn ($query) => $query->where('requested_by', $user->id))
                    ->latest('updated_at')->orderByDesc('id')->limit(20)->get(['id', 'tipo', 'updated_at', 'resolucion'])
                : collect(),
        ]);
    }

    public function approve(Request $request, ConsultorioReservaSolicitud $solicitud): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
        $this->requireResolutionAudit();
        if ($solicitud->tipo === 'baja') {
            $this->reservationUpdater->finishWithoutEdit($solicitud, $request->user(), reject: false);
        } else {
            $this->applyProposal($request, $solicitud);
        }

        return redirect()->route('admin.consultorios.solicitudes.index')->with('status', 'Solicitud aprobada correctamente.');
    }

    public function correct(Request $request, ConsultorioReservaSolicitud $solicitud): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
        $this->requireResolutionAudit();
        $correction = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i'],
            'consultorio_numero' => ['required', 'integer'],
            'motivo' => ['required', 'string', 'max:300'],
            'expediente_id' => ['missing'],
            'usuario_atendido_id' => ['missing'],
            'estratega_id' => ['missing'],
            'supervisor_id' => ['missing'],
            'estrategia' => ['missing'],
        ]);
        $this->applyProposal($request, $solicitud, $correction);

        return redirect()->route('admin.consultorios.solicitudes.index')->with('status', 'Solicitud corregida y aprobada. Se actualizó la misma reserva.');
    }

    public function reject(Request $request, ConsultorioReservaSolicitud $solicitud): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
        $this->requireResolutionAudit();
        $reason = $request->validate(['motivo' => ['required', 'string', 'max:300']])['motivo'];
        $this->reservationUpdater->finishWithoutEdit($solicitud, $request->user(), reject: true, reason: $reason);

        return redirect()->route('admin.consultorios.solicitudes.index')->with('status', 'Solicitud rechazada. No se modificó ni canceló ninguna reserva.');
    }

    private function applyProposal(Request $request, ConsultorioReservaSolicitud $current, array $correction = []): void
    {
        if ($current->status !== 'pendiente' || $current->tipo !== 'edicion') {
            throw ValidationException::withMessages(['solicitud' => 'Solo se pueden aprobar cambios de una solicitud de edición pendiente.']);
        }
        $payload = $current->payload;
        if (! is_array($payload) && $payload !== null) {
            throw ValidationException::withMessages(['solicitud' => 'No se reconoce el formato de la propuesta. Puedes rechazarla, sin cancelar la reserva.']);
        }

        $changes = Arr::only($payload ?? [], [
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
        $reason = $correction['motivo'] ?? null;
        $changes = array_replace($changes, Arr::except($correction, ['motivo']));
        if ($changes === []) {
            throw ValidationException::withMessages(['solicitud' => 'La solicitud no contiene una propuesta. Indica un horario con «Corregir y aprobar» o recházala.']);
        }
        foreach (['hora_inicio', 'hora_fin'] as $field) {
            if (is_string($changes[$field] ?? null) && preg_match('/^\d{2}:\d{2}:00$/D', $changes[$field])) {
                $changes[$field] = substr($changes[$field], 0, 5);
            }
        }

        // Retain the original request payload as audit evidence, but apply
        // only its physical room selection, never a legacy sub-room pair.
        $changes['cubiculo_numero'] = $changes['consultorio_numero'] ?? null;

        $validator = Validator::make($changes, [
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'consultorio_numero' => ['required', 'integer', Rule::exists('catalogo_consultorios', 'numero')->where('activo', true)],
            'cubiculo_numero' => ['required', 'integer', 'same:consultorio_numero'],
            'estrategia' => ['sometimes', 'required', 'string', 'max:255', Rule::exists('catalogo_estrategias', 'nombre')->where('activo', true)],
            'expediente_id' => ['sometimes', 'nullable', 'integer', 'exists:expedientes,id'],
            'usuario_atendido_id' => ['nullable', 'integer', 'exists:users,id'],
            'estratega_id' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
        ], [], ['consultorio_numero' => 'cubículo', 'estrategia' => 'estrategia propuesta']);

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
        $reserva = ConsultorioReserva::query()->find($current->consultorio_reserva_id);
        if (! $reserva) {
            throw ValidationException::withMessages(['solicitud' => 'La reserva vinculada ya no existe. Puedes rechazar esta solicitud.']);
        }
        $this->reservationUpdater->approvePendingEdit(
            $current,
            $reserva,
            $validated,
            $request->user(),
            $reason,
        );
    }
}
