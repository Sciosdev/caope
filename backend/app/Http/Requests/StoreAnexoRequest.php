<?php

namespace App\Http\Requests;

use App\Models\Anexo;
use App\Models\Expediente;
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
        $mimes = (string) config('uploads.anexos.mimes', 'pdf,jpg,jpeg,png,doc,docx');
        $max = (int) config('uploads.anexos.max', 10240);

        return [
            'archivo' => [
                'required',
                'file',
                'mimes:'.$mimes,
                'max:'.$max,
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'archivo' => 'archivo de anexo',
        ];
    }
}
