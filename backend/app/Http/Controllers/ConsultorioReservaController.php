<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConsultorioReservaRequest;
use App\Http\Requests\UpdateConsultorioReservaRequest;
use App\Models\CatalogoCubiculo;
use App\Models\CatalogoConsultorio;
use App\Models\CatalogoEstrategia;
use App\Models\ConsultorioReserva;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ConsultorioReservaController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'coordinador', 'alumno']), 403);

        $fechaFiltro = $request->string('fecha')->toString() ?: now()->toDateString();
        $bitacoraModo = $request->string('bitacora_modo')->toString() === 'mes' ? 'mes' : 'semana';
        $bitacoraFechaBase = Carbon::parse($request->string('bitacora_inicio')->toString() ?: $fechaFiltro);
        $bitacoraInicio = ($bitacoraModo === 'mes' ? $bitacoraFechaBase->copy()->startOfMonth() : $bitacoraFechaBase->copy()->startOfWeek(Carbon::MONDAY))->toDateString();
        $bitacoraFin = ($bitacoraModo === 'mes' ? $bitacoraFechaBase->copy()->endOfMonth() : $bitacoraFechaBase->copy()->endOfWeek(Carbon::SUNDAY))->toDateString();
        $consultoriosActivos = CatalogoConsultorio::activos();
        $cubiculosActivos = CatalogoCubiculo::activos();
        $cubiculosDisponibles = $cubiculosActivos->pluck('numero')->map(fn ($numero) => (int) $numero)->values();
        $defaultCubiculo = (int) ($cubiculosDisponibles->first() ?? 1);
        $consultorioSeleccionado = (int) $request->integer('consultorio_numero', (int) ($consultoriosActivos->first()->numero ?? 1));
        $cubiculoSolicitado = (int) $request->integer('cubiculo_numero', $defaultCubiculo);
        $cubiculoSeleccionado = $request->filled('cubiculo_numero') && $cubiculosDisponibles->contains($cubiculoSolicitado)
            ? $cubiculoSolicitado
            : null;

        $reservas = ConsultorioReserva::query()
            ->with(['usuarioAtendido', 'estratega', 'supervisor', 'creadoPor'])
            ->whereBetween('fecha', [$bitacoraInicio, $bitacoraFin])
            ->orderBy('fecha')
            ->orderBy('consultorio_numero')
            ->orderBy('cubiculo_numero')
            ->orderBy('hora_inicio')
            ->paginate(25)
            ->withQueryString();

        $ocupacionPorCubiculo = ConsultorioReserva::query()
            ->with(['usuarioAtendido', 'estratega', 'supervisor'])
            ->whereDate('fecha', $fechaFiltro)
            ->where('consultorio_numero', $consultorioSeleccionado)
            ->when($cubiculoSeleccionado, fn ($query) => $query->where('cubiculo_numero', $cubiculoSeleccionado))
            ->orderBy('cubiculo_numero')
            ->orderBy('hora_inicio')
            ->get()
            ->groupBy('cubiculo_numero');

        $usuariosActivos = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $docentes = User::role('docente')->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('consultorios.index', [
            'reservas' => $reservas,
            'ocupacionPorCubiculo' => $ocupacionPorCubiculo,
            'fechaFiltro' => $fechaFiltro,
            'bitacoraInicio' => $bitacoraInicio,
            'bitacoraFin' => $bitacoraFin,
            'bitacoraModo' => $bitacoraModo,
            'consultorioSeleccionado' => $consultorioSeleccionado,
            'cubiculoSeleccionado' => $cubiculoSeleccionado,
            'usuarios' => $usuariosActivos,
            'docentes' => $docentes,
            'consultoriosActivos' => $consultoriosActivos,
            'cubiculosActivos' => $cubiculosActivos,
            'estrategiasActivas' => CatalogoEstrategia::activos(),
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'coordinador', 'alumno']), 403);

        $fecha = $request->string('fecha')->toString() ?: now()->toDateString();
        $fechaInicio = $request->string('fecha_inicio')->toString();
        $fechaFin = $request->string('fecha_fin')->toString();
        $consultorioNumero = $request->filled('consultorio_numero')
            ? (int) $request->integer('consultorio_numero')
            : null;

        $reservas = ConsultorioReserva::query()
            ->with(['usuarioAtendido:id,name', 'estratega:id,name'])
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
            ->orderBy('hora_inicio')
            ->get(['fecha', 'consultorio_numero', 'cubiculo_numero', 'hora_inicio', 'hora_fin', 'estrategia', 'usuario_atendido_id', 'estratega_id'])
            ->map(fn (ConsultorioReserva $reserva) => [
                'fecha' => $reserva->fecha,
                'consultorio_numero' => $reserva->consultorio_numero,
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

    public function edit(Request $request, ConsultorioReserva $reserva): View
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        return view('consultorios.edit', [
            'reserva' => $reserva->load(['usuarioAtendido', 'estratega', 'supervisor']),
            'usuarios' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'docentes' => User::role('docente')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'consultoriosActivos' => CatalogoConsultorio::activos(),
            'cubiculosActivos' => CatalogoCubiculo::activos(),
            'estrategiasActivas' => CatalogoEstrategia::activos(),
        ]);
    }

    public function store(StoreConsultorioReservaRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $baseData = Arr::only($validated, [
            'hora_inicio',
            'hora_fin',
            'consultorio_numero',
            'cubiculo_numero',
            'estrategia',
            'usuario_atendido_id',
            'estratega_id',
            'supervisor_id',
        ]);

        foreach ($request->reservationDates() as $fecha) {
            ConsultorioReserva::query()->create($baseData + [
                'fecha' => $fecha,
                'creado_por' => $request->user()->id,
            ]);
        }

        return redirect()->route('consultorios.index')->with('status', 'Reserva registrada correctamente.');
    }

    public function update(UpdateConsultorioReservaRequest $request, ConsultorioReserva $reserva): RedirectResponse
    {
        $reserva->update($request->validated());

        return redirect()->route('consultorios.index')->with('status', 'Reserva actualizada correctamente.');
    }

    public function destroy(Request $request, ConsultorioReserva $reserva): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        ConsultorioReserva::query()
            ->whereKey($reserva->getKey())
            ->toBase()
            ->delete();

        return redirect()->route('consultorios.index')->with('status', 'Reserva eliminada correctamente.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

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
}
