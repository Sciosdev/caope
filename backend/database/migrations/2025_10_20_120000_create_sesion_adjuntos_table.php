<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sesion_adjuntos')) {
            return;
        }

        Schema::create('sesion_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('sesiones')->cascadeOnDelete();
            $table->string('nombre_original');
            $table->string('ruta');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('tamano');
            $table->foreignId('subido_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesion_adjuntos');
    }
};
