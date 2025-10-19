<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expedientes', function (Blueprint $table) {
            $table->id();
            $table->string('no')->unique();
            $table->string('paciente');
            $table->enum('estado', ['abierto', 'revision', 'cerrado'])->default('abierto');
            $table->date('apertura')->nullable();
            $table->string('carrera')->nullable();
            $table->string('turno')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expedientes');
    }
};
