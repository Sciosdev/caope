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
            'referencia_externa' => ['nullable', 'string', 'max:120'],
            'nota' => ['required', 'string'],
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
            'referencia_externa',
            'nota',
        ]);
    }
}
