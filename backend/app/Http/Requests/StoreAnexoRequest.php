<?php

namespace App\Http\Requests;

use App\Models\Anexo;
use App\Models\Expediente;
use App\Models\Parametro;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnexoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expediente = $this->route('expediente');

        if (! $expediente instanceof Expediente) {
            return false;
        }

        return $this->user()?->can('create', [Anexo::class, $expediente]) ?? false;
    }

    public function rules(): array
    {
        $mimes = (string) Parametro::obtener('uploads.anexos.mimes', 'pdf,jpg,jpeg,png,doc,docx');
        $max = (int) Parametro::obtener('uploads.anexos.max', 10240);

        return [
            'archivo' => [
                'required',
                'file',
                'mimes:'.$mimes,
                'max:'.$max,
            ],
            'es_privado' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'archivo' => 'archivo de anexo',
            'es_privado' => 'marcador de privacidad',
        ];
    }
}
