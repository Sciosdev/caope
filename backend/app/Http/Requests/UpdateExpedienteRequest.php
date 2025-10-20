<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expediente = $this->route('expediente');

        return $expediente instanceof Expediente
            ? $this->user()?->can('update', $expediente) ?? false
            : false;
    }

    public function rules(): array
    {
        $expediente = $this->route('expediente');

        return [
            'no_control' => [
                'required',
                'string',
                'max:30',
                Rule::unique('expedientes', 'no_control')->ignore($expediente),
            ],
            'paciente' => ['required', 'string', 'min:2', 'max:140'],
            'apertura' => ['required', 'date', 'before_or_equal:today'],
            'carrera' => [
                'required',
                'string',
                'max:100',
                Rule::exists('catalogo_carreras', 'nombre')->where('activo', true),
            ],
            'turno' => [
                'required',
                'string',
                'max:20',
                Rule::exists('catalogo_turnos', 'nombre')->where('activo', true),
            ],
            'tutor_id' => ['nullable', 'integer', 'exists:users,id'],
            'coordinador_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
