<?php

namespace App\Http\Requests;

use App\Services\ConsultorioReservationGuard;
use App\Services\ReservationDates;
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
        return ReservationDates::forSchedule($this->only(['fecha', 'modo_repeticion', 'fecha_inicio_repeticion', 'fecha_fin_repeticion', 'dias_semana', 'hora_inicio', 'hora_fin']));
    }
}
