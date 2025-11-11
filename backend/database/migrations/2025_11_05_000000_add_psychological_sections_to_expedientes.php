<?php

use App\Models\Expediente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            if (! Schema::hasColumn('expedientes', 'antecedente_padecimiento_actual')) {
                $table->text('antecedente_padecimiento_actual')
                    ->nullable()
                    ->after('antecedentes_personales_observaciones');
            }

            if (! Schema::hasColumn('expedientes', 'aparatos_sistemas')) {
                $table->json('aparatos_sistemas')
                    ->nullable()
                    ->after('antecedente_padecimiento_actual');
            }
        });

        $defaultSystems = json_encode(Expediente::defaultSystemsReview(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        DB::table('expedientes')
            ->whereNull('aparatos_sistemas')
            ->update(['aparatos_sistemas' => $defaultSystems]);

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $version = $this->getDatabaseVersion();
            $supportsJsonDefaults = $this->supportsJsonDefaults($driver, $version);

            if ($supportsJsonDefaults) {
                $quotedDefault = $connection->getPdo()->quote($defaultSystems);

                DB::statement("ALTER TABLE `expedientes` MODIFY `aparatos_sistemas` JSON NOT NULL DEFAULT {$quotedDefault}");
            } else {
                DB::statement('ALTER TABLE `expedientes` MODIFY `aparatos_sistemas` JSON NOT NULL');
            }
        }
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            if (Schema::hasColumn('expedientes', 'aparatos_sistemas')) {
                $table->dropColumn('aparatos_sistemas');
            }

            if (Schema::hasColumn('expedientes', 'antecedente_padecimiento_actual')) {
                $table->dropColumn('antecedente_padecimiento_actual');
            }
        });
    }

    private function getDatabaseVersion(): ?string
    {
        $result = DB::selectOne('select version() as version');

        return $this->normalizeVersionResult($result);
    }

    private function supportsJsonDefaults(string $driver, ?string $version): bool
    {
        if ($version === null) {
            return false;
        }

        $normalized = $this->normalizeVersionString($version);

        if ($normalized === null) {
            return false;
        }

        if (stripos($normalized, 'mariadb') !== false || $driver === 'mariadb') {
            return false;
        }

        $versionNumber = $this->extractVersionNumber($normalized);

        if ($versionNumber === null) {
            return false;
        }

        return version_compare($versionNumber, '8.0.13', '>=');
    }

    private function normalizeVersionResult(mixed $result): ?string
    {
        if ($result === null) {
            return null;
        }

        if (is_object($result)) {
            if (isset($result->version)) {
                return $this->normalizeVersionString((string) $result->version);
            }

            if (isset($result->{'VERSION()'})) {
                return $this->normalizeVersionString((string) $result->{'VERSION()'});
            }
        }

        if (is_array($result)) {
            if (isset($result['version'])) {
                return $this->normalizeVersionString((string) $result['version']);
            }

            if (isset($result['VERSION()'])) {
                return $this->normalizeVersionString((string) $result['VERSION()']);
            }
        }

        if (is_string($result)) {
            return $this->normalizeVersionString($result);
        }

        return null;
    }

    private function normalizeVersionString(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $trimmed = trim($version);

        if ($trimmed === '') {
            return null;
        }

        $collapsed = preg_replace('/\s+/', ' ', $trimmed);

        if (! is_string($collapsed)) {
            return null;
        }

        return $collapsed;
    }

    private function extractVersionNumber(string $version): ?string
    {
        if (preg_match('/(\d+\.\d+\.\d+)/', $version, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(\d+\.\d+)/', $version, $matches)) {
            return $matches[1];
        }

        return null;
    }
};
