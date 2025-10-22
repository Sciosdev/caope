<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expedientes') && ! $this->indexExists('expedientes', 'expedientes_apertura_index')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->index('apertura', 'expedientes_apertura_index');
            });
        }

        if (Schema::hasTable('sesiones') && ! $this->indexExists('sesiones', 'sesiones_status_validada_index')) {
            Schema::table('sesiones', function (Blueprint $table) {
                $table->index(['status_revision', 'validada_por'], 'sesiones_status_validada_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expedientes') && $this->indexExists('expedientes', 'expedientes_apertura_index')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->dropIndex('expedientes_apertura_index');
            });
        }

        if (Schema::hasTable('sesiones') && $this->indexExists('sesiones', 'sesiones_status_validada_index')) {
            Schema::table('sesiones', function (Blueprint $table) {
                $table->dropIndex('sesiones_status_validada_index');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $tableWithPrefix = $connection->getTablePrefix() . $table;
        $driver = $connection->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $result = $connection->select("SHOW INDEX FROM `{$tableWithPrefix}` WHERE Key_name = ?", [$indexName]);

            return count($result) > 0;
        }

        if ($driver === 'sqlite') {
            $result = $connection->select("PRAGMA index_list('{$tableWithPrefix}')");

            foreach ($result as $row) {
                $rowArray = (array) $row;

                if (($rowArray['name'] ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }
};
