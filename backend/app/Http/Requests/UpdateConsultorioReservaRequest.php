<?php

namespace App\Http\Requests;

use App\Models\ConsultorioReserva;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConsultorioReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'coordinador']) ?? false;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'consultorio_numero' => ['required', 'integer', 'between:1,14'],
            'estrategia' => ['required', 'string', 'max:255'],
            'usuario_atendido_id' => ['nullable', 'integer', 'exists:users,id'],
            'estratega_id' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $reserva = $this->route('reserva');
            $fecha = $this->input('fecha');
            $horaInicio = $this->input('hora_inicio');
            $horaFin = $this->input('hora_fin');
            $consultorio = (int) $this->input('consultorio_numero');

            if (! $fecha || ! $horaInicio || ! $horaFin || ! $reserva) {
                return;
            }

            if ((int) date('w', strtotime((string) $fecha)) === 0) {
                $validator->errors()->add('fecha', 'Solo se permiten reservas de lunes a sábado.');
            }

            if ($horaInicio < '07:00' || $horaFin > '22:00') {
                $validator->errors()->add('hora_inicio', 'El horario permitido es de 07:00 a 22:00.');
            }

            $overlap = ConsultorioReserva::query()
                ->whereKeyNot($reserva->id)
                ->whereDate('fecha', $fecha)
                ->where('consultorio_numero', $consultorio)
                ->where('hora_inicio', '<', $horaFin)
                ->where('hora_fin', '>', $horaInicio)
                ->exists();

            if ($overlap) {
                $validator->errors()->add('hora_inicio', 'Ese consultorio ya está reservado en el bloque seleccionado.');
            }
        });
    }
}
