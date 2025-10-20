<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ExpedienteFormRules;
use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpedienteRequest extends FormRequest
{
    use ExpedienteFormRules;

    public function authorize(): bool
    {
        return $this->canUser('create', Expediente::class);
    }

    public function rules(): array
    {
        return $this->expedienteRules();
    }
}
