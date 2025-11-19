<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdateSesionRequest extends StoreSesionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['adjuntos_eliminar'] = ['nullable', 'array'];
        $rules['adjuntos_eliminar.*'] = [
            Rule::exists('sesion_adjuntos', 'id')
                ->where('sesion_id', $this->route('sesion')?->id ?? 0),
        ];

        return $rules;
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
        ]);
    }
}
