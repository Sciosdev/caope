<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureStrategyColumn('expedientes');

        if (! Schema::hasTable('coordinador_estrategia')) {
            Schema::create('coordinador_estrategia', function (Blueprint $table): void {
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('estrategia_id')->constrained('catalogo_estrategias')->cascadeOnDelete();
                $table->primary(['user_id', 'estrategia_id']);
                $table->timestamps();
            });
        }

        $this->ensureStrategyColumn('consultorio_reservas');

        // Las reservas ya identificaban la estrategia por su nombre de catálogo.
        // Conserva esa identidad al empezar a autorizar por una llave estable.
        DB::table('consultorio_reservas')->whereNull('estrategia_id')->update([
            'estrategia_id' => DB::raw('(SELECT catalogo_estrategias.id FROM catalogo_estrategias WHERE catalogo_estrategias.nombre = consultorio_reservas.estrategia)'),
        ]);

        // Las asignaciones se capturan explícitamente; no se infieren de nombres
        // ni del coordinador histórico de cada expediente.
    }

    public function down(): void
    {
        Schema::table('consultorio_reservas', function (Blueprint $table): void {
            $table->dropForeign(['estrategia_id']);
            $table->dropIndex(['estrategia_id']);
            $table->dropColumn('estrategia_id');
        });

        Schema::dropIfExists('coordinador_estrategia');

        Schema::table('expedientes', function (Blueprint $table): void {
            $table->dropForeign(['estrategia_id']);
            $table->dropIndex(['estrategia_id']);
            $table->dropColumn('estrategia_id');
        });
    }

    private function ensureStrategyColumn(string $tableName): void
    {
        if (! Schema::hasColumn($tableName, 'estrategia_id')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('estrategia_id')->nullable();
            });
        }

        if (! Schema::hasIndex($tableName, ['estrategia_id'])) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->index('estrategia_id');
            });
        }

        $hasForeignKey = collect(Schema::getForeignKeys($tableName))
            ->contains(fn (array $key): bool => $key['columns'] === ['estrategia_id']);

        if (! $hasForeignKey) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('estrategia_id')->references('id')->on('catalogo_estrategias')->restrictOnDelete();
            });
        }
    }
};
