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
            $table->string('no_control', 20)->unique();
            $table->string('paciente', 140);
            $table->enum('estado', ['abierto', 'revision', 'cerrado'])->default('abierto')->index();
            $table->date('apertura')->nullable()->index();
            $table->string('carrera', 60)->nullable()->index();
            $table->string('turno', 40)->nullable()->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('tutor_id')->nullable()->constrained('users')->restrictOnDelete()->index();
            $table->foreignId('coordinador_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expedientes');
    }
};
