<?php

namespace App\Http\Requests;

use App\Models\ConsultorioReserva;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsultorioReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'modo_repeticion' => ['nullable', 'in:unica,semanal'],
            'fecha' => ['required_if:modo_repeticion,unica', 'nullable', 'date', 'after_or_equal:today'],
            'fecha_inicio_repeticion' => ['required_if:modo_repeticion,semanal', 'nullable', 'date', 'after_or_equal:today'],
            'fecha_fin_repeticion' => ['required_if:modo_repeticion,semanal', 'nullable', 'date', 'after_or_equal:fecha_inicio_repeticion'],
            'dias_semana' => ['nullable', 'array', 'min:1'],
            'dias_semana.*' => ['integer', 'between:1,6'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'consultorio_numero' => ['required', 'integer', Rule::exists('catalogo_consultorios', 'numero')->where('activo', true)],
            'cubiculo_numero' => ['required', 'integer', Rule::exists('catalogo_cubiculos', 'numero')->where('activo', true)],
            'estrategia' => ['required', 'string', 'max:255', Rule::exists('catalogo_estrategias', 'nombre')->where('activo', true)],
            'usuario_atendido_id' => ['nullable', 'integer', 'exists:users,id'],
            'estratega_id' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $fechas = $this->reservationDates();
            $horaInicio = $this->input('hora_inicio');
            $horaFin = $this->input('hora_fin');
            $horaInicioComparable = $this->toDatabaseTime($horaInicio);
            $horaFinComparable = $this->toDatabaseTime($horaFin);
            $consultorio = (int) $this->input('consultorio_numero');
            $cubiculo = (int) $this->input('cubiculo_numero');

            if ($fechas->isEmpty() || ! $horaInicio || ! $horaFin || ! $horaInicioComparable || ! $horaFinComparable) {
                return;
            }

            if ($fechas->count() > 180) {
                $validator->errors()->add('fecha_fin_repeticion', 'Solo se pueden registrar hasta 180 fechas por operación.');
            }

            if ($fechas->contains(fn (string $fecha) => (int) date('w', strtotime($fecha)) === 0)) {
                $validator->errors()->add('fecha', 'Solo se permiten reservas de lunes a sábado.');
            }

            if ($horaInicio < '07:00' || $horaFin > '22:00') {
                $validator->errors()->add('hora_inicio', 'El horario permitido es de 07:00 a 22:00.');
            }

            $overlap = ConsultorioReserva::query()
                ->whereIn('fecha', $fechas->all())
                ->where('consultorio_numero', $consultorio)
                ->where('cubiculo_numero', $cubiculo)
                ->where('hora_inicio', '<', $horaFinComparable)
                ->where('hora_fin', '>', $horaInicioComparable)
                ->exists();

            if ($overlap) {
                $validator->errors()->add('hora_inicio', 'Ese consultorio ya está reservado en el bloque seleccionado.');
            }
        });
    }

    protected function toDatabaseTime(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return Carbon::createFromFormat('H:i', $time)?->format('H:i:s');
    }

    public function reservationDates(): Collection
    {
        $mode = $this->input('modo_repeticion', 'unica');

        if ($mode !== 'semanal') {
            $fecha = $this->input('fecha');

            return collect($fecha ? [$fecha] : []);
        }

        $inicio = $this->input('fecha_inicio_repeticion');
        $fin = $this->input('fecha_fin_repeticion');
        $dias = collect($this->input('dias_semana', []))->map(fn ($dia) => (int) $dia)->unique();

        if (! $inicio || ! $fin) {
            return collect();
        }

        if ($dias->isEmpty()) {
            $dias = collect([(int) Carbon::parse($inicio)->dayOfWeekIso]);
        }

        $cursor = Carbon::parse($inicio)->startOfDay();
        $end = Carbon::parse($fin)->startOfDay();
        $fechas = collect();

        while ($cursor->lte($end)) {
            if ($dias->contains($cursor->dayOfWeekIso)) {
                $fechas->push($cursor->toDateString());
            }

            $cursor->addDay();
        }

        return $fechas;
    }
}
