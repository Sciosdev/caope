<?php

namespace App\Http\Requests;

use App\Services\ConsultorioReservationGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConsultorioReservaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['cubiculo_numero' => $this->input('consultorio_numero')]);
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

        return $user->isApprovedPaps();
    }

    public function rules(): array
    {
        $scheduleOnly = $this->isExpedienteScheduleContext();

        return [
            'edit_context' => [
                'nullable',
                Rule::in(['expediente']),
                Rule::prohibitedIf(fn (): bool => ! $this->user()?->hasRole('admin')),
            ],
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'consultorio_numero' => ['required', 'integer', Rule::exists('catalogo_consultorios', 'numero')->where('activo', true)],
            'cubiculo_numero' => ['required', 'integer', 'same:consultorio_numero'],
            'estrategia' => $scheduleOnly
                ? ['prohibited']
                : ['required', 'string', 'max:255', Rule::exists('catalogo_estrategias', 'nombre')->where('activo', true)],
            'expediente_id' => $scheduleOnly
                ? ['prohibited']
                : ['sometimes', 'nullable', 'integer', 'exists:expedientes,id'],
            'usuario_atendido_id' => $scheduleOnly ? ['prohibited'] : ['nullable', 'integer', 'exists:users,id'],
            'estratega_id' => $scheduleOnly ? ['prohibited'] : ['nullable', 'integer', 'exists:users,id'],
            'supervisor_id' => $scheduleOnly ? ['prohibited'] : ['nullable', 'integer', 'exists:users,id'],
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

            $reserva = $this->route('reserva');
            $fecha = $this->input('fecha');
            $horaInicio = $this->input('hora_inicio');
            $horaFin = $this->input('hora_fin');
            $consultorio = (int) $this->input('consultorio_numero');

            if (! $reserva) {
                return;
            }

            if ((int) date('w', strtotime((string) $fecha)) === 0) {
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
                [$fecha],
                $horaInicio,
                $horaFin,
                (int) $reserva->id
            );
        });
    }

    private function isExpedienteScheduleContext(): bool
    {
        return $this->input('edit_context') === 'expediente'
            && ($this->user()?->hasRole('admin') ?? false);
    }
}
