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
        $shouldAddFamilyHistory = ! $this->columnExists('expedientes', 'antecedentes_familiares');
        $shouldAddObservaciones = ! $this->columnExists('expedientes', 'antecedentes_observaciones');

        if ($shouldAddFamilyHistory || $shouldAddObservaciones) {
            Schema::table('expedientes', function (Blueprint $table) use ($shouldAddFamilyHistory, $shouldAddObservaciones) {
                if ($shouldAddFamilyHistory) {
                    $table->json('antecedentes_familiares')
                        ->after('coordinador_id')
                        ->nullable();
                }

                if ($shouldAddObservaciones) {
                    $table->text('antecedentes_observaciones')
                        ->after('antecedentes_familiares')
                        ->nullable();
                }
            });
        }

        DB::table('expedientes')
            ->whereNull('antecedentes_familiares')
            ->update([
                'antecedentes_familiares' => json_encode(
                    Expediente::defaultFamilyHistory(),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
                'antecedentes_observaciones' => null,
            ]);

        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE `expedientes` MODIFY `antecedentes_familiares` JSON NOT NULL');
        }
    }

    public function down(): void
    {
        $shouldDropObservaciones = $this->columnExists('expedientes', 'antecedentes_observaciones');
        $shouldDropFamilyHistory = $this->columnExists('expedientes', 'antecedentes_familiares');

        if ($shouldDropObservaciones || $shouldDropFamilyHistory) {
            Schema::table('expedientes', function (Blueprint $table) use ($shouldDropObservaciones, $shouldDropFamilyHistory) {
                if ($shouldDropObservaciones) {
                    $table->dropColumn('antecedentes_observaciones');
                }

                if ($shouldDropFamilyHistory) {
                    $table->dropColumn('antecedentes_familiares');
                }
            });
        }
    }

    protected function columnExists(string $table, string $column): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $results = DB::select("PRAGMA table_info('{$table}')");

            foreach ($results as $result) {
                if ($result->name === $column) {
                    return true;
                }
            }

            return false;
        }

        return Schema::hasColumn($table, $column);
    }
};
