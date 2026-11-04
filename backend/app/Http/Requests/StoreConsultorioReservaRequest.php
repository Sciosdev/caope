<?php

namespace App\Http\Requests;

use App\Services\ConsultorioReservationGuard;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StoreConsultorioReservaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // One physical space. Ignore the obsolete independent sub-room input.
        $this->merge(['cubiculo_numero' => $this->input('consultorio_numero')]);

        $mode = $this->input('modo_repeticion', 'unica');

        if ($mode !== 'semanal') {
            return;
        }

        $fechaBase = $this->input('fecha');
        $inicio = $this->input('fecha_inicio_repeticion');
        $fin = $this->input('fecha_fin_repeticion');

        if (! $inicio && $fechaBase) {
            $inicio = $fechaBase;
        }

        if (! $fin && is_string($inicio) && Validator::make(['fecha' => $inicio], ['fecha' => 'date'])->passes()) {
            $fin = Carbon::parse($inicio)->addMonth()->toDateString();
        }

        $this->merge([
            'fecha_inicio_repeticion' => $inicio,
            'fecha_fin_repeticion' => $fin,
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if (! $user->isApprovedPaps()) {
            return false;
        }

        return $this->boolean('origen_expediente');
    }

    public function rules(): array
    {
        return [
            'modo_repeticion' => ['nullable', 'in:unica,semanal'],
            'fecha' => ['required_if:modo_repeticion,unica', 'nullable', 'date'],
            'fecha_inicio_repeticion' => ['required_if:modo_repeticion,semanal', 'nullable', 'date'],
            'fecha_fin_repeticion' => ['required_if:modo_repeticion,semanal', 'nullable', 'date', 'after_or_equal:fecha_inicio_repeticion'],
            'dias_semana' => ['nullable', 'array', 'min:1'],
            'dias_semana.*' => ['nullable', 'integer', 'between:1,6'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'consultorio_numero' => ['required', 'integer', Rule::exists('catalogo_consultorios', 'numero')->where('activo', true)],
            'cubiculo_numero' => ['required', 'integer', 'same:consultorio_numero'],
            'estrategia' => ['required', 'string', 'max:255', Rule::exists('catalogo_estrategias', 'nombre')->where('activo', true)],
            'expediente_id' => ['sometimes', 'nullable', 'integer', 'exists:expedientes,id'],
            'usuario_atendido_id' => ['nullable', 'integer', 'exists:users,id'],
            'estratega_id' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'origen_expediente' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['consultorio_numero' => 'cubículo', 'cubiculo_numero' => 'cubículo'];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $fechas = $this->reservationDates();
            $horaInicio = $this->input('hora_inicio');
            $horaFin = $this->input('hora_fin');
            $consultorio = (int) $this->input('consultorio_numero');

            if ($fechas->isEmpty()) {
                $validator->errors()->add('fecha', 'Selecciona al menos una fecha para la reserva.');

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

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            app(ConsultorioReservationGuard::class)->assertAvailable(
                $consultorio,
                $fechas->all(),
                $horaInicio,
                $horaFin
            );
        });
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
        $dias = collect($this->input('dias_semana', []))
            ->filter(fn ($dia) => filled($dia))
            ->map(fn ($dia) => (int) $dia)
            ->unique();

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

                if ($fechas->count() > 180) {
                    break;
                }
            }

            $cursor->addDay();
        }

        return $fechas;
    }
}
