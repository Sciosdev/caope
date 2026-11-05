<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'participante_estrategia';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('estrategia_id');
                $table->timestamps();
            });
        }

        // Cada paso puede retomarse si una instalación anterior se interrumpió.
        foreach (['user_id', 'estrategia_id'] as $column) {
            if (! Schema::hasColumn(self::TABLE, $column)) {
                Schema::table(self::TABLE, function (Blueprint $table) use ($column): void {
                    $table->unsignedBigInteger($column);
                });
            }
        }

        foreach (['created_at', 'updated_at'] as $column) {
            if (! Schema::hasColumn(self::TABLE, $column)) {
                Schema::table(self::TABLE, function (Blueprint $table) use ($column): void {
                    $table->timestamp($column)->nullable();
                });
            }
        }

        if (! Schema::hasIndex(self::TABLE, ['user_id', 'estrategia_id'], 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique(['user_id', 'estrategia_id']);
            });
        }

        foreach (['user_id' => 'users', 'estrategia_id' => 'catalogo_estrategias'] as $column => $target) {
            if (! Schema::hasIndex(self::TABLE, [$column])) {
                Schema::table(self::TABLE, function (Blueprint $table) use ($column): void {
                    $table->index($column);
                });
            }

            $hasForeignKey = collect(Schema::getForeignKeys(self::TABLE))
                ->contains(fn (array $key): bool => $key['columns'] === [$column]);

            if (! $hasForeignKey) {
                Schema::table(self::TABLE, function (Blueprint $table) use ($column, $target): void {
                    $table->foreign($column)->references('id')->on($target)->cascadeOnDelete();
                });
            }
        }

        // No se infieren membresías de expedientes, reservas, roles ni coordinación.
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
