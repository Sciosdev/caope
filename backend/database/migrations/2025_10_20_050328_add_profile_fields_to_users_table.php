<?php

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = 'users';
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        if (!Schema::hasColumn($tableName, 'carrera')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('carrera', 100)->nullable()->after('email');
            });
        }

        if (!Schema::hasColumn($tableName, 'turno')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('turno', 20)->nullable()->after('carrera');
            });
        }

        if (! $this->indexExists($connection, $database, $tableName, 'users_carrera_index')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->index('carrera');
            });
        }

        if (! $this->indexExists($connection, $database, $tableName, 'users_turno_index')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->index('turno');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'users';
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        Schema::table($tableName, function (Blueprint $table) use ($connection, $database, $tableName) {
            if ($this->indexExists($connection, $database, $tableName, 'users_carrera_index')) {
                $table->dropIndex('users_carrera_index');
            }

            if ($this->indexExists($connection, $database, $tableName, 'users_turno_index')) {
                $table->dropIndex('users_turno_index');
            }

            $columnsToDrop = array_filter([
                Schema::hasColumn($tableName, 'carrera') ? 'carrera' : null,
                Schema::hasColumn($tableName, 'turno') ? 'turno' : null,
            ]);

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    private function indexExists(ConnectionInterface $connection, string $database, string $table, string $index): bool
    {
        $driver = $connection->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $result = $connection->selectOne(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$database, $table, $index]
            );

            return $result !== null;
        }

        if ($driver === 'sqlite') {
            $result = $connection->selectOne(
                "SELECT 1 FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ? LIMIT 1",
                [$table, $index]
            );

            return $result !== null;
        }

        if (method_exists($connection, 'getDoctrineSchemaManager')) {
            try {
                $schemaManager = $connection->getDoctrineSchemaManager();

                if ($schemaManager !== null) {
                    $indexes = $schemaManager->listTableIndexes($table);

                    return array_key_exists($index, $indexes);
                }
            } catch (Throwable $exception) {
                // Ignore Doctrine availability issues and fall back to false below.
            }
        }

        return false;
    }
};
