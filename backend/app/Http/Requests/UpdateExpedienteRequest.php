<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ExpedienteFormRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpedienteRequest extends FormRequest
{
    use ExpedienteFormRules {
        prepareForValidation as private prepareExpedienteForValidation;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareExpedienteForValidation();

        $expediente = $this->resolveExpedienteFromRoute();

        if (! $expediente) {
            return;
        }

        $requiredFieldFallbacks = [
            'no_control' => $expediente->no_control,
            'paciente' => $expediente->paciente,
            'apertura' => optional($expediente->apertura)->toDateString(),
            'carrera' => $expediente->carrera,
            'turno' => $expediente->turno,
        ];

        $missingRequiredFields = collect($requiredFieldFallbacks)
            ->filter(fn (mixed $value, string $field) => ! $this->exists($field) && $value !== null)
            ->all();

        if ($missingRequiredFields !== []) {
            $this->merge($missingRequiredFields);
        }
    }

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

    public function messages(): array
    {
        return $this->expedienteMessages();
    }
}
