<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('expedientes')) {
            return;
        }

        if (Schema::hasColumn('expedientes', 'carrera')) {
            $indexName = 'expedientes_carrera_index';

            if ($this->indexExists('expedientes', $indexName)) {
                Schema::table('expedientes', function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }

            Schema::table('expedientes', function (Blueprint $table) {
                $table->dropColumn('carrera');
            });
        }

        if (Schema::hasColumn('expedientes', 'turno')) {
            $indexName = 'expedientes_turno_index';

            if ($this->indexExists('expedientes', $indexName)) {
                Schema::table('expedientes', function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }

            Schema::table('expedientes', function (Blueprint $table) {
                $table->dropColumn('turno');
            });
        }

        if (!Schema::hasColumn('expedientes', 'carrera_id')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->foreignId('carrera_id')->nullable()->after('apertura')->constrained('catalogo_carreras')->restrictOnDelete();
            });
        }

        if (!Schema::hasColumn('expedientes', 'turno_id')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->foreignId('turno_id')->nullable()->after('carrera_id')->constrained('catalogo_turnos')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('expedientes')) {
            return;
        }

        if (Schema::hasColumn('expedientes', 'turno_id')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('turno_id');
            });
        }

        if (Schema::hasColumn('expedientes', 'carrera_id')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('carrera_id');
            });
        }

        if (!Schema::hasColumn('expedientes', 'carrera')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->string('carrera', 60)->nullable()->index()->after('apertura');
            });
        }

        if (!Schema::hasColumn('expedientes', 'turno')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->string('turno', 40)->nullable()->index()->after('carrera');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        $tableName = $connection->getTablePrefix() . $table;

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('" . $tableName . "')");

            foreach ($indexes as $indexRow) {
                $name = is_object($indexRow) ? ($indexRow->name ?? null) : ($indexRow['name'] ?? null);

                if ($name === $index) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'mysql') {
            $indexes = $connection->select('SHOW INDEX FROM `' . $tableName . '` WHERE Key_name = ?', [$index]);

            return !empty($indexes);
        }

        if ($driver === 'pgsql') {
            $indexes = $connection->select(
                'SELECT 1 FROM pg_indexes WHERE schemaname = ANY (current_schemas(false)) AND tablename = ? AND indexname = ? LIMIT 1',
                [$tableName, $index]
            );

            return !empty($indexes);
        }

        if ($driver === 'sqlsrv') {
            $indexes = $connection->select(
                'SELECT 1 FROM sys.indexes i INNER JOIN sys.tables t ON i.object_id = t.object_id WHERE t.name = ? AND i.name = ?',
                [$tableName, $index]
            );

            return !empty($indexes);
        }

        if (class_exists(\Doctrine\DBAL\Schema\AbstractSchemaManager::class)) {
            try {
                $schemaManager = $connection->getDoctrineSchemaManager();

                return array_key_exists($index, $schemaManager->listTableIndexes($table));
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }
};
