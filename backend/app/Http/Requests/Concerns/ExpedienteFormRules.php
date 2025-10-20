<?php

namespace App\Http\Requests\Concerns;

use App\Models\Expediente;
use Illuminate\Validation\Rule;

trait ExpedienteFormRules
{
    protected function prepareForValidation(): void
    {
        $this->merge($this->sanitizedStringFields());
    }

    /**
     * @return array<string, mixed>
     */
    protected function sanitizedStringFields(): array
    {
        $sanitized = [];

        foreach (['no_control', 'paciente', 'estado', 'carrera', 'turno'] as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);

                if (is_string($value)) {
                    $value = trim($value);
                    $sanitized[$field] = $value === '' ? null : $value;
                }
            }
        }

        foreach (['tutor_id', 'coordinador_id'] as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);

                $sanitized[$field] = $value === '' || $value === null ? null : (int) $value;
            }
        }

        return $sanitized;
    }

    /**
     * @return array<string, mixed>
     */
    protected function expedienteRules(?Expediente $expediente = null): array
    {
        $uniqueNoControl = Rule::unique('expedientes', 'no_control');

        if ($expediente) {
            $uniqueNoControl = $uniqueNoControl->ignore($expediente);
        }

        return [
            'no_control' => ['required', 'string', 'max:30', $uniqueNoControl],
            'paciente' => ['required', 'string', 'min:2', 'max:140'],
            'estado' => ['sometimes', 'nullable', 'string', Rule::in(['abierto', 'revision', 'cerrado'])],
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
            'tutor_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'coordinador_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }

    protected function resolveExpedienteFromRoute(): ?Expediente
    {
        $expediente = $this->route('expediente');

        return $expediente instanceof Expediente ? $expediente : null;
    }

    protected function canUser(string $ability, mixed $arguments): bool
    {
        $user = $this->user();

        return $user ? $user->can($ability, $arguments) : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedExpedienteData(): array
    {
        return $this->safe()->only([
            'no_control',
            'paciente',
            'estado',
            'apertura',
            'carrera',
            'turno',
            'tutor_id',
            'coordinador_id',
        ])->toArray();
    }
}
