<?php

namespace App\Http\Controllers;

use App\Exports\ConsultorioReservasExport;
use App\Http\Requests\StoreConsultorioReservaRequest;
use App\Http\Requests\UpdateConsultorioReservaRequest;
use App\Models\CatalogoConsultorio;
use App\Models\CatalogoEstrategia;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\Expediente;
use App\Models\User;
use App\Services\ConsultorioReservationGuard;
use App\Services\ConsultorioReservationUpdateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ConsultorioReservaController extends Controller
{
    public function __construct(private ConsultorioReservationUpdateService $reservationUpdater) {}

    private function applyPendingApprovalVisibilityFilter(Builder $query): Builder
    {
        if (! $this->hasSolicitudesTable()) {
            return $query;
        }

        return $query->whereDoesntHave('solicitudes', function (Builder $solicitudes): void {
            $solicitudes
                ->where('status', 'pendiente')
                ->whereIn('tipo', ['edicion', 'baja']);
        });
    }

    private function hasSolicitudesTable(): bool
    {
        return Schema::hasTable('consultorio_reserva_solicitudes');
    }

    private function canManageBitacora(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('admin');
    }

    private function canRequestBitacoraChanges(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        return $user->isApprovedPaps();
    }

    private function canAccessConsultorios(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'coordinador', 'estratega', 'alumno', 'docente'])
            || $user->isApprovedPaps();
    }

    private function applyAssignedReservationsVisibilityFilter(Request $request, Builder $query): Builder
    {
        return $query->visibleTo($request->user());
    }

    public function index(Request $request): View
    {
        abort_unless($this->canAccessConsultorios($request), 403);

        $fechaParam = $request->string('fecha')->toString();
        $fechaFiltro = preg_match('/^\d{4}-\d{2}$/', $fechaParam) === 1
            ? Carbon::createFromFormat('Y-m', $fechaParam)->startOfMonth()->toDateString()
            : ($fechaParam ?: now()->toDateString());
        $bitacoraModo = $request->string('bitacora_modo')->toString() === 'mes' ? 'mes' : 'semana';
        $bitacoraFechaBase = Carbon::parse($request->string('bitacora_inicio')->toString() ?: $fechaFiltro);
        $bitacoraFechaSeleccionada = $bitacoraFechaBase->toDateString();
        $bitacoraInicio = ($bitacoraModo === 'mes' ? $bitacoraFechaBase->copy()->startOfMonth() : $bitacoraFechaBase->copy()->startOfWeek(Carbon::MONDAY))->toDateString();
        $bitacoraFin = ($bitacoraModo === 'mes' ? $bitacoraFechaBase->copy()->endOfMonth() : $bitacoraFechaBase->copy()->endOfWeek(Carbon::SUNDAY))->toDateString();
        $user = $request->user();
        $hasGlobalVisibility = $user->hasRole('admin') || $user->isApprovedPaps();
        $visibleReservationsForCatalogs = $this->applyPendingApprovalVisibilityFilter(
            $this->applyAssignedReservationsVisibilityFilter($request, ConsultorioReserva::query())
        );

        $consultoriosActivos = CatalogoConsultorio::activos();
        if (! $hasGlobalVisibility) {
            $assignedConsultorios = (clone $visibleReservationsForCatalogs)
                ->select('consultorio_numero')
                ->distinct()
                ->pluck('consultorio_numero')
                ->map(fn ($numero) => (int) $numero);

            $consultoriosActivos = $consultoriosActivos
                ->whereIn('numero', $assignedConsultorios)
                ->values();
        }

        $consultorioSolicitado = $request->filled('consultorio_numero')
            ? (int) $request->integer('consultorio_numero')
            : null;
        $consultorioSeleccionado = $consultorioSolicitado !== null
            && $consultoriosActivos->contains(fn (CatalogoConsultorio $consultorio) => $consultorio->numero === $consultorioSolicitado)
                ? $consultorioSolicitado
                : $consultoriosActivos->first()?->numero;

        $reservasQuery = ConsultorioReserva::query()
            ->with(['usuarioAtendido', 'estratega', 'supervisor', 'creadoPor', 'cubiculo'])
            ->orderByDesc('fecha')
            ->orderBy('consultorio_numero')
            ->orderBy('cubiculo_numero')
            ->orderBy('hora_inicio');

        $reservas = $this->applyPendingApprovalVisibilityFilter(
            $this->applyAssignedReservationsVisibilityFilter($request, $reservasQuery)
        )
            ->paginate(25)
            ->withQueryString();

        $ocupacionQuery = ConsultorioReserva::query()
            ->with(['usuarioAtendido', 'estratega', 'supervisor'])
            ->whereDate('fecha', $fechaFiltro)
            ->when(
                $consultorioSeleccionado !== null,
                fn (Builder $query) => $query->where('consultorio_numero', $consultorioSeleccionado),
                fn (Builder $query) => $query->whereRaw('1 = 0')
            )
            ->orderBy('cubiculo_numero')
            ->orderBy('hora_inicio');

        $ocupacionPorCubiculo = $this->applyPendingApprovalVisibilityFilter(
            $this->applyAssignedReservationsVisibilityFilter($request, $ocupacionQuery)
        )
            ->get()
            ->groupBy('consultorio_numero');

        $usuariosActivos = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $docentes = User::role(['docente', 'estratega'])->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('consultorios.index', [
            'reservas' => $reservas,
            'ocupacionPorCubiculo' => $ocupacionPorCubiculo,
            'fechaFiltro' => $fechaFiltro,
            'bitacoraFechaSeleccionada' => $bitacoraFechaSeleccionada,
            'bitacoraInicio' => $bitacoraInicio,
            'bitacoraFin' => $bitacoraFin,
            'bitacoraModo' => $bitacoraModo,
            'consultorioSeleccionado' => $consultorioSeleccionado,
            'cubiculoSeleccionado' => null,
            'usuarios' => $usuariosActivos,
            'docentes' => $docentes,
            'consultoriosActivos' => $consultoriosActivos,
            'cubiculosActivos' => $consultoriosActivos,
            'estrategiasActivas' => CatalogoEstrategia::activos(),
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        abort_unless($this->canAccessConsultorios($request), 403);

        $fecha = $request->string('fecha')->toString() ?: now()->toDateString();
        $fechaInicio = $request->string('fecha_inicio')->toString();
        $fechaFin = $request->string('fecha_fin')->toString();
        $consultorioNumero = $request->filled('consultorio_numero')
            ? (int) $request->integer('consultorio_numero')
            : null;

        $reservasQuery = ConsultorioReserva::query()
            ->with(['usuarioAtendido:id,name', 'estratega:id,name', 'cubiculo:id,numero,nombre'])
            ->when(
                $fechaInicio && $fechaFin,
                fn ($query) => $query->whereBetween('fecha', [
                    Carbon::parse($fechaInicio)->toDateString(),
                    Carbon::parse($fechaFin)->toDateString(),
                ]),
                fn ($query) => $query->whereDate('fecha', $fecha)
            )
            ->when($consultorioNumero, fn ($query) => $query->where('consultorio_numero', $consultorioNumero))
            ->orderBy('cubiculo_numero')
            ->orderBy('fecha')
            ->orderBy('hora_inicio');

        $reservas = $this->applyPendingApprovalVisibilityFilter(
            $this->applyAssignedReservationsVisibilityFilter($request, $reservasQuery)
        )
            ->get(['fecha', 'consultorio_numero', 'cubiculo_numero', 'hora_inicio', 'hora_fin', 'estrategia', 'usuario_atendido_id', 'estratega_id'])
            ->map(fn (ConsultorioReserva $reserva) => [
                'fecha' => $reserva->fecha?->toDateString(),
                'consultorio_numero' => $reserva->consultorio_numero,
                'cubiculo_nombre' => $reserva->cubiculo?->nombre ?? 'Cubículo '.$reserva->consultorio_numero,
                'cubiculo_numero' => $reserva->cubiculo_numero,
                'hora_inicio' => $reserva->hora_inicio,
                'hora_fin' => $reserva->hora_fin,
                'estrategia' => $reserva->estrategia,
                'estratega_id' => $reserva->estratega_id,
                'estratega_nombre' => $reserva->estratega?->name,
                'usuario_atendido_id' => $reserva->usuario_atendido_id,
                'usuario_atendido_nombre' => $reserva->usuarioAtendido?->name,
            ]);

        return response()->json([
            'fecha' => $fecha,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'consultorio_numero' => $consultorioNumero,
            'reservas' => $reservas,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        abort_unless($this->canAccessConsultorios($request), 403);

        $filename = sprintf('bitacora_reservas_%s.xlsx', Date::now()->format('Ymd_His'));

        return Excel::download(new ConsultorioReservasExport($request->user()->getKey()), $filename);
    }

    public function edit(Request $request, ConsultorioReserva $reserva): View
    {
        abort_unless(
            $this->canManageBitacora($request) || $this->canRequestBitacoraChanges($request),
            403
        );

        $reserva->load([
            'usuarioAtendido',
            'estratega',
            'supervisor',
            'estrategiaCatalogo',
            'cubiculo',
            'expediente',
        ]);
        abort_if($reserva->expediente?->trashed(), 403, 'Un expediente archivado es de solo lectura.');
        abort_if($this->reservationHasPendingRequest($reserva), 409, 'Esta reserva tiene una solicitud pendiente por atender.');

        $fromExpediente = $request->string('origen')->toString() === 'expediente';
        if ($fromExpediente) {
            abort_unless($this->canManageBitacora($request), 403);
            abort_if($reserva->expediente === null, 404);
        }

        return view('consultorios.edit', [
            'reserva' => $reserva,
            'editContext' => $fromExpediente ? 'expediente' : null,
            'expedientes' => Expediente::query()->visibleTo($request->user())
                ->orderBy('no_control')->get(['id', 'no_control', 'paciente']),
            'usuarios' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'docentes' => User::role(['docente', 'estratega'])->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'consultoriosActivos' => CatalogoConsultorio::activos(),
            'cubiculosActivos' => CatalogoConsultorio::activos(),
            'estrategiasActivas' => CatalogoEstrategia::activos(),
        ]);
    }

    public function store(StoreConsultorioReservaRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $baseData = Arr::only($validated, [
            'hora_inicio',
            'hora_fin',
            'consultorio_numero',
            'cubiculo_numero',
            'estrategia',
            'expediente_id',
            'usuario_atendido_id',
            'estratega_id',
            'supervisor_id',
            'origen_expediente',
        ]);

        $fechas = $request->reservationDates()->all();
        $reservationGuard = app(ConsultorioReservationGuard::class);

        DB::transaction(function () use ($baseData, $fechas, $reservationGuard, $request): void {
            $consultorioNumero = (int) $baseData['consultorio_numero'];
            $reservationGuard->lockConsultorio($consultorioNumero);
            $reservationGuard->assertAvailable(
                $consultorioNumero,
                $fechas,
                $baseData['hora_inicio'],
                $baseData['hora_fin']
            );

            foreach ($fechas as $fecha) {
                ConsultorioReserva::query()->create($baseData + [
                    'fecha' => $fecha,
                    'creado_por' => $request->user()->id,
                    'origen_expediente' => (bool) ($baseData['origen_expediente'] ?? false),
                ]);
            }
        }, 3);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Reserva registrada correctamente.',
            ]);
        }

        return redirect()->route('consultorios.index')->with('status', 'Reserva registrada correctamente.');
    }

    public function update(UpdateConsultorioReservaRequest $request, ConsultorioReserva $reserva): RedirectResponse
    {
        abort_unless($this->canManageBitacora($request), 403);

        $validated = $request->validated();
        $scheduleOnly = ($validated['edit_context'] ?? null) === 'expediente';
        $updated = $this->reservationUpdater->updateApproved(
            $reserva,
            Arr::except($validated, ['edit_context']),
            $request->user(),
            $scheduleOnly,
        );

        if ($scheduleOnly && $updated->expediente_id !== null) {
            return redirect()
                ->route('expedientes.show', $updated->expediente_id)
                ->with('status', 'Horario y cubículo actualizados correctamente.');
        }

        return redirect()->route('consultorios.index')->with('status', 'Reserva actualizada correctamente.');
    }

    public function destroy(Request $request, ConsultorioReserva $reserva): RedirectResponse
    {
        abort_unless($this->canManageBitacora($request), 403);
        ConsultorioReserva::query()
            ->whereKey($reserva->getKey())
            ->toBase()
            ->delete();

        return redirect()->route('consultorios.index')->with('status', 'Reserva eliminada correctamente.');
    }

    public function requestUpdate(UpdateConsultorioReservaRequest $request, ConsultorioReserva $reserva): RedirectResponse
    {
        abort_unless($this->canRequestBitacoraChanges($request), 403);
        if (! $this->hasSolicitudesTable()) {
            return redirect()->route('consultorios.index')->with('status', 'Las solicitudes de modificación no están disponibles temporalmente.');
        }

        DB::transaction(function () use ($request, $reserva): void {
            $validated = $request->validated();
            app(ConsultorioReservationGuard::class)->lockConsultorio((int) $validated['consultorio_numero']);
            $lockedReserva = ConsultorioReserva::query()
                ->whereKey($reserva->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertReservationIsMutable($lockedReserva);
            ConsultorioReservaSolicitud::query()->create([
                'consultorio_reserva_id' => $lockedReserva->id,
                'requested_by' => $request->user()->id,
                'tipo' => 'edicion',
                'payload' => $validated,
                'status' => 'pendiente',
            ]);
        }, 3);

        return redirect()->route('consultorios.index')->with('status', 'Solicitud de modificación enviada. El administrador general revisará el cambio.');
    }

    public function requestEdit(Request $request, ConsultorioReserva $reserva): RedirectResponse
    {
        abort_unless($this->canRequestBitacoraChanges($request), 403);
        if (! $this->hasSolicitudesTable()) {
            return redirect()->route('consultorios.index')->with('status', 'Las solicitudes de modificación no están disponibles temporalmente.');
        }

        DB::transaction(function () use ($request, $reserva): void {
            $lockedReserva = ConsultorioReserva::query()->lockForUpdate()->findOrFail($reserva->getKey());
            $this->assertReservationIsMutable($lockedReserva);
            ConsultorioReservaSolicitud::query()->create([
                'consultorio_reserva_id' => $lockedReserva->id,
                'requested_by' => $request->user()->id,
                'tipo' => 'edicion',
                'payload' => [
                    'fecha' => $lockedReserva->fecha?->format('Y-m-d'),
                    'hora_inicio' => substr((string) $lockedReserva->hora_inicio, 0, 5),
                    'hora_fin' => substr((string) $lockedReserva->hora_fin, 0, 5),
                    'consultorio_numero' => (int) $lockedReserva->consultorio_numero,
                    'cubiculo_numero' => (int) $lockedReserva->cubiculo_numero,
                    'estrategia' => $lockedReserva->estrategia,
                    'expediente_id' => $lockedReserva->expediente_id,
                    'usuario_atendido_id' => $lockedReserva->usuario_atendido_id,
                    'estratega_id' => $lockedReserva->estratega_id,
                    'supervisor_id' => $lockedReserva->supervisor_id,
                ],
                'status' => 'pendiente',
            ]);
        }, 3);

        return redirect()->route('consultorios.index')->with('status', 'Solicitud enviada al administrador.');
    }

    public function requestDestroy(Request $request, ConsultorioReserva $reserva): RedirectResponse
    {
        abort_unless($this->canRequestBitacoraChanges($request), 403);
        if (! $this->hasSolicitudesTable()) {
            return redirect()->route('consultorios.index')->with('status', 'Las solicitudes de baja no están disponibles temporalmente.');
        }

        DB::transaction(function () use ($request, $reserva): void {
            $lockedReserva = ConsultorioReserva::query()->lockForUpdate()->findOrFail($reserva->getKey());
            $this->assertReservationIsMutable($lockedReserva);
            ConsultorioReservaSolicitud::query()->create([
                'consultorio_reserva_id' => $lockedReserva->id,
                'requested_by' => $request->user()->id,
                'tipo' => 'baja',
                'payload' => null,
                'status' => 'pendiente',
            ]);
        }, 3);

        return redirect()->route('consultorios.index')->with('status', 'Solicitud enviada al administrador.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        abort_unless($this->canManageBitacora($request), 403);

        $ids = collect($request->input('reservas', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return redirect()
                ->route('consultorios.index', $request->query())
                ->with('status', 'Selecciona al menos un registro para eliminar.');
        }

        $eliminadas = ConsultorioReserva::query()
            ->whereIn('id', $ids)
            ->toBase()
            ->delete();

        return redirect()
            ->route('consultorios.index', $request->query())
            ->with('status', $eliminadas > 1
                ? 'Reservas eliminadas correctamente.'
                : ($eliminadas === 1
                    ? 'Reserva eliminada correctamente.'
                    : 'No se encontró un registro vigente para eliminar.'));
    }

    private function reservationHasPendingRequest(ConsultorioReserva $reserva): bool
    {
        return $this->hasSolicitudesTable()
            && ConsultorioReservaSolicitud::query()
                ->where('consultorio_reserva_id', $reserva->getKey())
                ->where('status', 'pendiente')
                ->exists();
    }

    private function assertReservationIsMutable(ConsultorioReserva $reserva): void
    {
        if ($reserva->expediente?->trashed()) {
            throw ValidationException::withMessages([
                'reserva' => 'Un expediente archivado es de solo lectura; su reserva no puede modificarse.',
            ]);
        }
    }
}
