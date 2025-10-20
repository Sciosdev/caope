<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ExpedienteFormRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpedienteRequest extends FormRequest
{
    use ExpedienteFormRules;

    public function authorize(): bool
    {
        $expediente = $this->resolveExpedienteFromRoute();

        if (! $expediente) {
            return false;
        }

        return $this->canUser('update', $expediente);
    }

    public function rules(): array
    {
        $expediente = $this->resolveExpedienteFromRoute();

        return $this->expedienteRules($expediente);
    }
}
