<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('expedientes', 'cubiculo_id')) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->unsignedBigInteger('cubiculo_id')->nullable()->after('estrategia_id');
            });
        }

        if (! Schema::hasIndex('expedientes', ['cubiculo_id'])) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->index('cubiculo_id');
            });
        }

        $hasForeignKey = collect(Schema::getForeignKeys('expedientes'))
            ->contains(fn (array $key): bool => $key['columns'] === ['cubiculo_id']);

        if (! $hasForeignKey) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->foreign('cubiculo_id')
                    ->references('id')
                    ->on('catalogo_consultorios')
                    ->restrictOnDelete();
            });
        }

        // No se infiere la identidad desde resumen_clinico.cubiculo: ese valor
        // pertenece al catálogo histórico y una coincidencia numérica no prueba
        // que sea el mismo espacio del catálogo canónico.
    }

    public function down(): void
    {
        if (! Schema::hasColumn('expedientes', 'cubiculo_id')) {
            return;
        }

        $foreignKeys = collect(Schema::getForeignKeys('expedientes'))
            ->filter(fn (array $key): bool => $key['columns'] === ['cubiculo_id']);

        foreach ($foreignKeys as $foreignKey) {
            Schema::table('expedientes', function (Blueprint $table) use ($foreignKey): void {
                $table->dropForeign($foreignKey['name'] ?? $foreignKey['columns']);
            });
        }

        $indexes = collect(Schema::getIndexes('expedientes'))
            ->filter(fn (array $index): bool => $index['columns'] === ['cubiculo_id'] && ! $index['primary']);

        foreach ($indexes as $index) {
            Schema::table('expedientes', function (Blueprint $table) use ($index): void {
                $table->dropIndex($index['name']);
            });
        }

        Schema::table('expedientes', function (Blueprint $table): void {
            $table->dropColumn('cubiculo_id');
        });
    }
};
