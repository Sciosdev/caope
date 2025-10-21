<?php

namespace App\Services;

use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardInsightsService
{
    /**
     * Obtiene el conteo de expedientes agrupados por estado conocido.
     */
    public function getExpedienteCountsByState(): array
    {
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
    }

    /**
     * Calcula el tiempo promedio que tardan en validarse las sesiones.
     */
    public function getAverageValidationTime(): array
    {
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

        if ($expediente->ultima_bitacora) {
            $dates->push(Carbon::parse($expediente->ultima_bitacora));
        }

        if ($expediente->ultima_sesion) {
            $dates->push(Carbon::parse($expediente->ultima_sesion));
        }

        $dates = $dates
            ->filter(fn ($date) => $date instanceof Carbon)
            ->sortByDesc(fn (Carbon $date) => $date->getTimestamp())
            ->values();

        return $dates->first();
    }
}

