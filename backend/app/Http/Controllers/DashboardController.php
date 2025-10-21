<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use App\Services\DashboardInsightsService;
use App\Notifications\ExpedienteClosureAttemptNotification;
use App\Notifications\SesionObservedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardInsightsService $insights)
    {
    }

    public function index(Request $request): View
    {
        return view('dashboard.index');
    }

    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();

        $cards = collect([
            $this->validationsCard($user),
            $this->observedCard($user),
            $this->closureAttemptsCard($user),
        ])->filter()->values();

        return response()->json([
            'cards' => $cards,
        ]);
    }

    public function metrics(Request $request): JsonResponse
    {
        $stateCounts = $this->insights->getExpedienteCountsByState();
        $averageValidation = $this->insights->getAverageValidationTime();

        return response()->json([
            'expedientes' => [
                'total' => array_sum($stateCounts),
                'por_estado' => $stateCounts,
            ],
            'sesiones' => [
                'tiempo_promedio_validacion' => $averageValidation,
            ],
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $requestedDays = $request->query('days');
        $requestedDays = is_numeric($requestedDays) ? (int) $requestedDays : null;

        $thresholdDays = $this->insights->getStalledThresholdDays($requestedDays);

        $alerts = $this->insights
            ->getStalledExpedientes($thresholdDays, $request->user())
            ->map(function (array $alert) {
                $alert['url'] = route('expedientes.show', $alert['id']);

                return $alert;
            })
            ->values();

        return response()->json([
            'threshold_days' => $thresholdDays,
            'alerts' => $alerts,
        ]);
    }

    private function validationsCard(User $user): ?array
    {
        if (! $user->can('sesiones.validate') && ! $user->can('expedientes.manage')) {
            return null;
        }

        $query = Sesion::query()
            ->with(['expediente:id,no_control,paciente'])
            ->where('status_revision', 'pendiente');

        if (! $user->can('expedientes.manage')) {
            $query->whereHas('expediente', fn ($q) => $q->where('tutor_id', $user->id));
        }

        $total = (clone $query)->count();

        $items = (clone $query)
            ->orderByDesc('fecha')
            ->take(5)
            ->get()
            ->map(fn (Sesion $sesion) => [
                'id' => $sesion->id,
                'primary' => sprintf('Sesión #%d', $sesion->id),
                'secondary' => $this->formatSecondaryLine(
                    $sesion->expediente?->no_control,
                    $sesion->expediente?->paciente,
                    $sesion->fecha
                ),
                'url' => route('expedientes.sesiones.show', [$sesion->expediente_id, $sesion->id]),
            ])
            ->all();

        return [
            'id' => 'validaciones',
            'title' => 'Sesiones pendientes de validar',
            'description' => 'Listado de sesiones que requieren tu validación.',
            'count' => $total,
            'items' => $items,
            'link' => $this->validationsLink(),
            'variant' => 'primary',
        ];
    }

    private function observedCard(User $user): ?array
    {
        $notifications = $user
            ->unreadNotifications()
            ->where('type', SesionObservedNotification::class)
            ->orderByDesc('created_at');

        $total = $notifications->count();

        if ($total === 0) {
            if (! $user->can('expedientes.manage') && ! $user->can('sesiones.validate') && ! $user->hasRole('alumno')) {
                return null;
            }

            return [
                'id' => 'observados',
                'title' => 'Sesiones observadas',
                'description' => 'Seguimiento a sesiones con observaciones pendientes.',
                'count' => 0,
                'items' => [],
                'link' => null,
                'variant' => 'warning',
            ];
        }

        $items = $notifications
            ->limit(5)
            ->get()
            ->map(function ($notification) {
                $data = $notification->data;
                $expedienteId = Arr::get($data, 'expediente_id');
                $sesionId = Arr::get($data, 'sesion_id');

                return [
                    'id' => $notification->id,
                    'primary' => Arr::get($data, 'message', 'Sesión con observaciones'),
                    'secondary' => $this->formatSecondaryLine(
                        Arr::get($data, 'expediente_no_control'),
                        Arr::get($data, 'actor_name'),
                        Arr::get($data, 'fecha') ? Carbon::parse($data['fecha']) : null
                    ),
                    'url' => ($expedienteId && $sesionId)
                        ? route('expedientes.sesiones.show', [$expedienteId, $sesionId])
                        : null,
                ];
            })
            ->all();

        return [
            'id' => 'observados',
            'title' => 'Sesiones observadas',
            'description' => 'Seguimiento a sesiones con observaciones pendientes.',
            'count' => $total,
            'items' => $items,
            'link' => null,
            'variant' => 'warning',
        ];
    }

    private function closureAttemptsCard(User $user): ?array
    {
        $notifications = $user
            ->unreadNotifications()
            ->where('type', ExpedienteClosureAttemptNotification::class)
            ->orderByDesc('created_at');

        $total = $notifications->count();

        if ($total === 0) {
            if (! $user->can('expedientes.manage') && ! $user->hasRole('docente') && ! $user->hasRole('coordinador')) {
                return null;
            }

            return [
                'id' => 'intentos_cierre',
                'title' => 'Intentos de cierre con observaciones',
                'description' => 'Expedientes que no pudieron cerrarse y requieren seguimiento.',
                'count' => 0,
                'items' => [],
                'link' => route('expedientes.index'),
                'variant' => 'danger',
            ];
        }

        $items = $notifications
            ->limit(5)
            ->get()
            ->map(function ($notification) {
                $data = $notification->data;
                $expedienteId = Arr::get($data, 'expediente_id');

                return [
                    'id' => $notification->id,
                    'primary' => Arr::get($data, 'message', 'Intento de cierre fallido'),
                    'secondary' => $this->formatSecondaryLine(
                        Arr::get($data, 'expediente_no_control'),
                        Arr::get($data, 'actor_name'),
                        $notification->created_at
                    ),
                    'url' => $expedienteId ? route('expedientes.show', $expedienteId) : null,
                ];
            })
            ->all();

        return [
            'id' => 'intentos_cierre',
            'title' => 'Intentos de cierre con observaciones',
            'description' => 'Expedientes que no pudieron cerrarse y requieren seguimiento.',
            'count' => $total,
            'items' => $items,
            'link' => route('expedientes.index'),
            'variant' => 'danger',
        ];
    }

    private function validationsLink(): ?string
    {
        $routeName = collect([
            'sesiones.validacion',
            'sesiones.validacion.index',
            'sesiones.validar.index',
            'sesiones.validation.index',
        ])->first(fn ($routeName) => \Route::has($routeName));

        return $routeName ? route($routeName) : null;
    }

    private function formatSecondaryLine(?string $reference, ?string $context, $date): string
    {
        $parts = collect([$reference, $context])
            ->filter()
            ->values()
            ->all();

        $formattedDate = null;

        if ($date instanceof Carbon) {
            $formattedDate = $date->format('d/m/Y');
        } elseif (is_string($date) && $date !== '') {
            $formattedDate = Carbon::parse($date)->format('d/m/Y');
        }

        if ($formattedDate) {
            $parts[] = $formattedDate;
        }

        return implode(' • ', $parts);
    }
}
