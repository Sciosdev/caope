<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpedienteRequest;
use App\Http\Requests\UpdateExpedienteRequest;
use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\Expediente;
use App\Models\User;
use App\Services\ExpedienteStateValidator;
use App\Services\TimelineLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpedienteController extends Controller
{
    /**
     * Campos relevantes para auditoría en timeline.
     *
     * @var list<string>
     */
    protected const TIMELINE_FIELDS = [
        'no_control',
        'paciente',
        'apertura',
        'carrera',
        'turno',
        'estado',
        'tutor_id',
        'coordinador_id',
    ];

    public function __construct(
        private TimelineLogger $timelineLogger,
        private ExpedienteStateValidator $stateValidator,
    )
    {
        $this->middleware('permission:expedientes.view')->only('index');
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expediente::class);

        $busqueda = (string) $request->input('q', '');
        $estado = (string) $request->input('estado', '');
        $carrera = (string) $request->input('carrera', '');
        $turno = (string) $request->input('turno', '');
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
                    $w->where('no_control', 'like', "%{$busqueda}%")
                        ->orWhere('paciente', 'like', "%{$busqueda}%");
                });
            })
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($carrera, fn ($q) => $q->where('carrera', $carrera))
            ->when($turno, fn ($q) => $q->where('turno', $turno))
            ->when($desde, fn ($q) => $q->whereDate('apertura', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('apertura', '<=', $hasta))
            ->orderByDesc('apertura');

        $expedientes = $query->paginate(10)->withQueryString();

        $carreras = CatalogoCarrera::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre');

        $turnos = CatalogoTurno::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre');

        return view('expedientes.index', [
            'expedientes' => $expedientes,
            'q' => $busqueda,
            'estado' => $estado,
            'desde' => $desde,
            'hasta' => $hasta,
            'carrera' => $carrera,
            'turno' => $turno,
            'carreras' => $carreras,
            'turnos' => $turnos,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Expediente::class);

        $options = $this->formOptions();

        return view('expedientes.create', array_merge($options, [
            'expediente' => new Expediente([
                'apertura' => Carbon::today(),
            ]),
        ]));
    }

    public function store(StoreExpedienteRequest $request): RedirectResponse
    {
        $data = $request->validatedExpedienteData();
        $data['creado_por'] = $request->user()->id;
        $data['estado'] = $data['estado'] ?? 'abierto';

        $expediente = Expediente::create($data);

        $this->timelineLogger->log($expediente, 'expediente.creado', $request->user(), [
            'datos' => Arr::only($expediente->toArray(), self::TIMELINE_FIELDS),
        ]);

        return redirect()
            ->route('expedientes.show', $expediente)
            ->with('status', 'Expediente creado correctamente.');
    }

    public function show(Expediente $expediente): View
    {
        $this->authorize('view', $expediente);

        $expediente->load([
            'creadoPor',
            'tutor',
            'coordinador',
            'sesiones' => fn ($q) => $q->with(['realizadaPor', 'validadaPor'])->orderByDesc('fecha'),
            'consentimientos' => fn ($q) => $q->orderByDesc('requerido')->orderBy('tratamiento'),
            'anexos' => fn ($q) => $q->with('subidoPor')->latest(),
            'timelineEventos' => fn ($q) => $q->with('actor')->orderByDesc('created_at'),
        ]);

        return view('expedientes.show', [
            'expediente' => $expediente,
            'sesiones' => $expediente->sesiones,
            'consentimientos' => $expediente->consentimientos,
            'anexos' => $expediente->anexos,
            'timelineEventos' => $expediente->timelineEventos,
            'timelineEventosRecientes' => $expediente->timelineEventos->take(5),
            'availableStates' => [
                'abierto' => 'Abierto',
                'revision' => 'En revisión',
                'cerrado' => 'Cerrado',
            ],
        ]);
    }

    public function edit(Expediente $expediente): View
    {
        $this->authorize('update', $expediente);

        $options = $this->formOptions();

        return view('expedientes.edit', array_merge($options, [
            'expediente' => $expediente,
        ]));
    }

    public function update(UpdateExpedienteRequest $request, Expediente $expediente): RedirectResponse
    {
        $data = $request->validatedExpedienteData();
        $before = Arr::only($expediente->getAttributes(), self::TIMELINE_FIELDS);

        $expediente->fill($data);
        $expediente->save();

        $expediente->refresh();

        $after = Arr::only($expediente->getAttributes(), self::TIMELINE_FIELDS);
        $cambios = collect($after)
            ->filter(fn ($value, $key) => ($before[$key] ?? null) !== $value)
            ->keys()
            ->all();

        if (! empty($cambios)) {
            $this->timelineLogger->log($expediente, 'expediente.actualizado', $request->user(), [
                'antes' => Arr::only($before, $cambios),
                'despues' => Arr::only($after, $cambios),
                'campos' => $cambios,
            ]);
        }

        return redirect()
            ->route('expedientes.show', $expediente)
            ->with('status', 'Expediente actualizado correctamente.');
    }

    public function destroy(Expediente $expediente): RedirectResponse
    {
        $this->authorize('delete', $expediente);

        $expediente->delete();

        return redirect()
            ->route('expedientes.index')
            ->with('status', 'Expediente eliminado correctamente.');
    }

    public function changeState(Request $request, Expediente $expediente): RedirectResponse
    {
        $this->authorize('changeState', $expediente);

        $validated = $request->validate([
            'estado' => ['required', Rule::in(['abierto', 'revision', 'cerrado'])],
        ]);

        $nuevoEstado = $validated['estado'];
        $estadoAnterior = $expediente->estado;

        if ($nuevoEstado === $estadoAnterior) {
            return redirect()
                ->route('expedientes.show', $expediente)
                ->with('status', 'El estado seleccionado es el mismo que el actual.');
        }

        if ($nuevoEstado === 'cerrado') {
            $erroresCierre = $this->stateValidator->validateClosureRequirements($expediente);

            if ($erroresCierre->isNotEmpty()) {
                return redirect()
                    ->route('expedientes.show', $expediente)
                    ->withErrors(['estado' => $erroresCierre->all()]);
            }
        }

        $expediente->update(['estado' => $nuevoEstado]);

        $this->timelineLogger->log($expediente, 'expediente.estado_cambiado', $request->user(), [
            'antes' => $estadoAnterior,
            'despues' => $nuevoEstado,
        ]);

        return redirect()
            ->route('expedientes.show', $expediente)
            ->with('status', 'Estado del expediente actualizado correctamente.');
    }

    protected function formOptions(): array
    {
        $carreras = CatalogoCarrera::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre');

        $turnos = CatalogoTurno::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre');

        $tutores = User::role('docente')->orderBy('name')->get();
        $coordinadores = User::role('coordinador')->orderBy('name')->get();

        return [
            'carreras' => $carreras,
            'turnos' => $turnos,
            'tutores' => $tutores,
            'coordinadores' => $coordinadores,
        ];
    }

}
