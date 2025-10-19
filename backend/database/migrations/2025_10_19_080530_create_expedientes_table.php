<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('expedientes', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();                 // CA-2025-0001
            $table->string('paciente');                          // Nombre paciente
            $table->enum('estado', ['abierto','revision','cerrado'])->default('abierto');
            $table->date('apertura')->index();
            $table->string('carrera')->nullable();
            $table->string('turno')->nullable();
            $table->boolean('alerta')->default(false);
            $table->timestamps();

            $table->index(['estado', 'carrera', 'turno']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('expedientes');
    }
};
