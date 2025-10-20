<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expedientes')) {
            return;
        }

        Schema::create('expedientes', function (Blueprint $table) {
            $table->id();
            $table->string('no_control', 30)->unique();
            $table->string('paciente', 150);
            $table->string('estado', 20)->default('abierto')->checkIn(['abierto', 'revision', 'cerrado'])->index();
            $table->date('apertura');
            $table->string('carrera', 100)->index();
            $table->string('turno', 20)->index();
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('tutor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('coordinador_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['estado', 'apertura']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expedientes');
    }
};
