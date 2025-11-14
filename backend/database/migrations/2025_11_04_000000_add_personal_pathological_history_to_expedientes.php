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
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        $shouldAddHistory = ! $this->columnExists('expedientes', 'antecedentes_personales_patologicos');
        $shouldAddObservaciones = ! $this->columnExists('expedientes', 'antecedentes_personales_observaciones');

        if ($shouldAddHistory || $shouldAddObservaciones) {
            if ($driver === 'sqlite') {
                $tableWithPrefix = $connection->getTablePrefix() . 'expedientes';

                if ($shouldAddHistory) {
                    $connection->statement(
                        "ALTER TABLE \"{$tableWithPrefix}\" ADD COLUMN \"antecedentes_personales_patologicos\" TEXT"
                    );
                }

                if ($shouldAddObservaciones) {
                    $connection->statement(
                        "ALTER TABLE \"{$tableWithPrefix}\" ADD COLUMN \"antecedentes_personales_observaciones\" TEXT"
                    );
                }
            } else {
                Schema::table('expedientes', function (Blueprint $table) use ($shouldAddHistory, $shouldAddObservaciones) {
                    if ($shouldAddHistory) {
                        $table->json('antecedentes_personales_patologicos')
                            ->after('antecedentes_observaciones')
                            ->nullable();
                    }

                    if ($shouldAddObservaciones) {
                        $table->text('antecedentes_personales_observaciones')
                            ->after('antecedentes_personales_patologicos')
                            ->nullable();
                    }
                });
            }
        }

        DB::table('expedientes')->update([
            'antecedentes_personales_patologicos' => json_encode(Expediente::defaultPersonalPathologicalHistory(), JSON_UNESCAPED_UNICODE),
            'antecedentes_personales_observaciones' => null,
        ]);

        if ($driver === 'mysql' || $driver === 'mariadb') {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->json('antecedentes_personales_patologicos')
                    ->nullable(false)
                    ->change();
            });
        }
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        $shouldDropHistory = $this->columnExists('expedientes', 'antecedentes_personales_patologicos');
        $shouldDropObservaciones = $this->columnExists('expedientes', 'antecedentes_personales_observaciones');

        if (! $shouldDropHistory && ! $shouldDropObservaciones) {
            return;
        }

        if ($driver === 'sqlite') {
            $tableWithPrefix = $connection->getTablePrefix() . 'expedientes';

            if ($shouldDropHistory) {
                $connection->statement(
                    "ALTER TABLE \"{$tableWithPrefix}\" DROP COLUMN \"antecedentes_personales_patologicos\""
                );
            }

            if ($shouldDropObservaciones) {
                $connection->statement(
                    "ALTER TABLE \"{$tableWithPrefix}\" DROP COLUMN \"antecedentes_personales_observaciones\""
                );
            }

            return;
        }

        Schema::table('expedientes', function (Blueprint $table) use ($shouldDropHistory, $shouldDropObservaciones) {
            $columnsToDrop = [];

            if ($shouldDropHistory) {
                $columnsToDrop[] = 'antecedentes_personales_patologicos';
            }

            if ($shouldDropObservaciones) {
                $columnsToDrop[] = 'antecedentes_personales_observaciones';
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    protected function columnExists(string $table, string $column): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $tableWithPrefix = $connection->getTablePrefix() . $table;
            $results = DB::select("PRAGMA table_info('{$tableWithPrefix}')");

            foreach ($results as $result) {
                $resultArray = (array) $result;

                if (($resultArray['name'] ?? null) === $column) {
                    return true;
                }
            }

            return false;
        }

        return Schema::hasColumn($table, $column);
    }
};
