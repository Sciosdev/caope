<?php

use App\Models\Expediente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('expedientes')
            ->select(['id', 'antecedentes_familiares'])
            ->orderBy('id')
            ->chunkById(100, function ($expedientes) {
                foreach ($expedientes as $expediente) {
                    $history = $this->decodeFamilyHistory($expediente->antecedentes_familiares);
                    $updated = $this->mapToNewStructure($history);

                    if ($updated !== $history) {
                        DB::table('expedientes')
                            ->where('id', $expediente->id)
                            ->update([
                                'antecedentes_familiares' => json_encode($updated, JSON_UNESCAPED_UNICODE),
                            ]);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('expedientes')
            ->select(['id', 'antecedentes_familiares'])
            ->orderBy('id')
            ->chunkById(100, function ($expedientes) {
                foreach ($expedientes as $expediente) {
                    $history = $this->decodeFamilyHistory($expediente->antecedentes_familiares);
                    $updated = $this->mapToLegacyStructure($history);

                    if ($updated !== $history) {
                        DB::table('expedientes')
                            ->where('id', $expediente->id)
                            ->update([
                                'antecedentes_familiares' => json_encode($updated, JSON_UNESCAPED_UNICODE),
                            ]);
                    }
                }
            });
    }

    /**
     * @param  mixed  $value
     * @return array<string, array<string, bool>>
     */
    private function decodeFamilyHistory(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
        } else {
            $decoded = $value;
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $history
     * @return array<string, array<string, bool>>
     */
    private function mapToNewStructure(array $history): array
    {
        $defaults = Expediente::defaultFamilyHistory();
        $mapped = [];

        foreach ($defaults as $condition => $members) {
            $existing = $history[$condition] ?? [];
            $legacyGrandparents = $this->normalizeBoolean($existing['abuelos'] ?? false);
            $legacyExtended = $this->normalizeBoolean($existing['tios'] ?? false) || $this->normalizeBoolean($existing['otros'] ?? false);

            $mappedMembers = [];

            foreach (array_keys($members) as $member) {
                if (array_key_exists($member, $existing)) {
                    $mappedMembers[$member] = $this->normalizeBoolean($existing[$member]);
                    continue;
                }

                $mappedMembers[$member] = match ($member) {
                    'abuela_materna', 'abuelo_materno', 'abuela_paterna', 'abuelo_paterno' => $legacyGrandparents,
                    'otros_maternos', 'otros_paternos' => $legacyExtended,
                    default => $this->normalizeBoolean($existing[$member] ?? false),
                };
            }

            $mapped[$condition] = array_merge($members, $mappedMembers);
        }

        return array_merge($defaults, $mapped);
    }

    /**
     * @param  array<string, array<string, mixed>>  $history
     * @return array<string, array<string, bool>>
     */
    private function mapToLegacyStructure(array $history): array
    {
        $legacyDefaults = [];
        $legacyMembers = ['madre', 'padre', 'hermanos', 'abuelos', 'tios', 'otros'];

        foreach (Expediente::HEREDITARY_HISTORY_CONDITIONS as $condition => $label) {
            $legacyDefaults[$condition] = array_fill_keys($legacyMembers, false);
            $existing = $history[$condition] ?? [];

            $legacyDefaults[$condition]['madre'] = $this->normalizeBoolean($existing['madre'] ?? false);
            $legacyDefaults[$condition]['padre'] = $this->normalizeBoolean($existing['padre'] ?? false);
            $legacyDefaults[$condition]['hermanos'] = $this->normalizeBoolean($existing['hermanos'] ?? false);

            $grandparents = [
                $this->normalizeBoolean($existing['abuela_materna'] ?? false),
                $this->normalizeBoolean($existing['abuelo_materno'] ?? false),
                $this->normalizeBoolean($existing['abuela_paterna'] ?? false),
                $this->normalizeBoolean($existing['abuelo_paterno'] ?? false),
            ];

            $extended = [
                $this->normalizeBoolean($existing['otros_maternos'] ?? false),
                $this->normalizeBoolean($existing['otros_paternos'] ?? false),
            ];

            $legacyDefaults[$condition]['abuelos'] = in_array(true, $grandparents, true);
            $legacyDefaults[$condition]['tios'] = in_array(true, $extended, true);
            $legacyDefaults[$condition]['otros'] = in_array(true, $extended, true);
        }

        return $legacyDefaults;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return filter_var($normalized, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $normalized === '1';
        }

        return false;
    }
};
