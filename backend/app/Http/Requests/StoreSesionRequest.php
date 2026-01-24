<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class StoreSesionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'tipo' => ['required', 'string', 'max:60'],
            'hora_atencion' => ['nullable', 'date_format:H:i'],
            'referencia_externa' => ['nullable', 'string', 'max:120'],
            'estrategia' => ['nullable', 'string', 'max:1000'],
            'nota' => ['required', 'string'],
            'interconsulta' => ['nullable', 'string', 'max:120'],
            'especialidad_referida' => ['nullable', 'string', 'max:120'],
            'motivo_referencia' => ['nullable', 'string', 'max:1000'],
            'nombre_facilitador' => ['nullable', 'string', 'max:120'],
            'autorizacion_estratega' => ['nullable', 'string', 'max:120'],
            'clinica' => ['nullable', 'string', 'max:120'],
            'adjuntos' => ['nullable', 'array'],
            'adjuntos.*' => ['file', 'max:10240'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedSesionData(): array
    {
        $data = $this->validated();

        return Arr::only($data, [
            'fecha',
            'tipo',
            'hora_atencion',
            'referencia_externa',
            'estrategia',
            'nota',
            'interconsulta',
            'especialidad_referida',
            'motivo_referencia',
            'nombre_facilitador',
            'autorizacion_estratega',
            'clinica',
        ]);
    }
}
