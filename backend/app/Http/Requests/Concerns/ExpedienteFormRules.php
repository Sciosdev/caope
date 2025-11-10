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

        if ($this->has('antecedentes_personales_patologicos')) {
            $this->merge([
                'antecedentes_personales_patologicos' => $this->sanitizePersonalPathologicalHistory(
                    $this->input('antecedentes_personales_patologicos')
                ),
            ]);
        }

        if ($this->has('aparatos_sistemas')) {
            $this->merge([
                'aparatos_sistemas' => $this->sanitizeSystemsReview($this->input('aparatos_sistemas')),
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
            'antecedentes_personales_observaciones',
            'antecedente_padecimiento_actual',
            'plan_accion',
            'diagnostico',
            'dsm_tr',
            'observaciones_relevantes',
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
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if ($value === null) {
                $sanitized[$field] = null;
                continue;
            }

            if (is_int($value)) {
                $sanitized[$field] = $value > 0 ? $value : null;
                continue;
            }

            if (is_string($value)) {
                $trimmed = trim($value);

                if ($trimmed === '' || $trimmed === '0') {
                    $sanitized[$field] = null;
                    continue;
                }

                if (is_numeric($trimmed)) {
                    $intValue = (int) $trimmed;
                    $sanitized[$field] = $intValue > 0 ? $intValue : null;
                    continue;
                }
            }

            $sanitized[$field] = $value;
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
            'tutor_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'exists:users,id'],
            'coordinador_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'exists:users,id'],
            'antecedentes_familiares' => ['sometimes', 'array'],
            'antecedentes_observaciones' => ['sometimes', 'nullable', 'string', 'max:500'],
            'antecedentes_personales_patologicos' => ['sometimes', 'array'],
            'antecedentes_personales_observaciones' => ['sometimes', 'nullable', 'string', 'max:500'],
            'antecedente_padecimiento_actual' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'plan_accion' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'diagnostico' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'dsm_tr' => ['sometimes', 'nullable', 'string', 'max:255'],
            'observaciones_relevantes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'aparatos_sistemas' => ['sometimes', 'array'],
            ...$this->familyHistoryMemberRules(),
            ...$this->personalPathologicalRules(),
            ...$this->systemsReviewRules(),
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
                    "antecedentes_familiares.$condition.$member" => ['sometimes', 'boolean'],
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

        return collect($defaults)
            ->map(function (array $members, string $condition) use ($history) {
                $providedMembers = is_array($history[$condition] ?? null) ? $history[$condition] : [];

                return collect($members)
                    ->map(function (bool $default, string $member) use ($providedMembers) {
                        if (! array_key_exists($member, $providedMembers)) {
                            return $default;
                        }

                        return $this->normalizeBooleanField($providedMembers[$member], $default);
                    })
                    ->all();
            })
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sanitizePersonalPathologicalHistory(mixed $value): array
    {
        $history = is_array($value) ? $value : [];
        $defaults = Expediente::defaultPersonalPathologicalHistory();

        $normalized = [];

        foreach ($defaults as $condition => $fields) {
            $provided = is_array($history[$condition] ?? null) ? $history[$condition] : [];

            $normalized[$condition] = [
                'padece' => $this->normalizeBooleanField($provided['padece'] ?? $fields['padece'], $fields['padece']),
                'fecha' => $this->normalizeDateField($provided['fecha'] ?? null),
            ];
        }

        return array_merge($defaults, $normalized);
    }

    /**
     * @param  mixed  $value
     * @return array<string, ?string>
     */
    private function sanitizeSystemsReview(mixed $value): array
    {
        $systems = is_array($value) ? $value : [];
        $defaults = Expediente::defaultSystemsReview();

        $normalized = [];

        foreach ($defaults as $section => $defaultValue) {
            $raw = $systems[$section] ?? $defaultValue;

            if (is_string($raw)) {
                $trimmed = trim($raw);
                $normalized[$section] = $trimmed === '' ? null : $trimmed;
                continue;
            }

            if (is_scalar($raw)) {
                $normalized[$section] = trim((string) $raw) ?: null;
                continue;
            }

            $normalized[$section] = null;
        }

        return array_merge($defaults, $normalized);
    }

    private function normalizeBooleanField(mixed $value, bool $default = false): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return $default;
            }

            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($normalized !== null) {
                return $normalized;
            }

            $lower = strtolower($trimmed);

            if (in_array($lower, ['si', 'sí', 'yes', 'true', '1'], true)) {
                return true;
            }

            if (in_array($lower, ['no', 'false', '0'], true)) {
                return false;
            }
        }

        return $value;
    }

    private function normalizeDateField(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return null;
            }

            try {
                return (new \DateTimeImmutable($trimmed))->format('Y-m-d');
            } catch (\Exception) {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function personalPathologicalRules(): array
    {
        return collect(Expediente::PERSONAL_PATHOLOGICAL_CONDITIONS)
            ->keys()
            ->flatMap(function (string $condition) {
                return [
                    "antecedentes_personales_patologicos.$condition.padece" => ['sometimes', 'boolean'],
                    "antecedentes_personales_patologicos.$condition.fecha" => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
                ];
            })
            ->all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function systemsReviewRules(): array
    {
        return collect(Expediente::SYSTEMS_REVIEW_SECTIONS)
            ->keys()
            ->mapWithKeys(fn (string $section) => [
                "aparatos_sistemas.$section" => ['sometimes', 'nullable', 'string', 'max:1000'],
            ])
            ->all();
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
            'antecedentes_personales_patologicos',
            'antecedentes_personales_observaciones',
            'antecedente_padecimiento_actual',
            'plan_accion',
            'diagnostico',
            'dsm_tr',
            'observaciones_relevantes',
            'aparatos_sistemas',
        ]);
    }
}
