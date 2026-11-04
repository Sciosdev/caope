<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('consultorio_reservas', 'expediente_id')) {
            Schema::table('consultorio_reservas', function (Blueprint $table): void {
                $table->unsignedBigInteger('expediente_id')->nullable();
            });
        }

        if (! Schema::hasIndex('consultorio_reservas', ['expediente_id'])) {
            Schema::table('consultorio_reservas', function (Blueprint $table): void {
                $table->index('expediente_id');
            });
        }

        $hasForeignKey = collect(Schema::getForeignKeys('consultorio_reservas'))
            ->contains(fn (array $key): bool => $key['columns'] === ['expediente_id']);

        if (! $hasForeignKey) {
            Schema::table('consultorio_reservas', function (Blueprint $table): void {
                $table->foreign('expediente_id')->references('id')->on('expedientes')->nullOnDelete();
            });
        }

        // No se deduce el expediente de reservas históricas por persona, nombre
        // ni fecha: una vinculación no comprobada debe permanecer nula.
    }

    public function down(): void
    {
        if (! Schema::hasColumn('consultorio_reservas', 'expediente_id')) {
            return;
        }

        $foreignKeys = collect(Schema::getForeignKeys('consultorio_reservas'))
            ->filter(fn (array $key): bool => $key['columns'] === ['expediente_id']);

        foreach ($foreignKeys as $foreignKey) {
            Schema::table('consultorio_reservas', function (Blueprint $table) use ($foreignKey): void {
                $table->dropForeign($foreignKey['name'] ?? $foreignKey['columns']);
            });
        }

        $indexes = collect(Schema::getIndexes('consultorio_reservas'))
            ->filter(fn (array $index): bool => $index['columns'] === ['expediente_id'] && ! $index['primary']);

        foreach ($indexes as $index) {
            Schema::table('consultorio_reservas', function (Blueprint $table) use ($index): void {
                $table->dropIndex($index['name']);
            });
        }

        Schema::table('consultorio_reservas', function (Blueprint $table): void {
            $table->dropColumn('expediente_id');
        });
    }
};
