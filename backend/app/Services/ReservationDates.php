<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ReservationDates
{
    public const LIMIT = 180;

    /**
     * Expand a structurally validated schedule. No database or timezone conversions
     * are involved: weekday selection is based on the local calendar date.
     *
     * @param  array<string, mixed>  $schedule
     * @return Collection<int, string>
     */
    public static function forSchedule(array $schedule, string $errorPrefix = ''): Collection
    {
        if (($schedule['hora_inicio'] ?? '') < '07:00' || ($schedule['hora_fin'] ?? '') > '22:00') {
            throw ValidationException::withMessages([$errorPrefix.'hora_inicio' => 'El horario permitido es de 07:00 a 22:00.']);
        }

        if (($schedule['modo_repeticion'] ?? 'unica') !== 'semanal') {
            $date = $schedule['fecha'] ?? null;
            if (! $date) {
                throw ValidationException::withMessages([$errorPrefix.'fecha' => 'Selecciona una fecha para la reserva.']);
            }
            if (CarbonImmutable::parse($date)->isSunday()) {
                throw ValidationException::withMessages([$errorPrefix.'fecha' => 'Solo se permiten reservas de lunes a sábado.']);
            }

            return collect([CarbonImmutable::parse($date)->toDateString()]);
        }

        $first = CarbonImmutable::parse($schedule['fecha_inicio_repeticion']);
        $last = CarbonImmutable::parse($schedule['fecha_fin_repeticion']);
        $weekdays = collect($schedule['dias_semana'] ?? [])->filter(fn ($day) => filled($day))->map(fn ($day) => (int) $day)->unique();
        if ($weekdays->isEmpty()) {
            // Legacy clients submitted just a base date; they repeat on its weekday.
            $weekdays = collect([$first->dayOfWeekIso]);
        }
        if ($weekdays->contains(7)) {
            throw ValidationException::withMessages([$errorPrefix.'dias_semana' => 'Solo se permiten reservas de lunes a sábado.']);
        }

        $dates = collect();
        for ($date = $first; $date->lte($last); $date = $date->addDay()) {
            if ($weekdays->contains($date->dayOfWeekIso)) {
                $dates->push($date->toDateString());
                if ($dates->count() > self::LIMIT) {
                    throw ValidationException::withMessages([$errorPrefix.'fecha_fin_repeticion' => 'Solo se pueden registrar hasta '.self::LIMIT.' fechas por operación.']);
                }
            }
        }
        if ($dates->isEmpty()) {
            throw ValidationException::withMessages([$errorPrefix.'dias_semana' => 'El periodo no contiene ninguno de los días seleccionados.']);
        }

        return $dates;
    }
}
