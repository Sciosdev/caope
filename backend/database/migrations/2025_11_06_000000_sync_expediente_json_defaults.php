<?php

use App\Models\Expediente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $familyDefaults = Expediente::defaultFamilyHistory();
        $personalDefaults = Expediente::defaultPersonalPathologicalHistory();
        $systemsDefaults = Expediente::defaultSystemsReview();

        Expediente::query()
            ->lazyById()
            ->each(function (Expediente $expediente) use ($familyDefaults, $personalDefaults, $systemsDefaults) {
                $updates = [];

                $normalizedFamily = $this->normalizeFamilyHistory($familyDefaults, $expediente->antecedentes_familiares ?? []);
                if ($normalizedFamily !== ($expediente->antecedentes_familiares ?? [])) {
                    $updates['antecedentes_familiares'] = $normalizedFamily;
                }

                $normalizedPersonal = $this->normalizePersonalHistory($personalDefaults, $expediente->antecedentes_personales_patologicos ?? []);
                if ($normalizedPersonal !== ($expediente->antecedentes_personales_patologicos ?? [])) {
                    $updates['antecedentes_personales_patologicos'] = $normalizedPersonal;
                }

                $normalizedSystems = $this->normalizeSystemsReview($systemsDefaults, $expediente->aparatos_sistemas ?? []);
                if ($normalizedSystems !== ($expediente->aparatos_sistemas ?? [])) {
                    $updates['aparatos_sistemas'] = $normalizedSystems;
                }

                if (! empty($updates)) {
                    $expediente->forceFill($updates)->save();
                }
            });

        $this->synchronizeColumnDefaults($familyDefaults, $personalDefaults, $systemsDefaults);
    }

    public function down(): void
    {
        // No se requiere revertir los datos normalizados.
    }

    /**
     * @param  array<string, array<string, bool>>  $defaults
     * @param  array<string, mixed>  $value
     * @return array<string, array<string, bool>>
     */
    private function normalizeFamilyHistory(array $defaults, array $value): array
    {
        $normalized = [];

        foreach ($defaults as $condition => $members) {
            $provided = isset($value[$condition]) && is_array($value[$condition]) ? $value[$condition] : [];

            $normalized[$condition] = [];

            foreach ($members as $member => $default) {
                $raw = $provided[$member] ?? $default;
                $normalized[$condition][$member] = $this->normalizeBoolean($raw, $default);
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, array{padece: bool, fecha: ?string}>  $defaults
     * @param  array<string, mixed>  $value
     * @return array<string, array{padece: bool, fecha: ?string}>
     */
    private function normalizePersonalHistory(array $defaults, array $value): array
    {
        $normalized = [];

        foreach ($defaults as $condition => $fields) {
            $provided = isset($value[$condition]) && is_array($value[$condition]) ? $value[$condition] : [];

            $normalized[$condition] = [
                'padece' => $this->normalizeBoolean($provided['padece'] ?? $fields['padece'], $fields['padece']),
                'fecha' => $this->normalizeDate($provided['fecha'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, ?string>  $defaults
     * @param  array<string, mixed>  $value
     * @return array<string, ?string>
     */
    private function normalizeSystemsReview(array $defaults, array $value): array
    {
        $normalized = [];

        foreach ($defaults as $section => $default) {
            $raw = $value[$section] ?? $default;

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

        return $normalized;
    }

    private function normalizeBoolean(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($normalized !== null) {
                return $normalized;
            }

            $lower = strtolower(trim($value));

            if (in_array($lower, ['si', 'sí', 'yes', 'true', '1'], true)) {
                return true;
            }

            if (in_array($lower, ['no', 'false', '0'], true)) {
                return false;
            }
        }

        return $default;
    }

    private function normalizeDate(mixed $value): ?string
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

    private function synchronizeColumnDefaults(array $familyDefaults, array $personalDefaults, array $systemsDefaults): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            return;
        }

        $familyJson = $connection->getPdo()->quote(json_encode($familyDefaults, JSON_UNESCAPED_UNICODE));
        $personalJson = $connection->getPdo()->quote(json_encode($personalDefaults, JSON_UNESCAPED_UNICODE));
        $systemsJson = $connection->getPdo()->quote(json_encode($systemsDefaults, JSON_UNESCAPED_UNICODE));

        DB::statement("ALTER TABLE `expedientes` MODIFY `antecedentes_familiares` JSON NOT NULL DEFAULT {$familyJson}");
        DB::statement("ALTER TABLE `expedientes` MODIFY `antecedentes_personales_patologicos` JSON NOT NULL DEFAULT {$personalJson}");
        DB::statement("ALTER TABLE `expedientes` MODIFY `aparatos_sistemas` JSON NULL DEFAULT {$systemsJson}");
    }
};
