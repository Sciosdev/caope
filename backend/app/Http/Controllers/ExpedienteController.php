<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpedienteRequest;
use App\Http\Requests\UpdateExpedienteRequest;
use App\Models\Anexo;
use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\Expediente;
use App\Models\Parametro;
use App\Models\User;
use App\Notifications\ExpedienteClosedNotification;
use App\Notifications\ExpedienteClosureAttemptNotification;
use App\Notifications\TutorAssignedNotification;
use App\Services\ExpedienteStateValidator;
use App\Services\TimelineLogger;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
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
        'diagnostico',
        'dsm_tr',
        'observaciones_relevantes',
        'antecedentes_familiares',
        'antecedentes_observaciones',
        'antecedentes_personales_patologicos',
        'antecedentes_personales_observaciones',
        'antecedente_padecimiento_actual',
        'plan_accion',
        'aparatos_sistemas',
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
            ->with('creadoPor')
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

        $carreras = CatalogoCarrera::activos()->pluck('nombre');

        $turnos = CatalogoTurno::activos()->pluck('nombre');

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

    public function store(StoreExpedienteRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validatedExpedienteData();
        $historyProvided = array_key_exists('antecedentes_familiares', $data)
            || array_key_exists('antecedentes_observaciones', $data);
        $personalHistoryProvided = array_key_exists('antecedentes_personales_patologicos', $data)
            || array_key_exists('antecedentes_personales_observaciones', $data);
        $systemsProvided = array_key_exists('aparatos_sistemas', $data)
            || array_key_exists('antecedente_padecimiento_actual', $data);
        $planAccionProvided = array_key_exists('plan_accion', $data)
            && $data['plan_accion'] !== null
            && $data['plan_accion'] !== '';
        $diagnosticoProvided = array_key_exists('diagnostico', $data)
            && $data['diagnostico'] !== null
            && $data['diagnostico'] !== '';
        $dsmTrProvided = array_key_exists('dsm_tr', $data)
            && $data['dsm_tr'] !== null
            && $data['dsm_tr'] !== '';
        $observacionesRelevantesProvided = array_key_exists('observaciones_relevantes', $data)
            && $data['observaciones_relevantes'] !== null
            && $data['observaciones_relevantes'] !== '';

        if (! array_key_exists('antecedentes_familiares', $data)) {
            $data['antecedentes_familiares'] = Expediente::defaultFamilyHistory();
        }

        $data['antecedentes_observaciones'] = $data['antecedentes_observaciones'] ?? null;
        if (! array_key_exists('antecedentes_personales_patologicos', $data)) {
            $data['antecedentes_personales_patologicos'] = Expediente::defaultPersonalPathologicalHistory();
        }

        $data['antecedentes_personales_observaciones'] = $data['antecedentes_personales_observaciones'] ?? null;
        $data['antecedente_padecimiento_actual'] = $data['antecedente_padecimiento_actual'] ?? null;
        $data['plan_accion'] = $data['plan_accion'] ?? null;
        $data['diagnostico'] = $data['diagnostico'] ?? null;
        $data['dsm_tr'] = $data['dsm_tr'] ?? null;
        $data['observaciones_relevantes'] = $data['observaciones_relevantes'] ?? null;
        if (! array_key_exists('aparatos_sistemas', $data)) {
            $data['aparatos_sistemas'] = Expediente::defaultSystemsReview();
        }
        $data['creado_por'] = $request->user()->id;
        $data['estado'] = $data['estado'] ?? 'abierto';

        try {
            $expediente = Expediente::create($data);
        } catch (QueryException $exception) {
            report($exception);

            $errorMessage = __('expedientes.messages.student_save_error');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $errorMessage,
                    'errors' => [
                        'expediente' => [$errorMessage],
                    ],
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['expediente' => $errorMessage]);
        }

        $this->logTimelineEvent($expediente, 'expediente.creado', $request->user(), [
            'datos' => Arr::only($expediente->toArray(), self::TIMELINE_FIELDS),
        ]);

        if (
            $request->user()->hasRole('alumno')
            && (
                $historyProvided
                || $personalHistoryProvided
                || $systemsProvided
                || $planAccionProvided
                || $diagnosticoProvided
                || $dsmTrProvided
                || $observacionesRelevantesProvided
            )
        ) {
            $this->logTimelineEvent($expediente, 'expediente.antecedentes_registrados', $request->user(), [
                'datos' => [
                    'familiares' => $expediente->antecedentes_familiares,
                    'observaciones' => $expediente->antecedentes_observaciones,
                    'personales' => $expediente->antecedentes_personales_patologicos,
                    'personales_observaciones' => $expediente->antecedentes_personales_observaciones,
                    'padecimiento_actual' => $expediente->antecedente_padecimiento_actual,
                    'plan_accion' => $expediente->plan_accion,
                    'aparatos_sistemas' => $expediente->aparatos_sistemas,
                    'diagnostico' => $expediente->diagnostico,
                    'dsm_tr' => $expediente->dsm_tr,
                    'observaciones_relevantes' => $expediente->observaciones_relevantes,
                ],
            ]);
        }

        if ($request->expectsJson()) {
            $this->loadExpedienteForApi($expediente);

            return response()->json([
                'message' => __('expedientes.messages.store_success'),
                'expediente' => $expediente,
                'student_error_message' => __('expedientes.messages.student_save_error'),
            ], 201);
        }

        return redirect()
            ->route('expedientes.show', $expediente)
            ->with('status', __('expedientes.messages.store_success'));
    }

    public function show(Request $request, Expediente $expediente): View
    {
        $this->authorize('view', $expediente);

        $filterValues = collect($request->only(['titulo', 'tipo']))
            ->map(fn ($value) => is_string($value) ? trim($value) : '')
            ->all();

        $activeFilters = collect($filterValues)
            ->filter(fn ($value) => $value !== '')
            ->all();

        $anexosTipos = $expediente->anexos()
            ->select('tipo')
            ->distinct()
            ->orderBy('tipo')
            ->pluck('tipo')
            ->filter()
            ->values();

        $expediente->load([
            'creadoPor',
            'tutor',
            'coordinador',
            'sesiones' => fn ($q) => $q->with(['realizadaPor', 'validadaPor'])->orderByDesc('fecha'),
            'consentimientos' => fn ($q) => $q->with('subidoPor')->orderByDesc('requerido')->orderBy('tratamiento'),
            'anexos' => fn ($q) => $q->with('subidoPor')->filter($activeFilters)->latest(),
            'timelineEventos' => fn ($q) => $q->with('actor')->orderByDesc('created_at'),
        ]);

        $this->hydrateAnexoLinks($expediente);

        $anexosMimes = (string) Parametro::obtener('uploads.anexos.mimes', 'pdf,jpg,jpeg,png,doc,docx,xls,xlsx,ppt,pptx,txt,csv');
        $anexosMax = (int) Parametro::obtener('uploads.anexos.max', 51200);
        $consentimientoMimes = (string) Parametro::obtener('uploads.consentimientos.mimes', 'pdf,jpg,jpeg');
        $consentimientoMax = (int) Parametro::obtener('uploads.consentimientos.max', 5120);

        return view('expedientes.show', [
            'expediente' => $expediente,
            'sesiones' => $expediente->sesiones,
            'consentimientos' => $expediente->consentimientos,
            'anexos' => $expediente->anexos,
            'anexosFilters' => $filterValues,
            'anexosTipos' => $anexosTipos,
            'activeTab' => $request->query('tab'),
            'timelineEventos' => $expediente->timelineEventos,
            'timelineEventosRecientes' => $expediente->timelineEventos->take(5),
            'availableStates' => [
                'abierto' => 'Abierto',
                'revision' => 'En revisión',
                'cerrado' => 'Cerrado',
            ],
            'anexosUploadMimes' => $anexosMimes,
            'anexosUploadMax' => $anexosMax,
            'consentimientosUploadMimes' => $consentimientoMimes,
            'consentimientosUploadMax' => $consentimientoMax,
            'familyHistoryMembers' => Expediente::FAMILY_HISTORY_MEMBERS,
            'hereditaryHistoryConditions' => Expediente::HEREDITARY_HISTORY_CONDITIONS,
            'personalPathologicalConditions' => Expediente::PERSONAL_PATHOLOGICAL_CONDITIONS,
            'systemsReviewSections' => Expediente::SYSTEMS_REVIEW_SECTIONS,
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

    public function update(UpdateExpedienteRequest $request, Expediente $expediente): JsonResponse|RedirectResponse
    {
        $data = $request->validatedExpedienteData();
        $before = Arr::only($expediente->getAttributes(), self::TIMELINE_FIELDS);
        $familyHistoryBefore = $expediente->antecedentes_familiares ?? Expediente::defaultFamilyHistory();
        $familyObservationsBefore = $expediente->antecedentes_observaciones;
        $personalHistoryBefore = $expediente->antecedentes_personales_patologicos
            ?? Expediente::defaultPersonalPathologicalHistory();
        $personalObservationsBefore = $expediente->antecedentes_personales_observaciones;
        $systemsReviewBefore = $expediente->aparatos_sistemas ?? Expediente::defaultSystemsReview();
        $currentConditionBefore = $expediente->antecedente_padecimiento_actual;
        $planActionBefore = $expediente->plan_accion;
        $diagnosticoBefore = $expediente->diagnostico;
        $dsmTrBefore = $expediente->dsm_tr;
        $observacionesRelevantesBefore = $expediente->observaciones_relevantes;
        $antecedentesAntes = [
            'familiares' => $familyHistoryBefore,
            'observaciones' => $familyObservationsBefore,
            'personales' => $personalHistoryBefore,
            'personales_observaciones' => $personalObservationsBefore,
            'padecimiento_actual' => $currentConditionBefore,
            'plan_accion' => $planActionBefore,
            'aparatos_sistemas' => $systemsReviewBefore,
            'diagnostico' => $diagnosticoBefore,
            'dsm_tr' => $dsmTrBefore,
            'observaciones_relevantes' => $observacionesRelevantesBefore,
        ];

        $expediente->fill($data);
        $expediente->save();

        $familyHistoryChanged = $expediente->wasChanged('antecedentes_familiares')
            || $expediente->wasChanged('antecedentes_observaciones');
        $personalHistoryChanged = $expediente->wasChanged('antecedentes_personales_patologicos')
            || $expediente->wasChanged('antecedentes_personales_observaciones');
        $systemsHistoryChanged = $expediente->wasChanged('aparatos_sistemas')
            || $expediente->wasChanged('antecedente_padecimiento_actual');
        $planActionChanged = $expediente->wasChanged('plan_accion');
        $diagnosticoChanged = $expediente->wasChanged('diagnostico')
            || $expediente->wasChanged('dsm_tr')
            || $expediente->wasChanged('observaciones_relevantes');

        $expediente->refresh();

        $after = Arr::only($expediente->getAttributes(), self::TIMELINE_FIELDS);
        $cambios = collect($after)
            ->filter(fn ($value, $key) => ($before[$key] ?? null) !== $value)
            ->keys()
            ->all();

        if (! empty($cambios)) {
            $this->logTimelineEvent($expediente, 'expediente.actualizado', $request->user(), [
                'antes' => Arr::only($before, $cambios),
                'despues' => Arr::only($after, $cambios),
                'campos' => $cambios,
            ]);

            if (in_array('tutor_id', $cambios, true)) {
                $expediente->loadMissing('tutor');
                $tutor = $expediente->tutor;

                if ($tutor) {
                    $tutor->notify(new TutorAssignedNotification($expediente, $request->user()));
                }
            }
        }

        if (
            $request->user()->hasRole('alumno')
            && (
                $familyHistoryChanged
                || $personalHistoryChanged
                || $systemsHistoryChanged
                || $planActionChanged
                || $diagnosticoChanged
            )
        ) {
            $this->logTimelineEvent($expediente, 'expediente.antecedentes_actualizados', $request->user(), [
                'antes' => $antecedentesAntes,
                'despues' => [
                    'familiares' => $expediente->antecedentes_familiares ?? Expediente::defaultFamilyHistory(),
                    'observaciones' => $expediente->antecedentes_observaciones,
                    'personales' => $expediente->antecedentes_personales_patologicos
                        ?? Expediente::defaultPersonalPathologicalHistory(),
                    'personales_observaciones' => $expediente->antecedentes_personales_observaciones,
                    'padecimiento_actual' => $expediente->antecedente_padecimiento_actual,
                    'plan_accion' => $expediente->plan_accion,
                    'aparatos_sistemas' => $expediente->aparatos_sistemas ?? Expediente::defaultSystemsReview(),
                    'diagnostico' => $expediente->diagnostico,
                    'dsm_tr' => $expediente->dsm_tr,
                    'observaciones_relevantes' => $expediente->observaciones_relevantes,
                ],
            ]);
        }

        if ($request->expectsJson()) {
            $expediente->refresh();
            $this->loadExpedienteForApi($expediente);

            return response()->json([
                'message' => __('expedientes.messages.update_success'),
                'expediente' => $expediente,
                'student_error_message' => __('expedientes.messages.student_save_error'),
            ]);
        }

        return redirect()
            ->route('expedientes.show', $expediente)
            ->with('status', __('expedientes.messages.update_success'));
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
                $this->notifyClosureAttempt($expediente, $request->user(), $erroresCierre->all());

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

        if ($nuevoEstado === 'cerrado') {
            $this->notifyClosureSuccess($expediente, $request->user());
        }

        return redirect()
            ->route('expedientes.show', $expediente)
            ->with('status', 'Estado del expediente actualizado correctamente.');
    }

    private function logTimelineEvent(Expediente $expediente, string $event, ?Authenticatable $actor, array $payload = []): void
    {
        if (! Schema::hasTable('timeline_eventos')) {
            return;
        }

        try {
            $this->timelineLogger->log($expediente, $event, $actor, $payload);
        } catch (QueryException $exception) {
            report($exception);
        }
    }

    protected function formOptions(): array
    {
        $carreras = CatalogoCarrera::activos()->pluck('nombre');

        $turnos = CatalogoTurno::activos()->pluck('nombre');

        $tutores = User::role('docente')->orderBy('name')->get();
        $coordinadores = User::role('coordinador')->orderBy('name')->get();

        return [
            'carreras' => $carreras,
            'turnos' => $turnos,
            'tutores' => $tutores,
            'coordinadores' => $coordinadores,
            'familyHistoryMembers' => Expediente::FAMILY_HISTORY_MEMBERS,
            'hereditaryHistoryConditions' => Expediente::HEREDITARY_HISTORY_CONDITIONS,
            'personalPathologicalConditions' => Expediente::PERSONAL_PATHOLOGICAL_CONDITIONS,
            'systemsReviewSections' => Expediente::SYSTEMS_REVIEW_SECTIONS,
        ];
    }

    private function loadExpedienteForApi(Expediente $expediente): void
    {
        $expediente->load([
            'alumno',
            'tutor',
            'coordinador',
            'anexos' => fn ($query) => $query->with('subidoPor')->latest(),
        ]);

        $this->hydrateAnexoLinks($expediente);
    }

    private function hydrateAnexoLinks(Expediente $expediente): void
    {
        $expediente->anexos->each(function (Anexo $anexo) use ($expediente) {
            $anexo->setAttribute(
                'download_url',
                URL::temporarySignedRoute(
                    'expedientes.anexos.show',
                    now()->addMinutes(30),
                    [$expediente, $anexo]
                )
            );

            $anexo->setAttribute(
                'preview_url',
                URL::temporarySignedRoute(
                    'expedientes.anexos.preview',
                    now()->addMinutes(30),
                    [$expediente, $anexo]
                )
            );
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function expedienteContacts(Expediente $expediente): Collection
    {
        $expediente->loadMissing(['tutor', 'creadoPor', 'coordinador']);

        return collect([$expediente->tutor, $expediente->creadoPor, $expediente->coordinador])
            ->filter()
            ->unique(fn (User $user) => $user->id)
            ->values();
    }

    /**
     * @param  list<string>  $errores
     */
    private function notifyClosureAttempt(Expediente $expediente, User $actor, array $errores): void
    {
        $this->expedienteContacts($expediente)
            ->each(fn (User $user) => $user->notify(new ExpedienteClosureAttemptNotification($expediente, $actor, $errores)));
    }

    private function notifyClosureSuccess(Expediente $expediente, User $actor): void
    {
        $this->expedienteContacts($expediente)
            ->each(fn (User $user) => $user->notify(new ExpedienteClosedNotification($expediente, $actor)));
    }

}

