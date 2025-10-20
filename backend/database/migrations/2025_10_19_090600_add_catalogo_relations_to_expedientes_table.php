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

        if (Schema::hasColumn('expedientes', 'created_by') && !Schema::hasColumn('expedientes', 'creado_por')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->renameColumn('created_by', 'creado_por');
            });
        }

        if (!Schema::hasColumn('expedientes', 'carrera')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->string('carrera', 100)->index()->after('apertura');
            });
        }

        if (Schema::hasColumn('expedientes', 'carrera_id')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('carrera_id');
            });
        }

        if (!Schema::hasColumn('expedientes', 'turno')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->string('turno', 20)->index()->after('carrera');
            });
        }

        if (Schema::hasColumn('expedientes', 'turno_id')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('turno_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('expedientes')) {
            return;
        }

        if (!Schema::hasColumn('expedientes', 'carrera_id')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->foreignId('carrera_id')->nullable()->after('apertura')->constrained('catalogo_carreras')->restrictOnDelete();
            });
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

        if (!Schema::hasColumn('expedientes', 'turno_id')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->foreignId('turno_id')->nullable()->after('carrera_id')->constrained('catalogo_turnos')->restrictOnDelete();
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

        if (Schema::hasColumn('expedientes', 'creado_por') && !Schema::hasColumn('expedientes', 'created_by')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->renameColumn('creado_por', 'created_by');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('" . $table . "')");

            foreach ($indexes as $indexRow) {
                $name = is_object($indexRow) ? ($indexRow->name ?? null) : ($indexRow['name'] ?? null);

                if ($name === $index) {
                    return true;
                }
            }

            return false;
        }

        try {
            $schemaManager = $connection->getDoctrineSchemaManager();

            return array_key_exists($index, $schemaManager->listTableIndexes($table));
        } catch (\Throwable $e) {
            return false;
        }
    }
};
