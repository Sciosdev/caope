<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->restrictOnDelete();
            $table->date('fecha')->index();
            $table->string('tipo', 60);
            $table->longText('nota');
            $table->foreignId('realizada_por')->constrained('users')->restrictOnDelete();
            $table->enum('status_revision', ['pendiente', 'observada', 'validada'])->default('pendiente')->index();
            $table->foreignId('validada_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones');
    }
};
