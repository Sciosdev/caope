<?php

namespace App\Http\Requests\Concerns;

use App\Models\Expediente;
use Illuminate\Validation\Rule;

trait ExpedienteFormRules
{
    protected function prepareForValidation(): void
    {
        $this->merge($this->sanitizedStringFields());

        if ($this->has('antecedentes_familiares')) {
            $this->merge([
                'antecedentes_familiares' => $this->sanitizeFamilyHistory($this->input('antecedentes_familiares')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function sanitizedStringFields(): array
    {
        $sanitized = [];

        foreach ([
            'no_control',
            'paciente',
            'estado',
            'carrera',
            'turno',
            'antecedentes_observaciones',
        ] as $field) {
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
            'antecedentes_familiares' => ['sometimes', 'array'],
            'antecedentes_observaciones' => ['sometimes', 'nullable', 'string', 'max:500'],
            ...$this->familyHistoryMemberRules(),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function familyHistoryMemberRules(): array
    {
        $members = collect(Expediente::FAMILY_HISTORY_MEMBERS)->keys();

        return collect(Expediente::HEREDITARY_HISTORY_CONDITIONS)
            ->keys()
            ->flatMap(function (string $condition) use ($members) {
                return $members->mapWithKeys(fn (string $member) => [
                    "antecedentes_familiares.$condition.$member" => ['required_with:antecedentes_familiares', 'boolean'],
                ]);
            })
            ->all();
    }

    /**
     * @param  mixed  $value
     * @return array<string, array<string, bool>>
     */
    private function sanitizeFamilyHistory(mixed $value): array
    {
        $history = is_array($value) ? $value : [];
        $defaults = Expediente::defaultFamilyHistory();

        $normalized = [];

        foreach ($defaults as $condition => $members) {
            $currentMembers = [];
            $providedMembers = is_array($history[$condition] ?? null) ? $history[$condition] : [];

            foreach ($members as $member => $default) {
                if (! array_key_exists($member, $providedMembers)) {
                    $currentMembers[$member] = $default;
                    continue;
                }

                $raw = $providedMembers[$member];

                if (is_bool($raw)) {
                    $currentMembers[$member] = $raw;
                    continue;
                }

                if (is_scalar($raw)) {
                    $boolValue = filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                    if ($boolValue !== null) {
                        $currentMembers[$member] = $boolValue;
                        continue;
                    }

                    $currentMembers[$member] = $raw;
                    continue;
                }

                $currentMembers[$member] = $raw;
            }

            $normalized[$condition] = array_merge($members, $currentMembers);
        }

        return array_merge($defaults, $normalized);
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
            'antecedentes_familiares',
            'antecedentes_observaciones',
        ]);
    }
}
