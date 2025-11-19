<?php

namespace App\Services;

use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use Carbon\CarbonInterval;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class DashboardInsightsService
{
    /**
     * Obtiene el conteo de expedientes agrupados por estado conocido.
     */
    public function getExpedienteCountsByState(): array
    {
        return $this->remember('dashboard.metrics.counts', function () {
            $states = ['abierto', 'revision', 'cerrado'];

            $counts = Expediente::query()
                ->select('estado')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado')
                ->map(fn ($value) => (int) $value)
                ->all();

            return collect($states)
                ->mapWithKeys(fn (string $state) => [$state => $counts[$state] ?? 0])
                ->all();
        });
    }

    /**
     * Calcula el tiempo promedio que tardan en validarse las sesiones.
     */
    public function getAverageValidationTime(): array
    {
        return $this->remember('dashboard.metrics.average_validation', function () {
            $durations = Sesion::query()
                ->where('status_revision', 'validada')
                ->whereNotNull('validada_por')
                ->get(['created_at', 'updated_at'])
                ->map(function (Sesion $sesion) {
                    if (! $sesion->created_at instanceof Carbon || ! $sesion->updated_at instanceof Carbon) {
                        return null;
                    }

                    return $sesion->created_at->diffInSeconds($sesion->updated_at);
                })
                ->filter(fn (?int $seconds) => $seconds !== null && $seconds >= 0);

            $count = $durations->count();

            if ($count === 0) {
                return [
                    'seconds' => null,
                    'human' => null,
                    'count' => 0,
                ];
            }

            $averageSeconds = (int) round($durations->avg());
            $interval = CarbonInterval::seconds($averageSeconds)->cascade();

            return [
                'seconds' => $averageSeconds,
                'human' => $interval->forHumans(['parts' => 2, 'short' => true, 'join' => true]),
                'count' => $count,
            ];
        });
    }

    /**
     * Resuelve el umbral de días que se utilizará para detectar expedientes estancados.
     */
    public function getStalledThresholdDays(?int $days = null): int
    {
        return $this->resolveDays($days);
    }

    /**
     * Obtiene los expedientes que no han tenido actividad en los últimos N días.
     */
    public function getStalledExpedientes(?int $days = null, ?User $user = null): Collection
    {
        $days = $this->resolveDays($days);
        $cacheKey = sprintf('dashboard.alerts.days_%d.user_%s', $days, $user?->getKey() ?? 'all');

        return $this->remember($cacheKey, function () use ($days, $user) {
            $threshold = Carbon::now()->subDays($days);
            $now = Carbon::now();

            $expedientes = Expediente::query()
                ->with(['tutor:id,name', 'coordinador:id,name'])
                ->withAggregate('timelineEventos as ultima_bitacora', 'created_at')
                ->withAggregate('sesiones as ultima_sesion', 'updated_at')
                ->whereIn('estado', ['abierto', 'revision'])
                ->get();

            $alerts = [];

            foreach ($expedientes as $expediente) {
                if ($user !== null && $user->cannot('view', $expediente)) {
                    continue;
                }

                $lastActivity = $this->resolveLastActivity($expediente);

                if ($lastActivity instanceof Carbon && $lastActivity->greaterThan($threshold)) {
                    continue;
                }

                $alerts[] = [
                    'id' => $expediente->getKey(),
                    'no_control' => $expediente->no_control,
                    'paciente' => $expediente->paciente,
                    'estado' => $expediente->estado,
                    'apertura' => $expediente->apertura?->toDateString(),
                    'tutor' => $expediente->tutor?->name,
                    'coordinador' => $expediente->coordinador?->name,
                    'ultima_actividad' => $lastActivity?->toIso8601String(),
                    'ultima_actividad_human' => $lastActivity
                        ? $lastActivity->diffForHumans($now, ['parts' => 2, 'join' => true])
                        : null,
                    'dias_inactivo' => $lastActivity ? $lastActivity->diffInDays($now) : null,
                ];
            }

            usort($alerts, function (array $a, array $b) {
                return ($b['dias_inactivo'] <=> $a['dias_inactivo'])
                    ?: strcmp($a['no_control'], $b['no_control']);
            });

            return collect($alerts);
        });
    }

    private function resolveDays(?int $days): int
    {
        $days = $days ?? (int) config('dashboard.stalled_days', 14);

        return max(1, $days);
    }

    private function resolveLastActivity(Expediente $expediente): ?Carbon
    {
        $dates = collect([
            $expediente->updated_at,
            $expediente->created_at,
        ]);

        foreach ([
            'ultima_bitacora' => $expediente->ultima_bitacora ?? null,
            'ultima_sesion' => $expediente->ultima_sesion ?? null,
        ] as $attribute => $value) {
            $parsed = $this->parseAggregateDate($expediente, $value, $attribute);

            if ($parsed instanceof Carbon) {
                $dates->push($parsed);
            }
        }

        $dates = $dates
            ->filter(fn ($date) => $date instanceof Carbon)
            ->sortByDesc(fn (Carbon $date) => $date->getTimestamp())
            ->values();

        return $dates->first();
    }

    private function parseAggregateDate(Expediente $expediente, mixed $value, string $attribute): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable $exception) {
            Log::warning('Failed to parse dashboard aggregate date', [
                'expediente_id' => $expediente->getKey(),
                'attribute' => $attribute,
                'value' => $value,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function remember(string $key, callable $callback): mixed
    {
        $ttl = max(0, (int) config('dashboard.cache_ttl', 60));

        if ($ttl === 0) {
            return $callback();
        }

        return Cache::remember($key, $ttl, $callback);
    }
}

