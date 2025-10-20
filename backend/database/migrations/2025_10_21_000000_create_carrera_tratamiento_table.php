<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('carrera_tratamiento')) {
            Schema::create('carrera_tratamiento', function (Blueprint $table) {
                $table->id();
                $table->foreignId('catalogo_carrera_id')->constrained('catalogo_carreras')->cascadeOnDelete();
                $table->foreignId('catalogo_tratamiento_id')->constrained('catalogo_tratamientos')->cascadeOnDelete();
                $table->boolean('obligatorio')->default(false);
                $table->timestamps();

                $table->unique(['catalogo_carrera_id', 'catalogo_tratamiento_id'], 'carrera_tratamiento_unico');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('carrera_tratamiento');
    }
};
