<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogo_cubiculos')) {
            Schema::create('catalogo_cubiculos', function (Blueprint $table): void {
                $table->id();
                $table->string('nombre')->unique();
                $table->unsignedTinyInteger('numero')->unique();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('catalogo_cubiculos') && DB::table('catalogo_cubiculos')->count() === 0) {
            $now = now();

            $rows = collect(range(1, 14))->map(fn (int $numero) => [
                'nombre' => "Cubículo {$numero}",
                'numero' => $numero,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            DB::table('catalogo_cubiculos')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_cubiculos');
    }
};

