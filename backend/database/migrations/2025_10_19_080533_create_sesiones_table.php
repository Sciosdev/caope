<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sesiones')) {
            return;
        }

        Schema::create('sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $table->date('fecha')->index();
            $table->string('tipo', 60);
            $table->string('referencia_externa', 120)->nullable();
            $table->longText('nota');
            $table->foreignId('realizada_por')->constrained('users')->restrictOnDelete();
            $table->enum('status_revision', ['pendiente', 'observada', 'validada'])->default('pendiente');
            $table->foreignId('validada_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones');
    }
};
