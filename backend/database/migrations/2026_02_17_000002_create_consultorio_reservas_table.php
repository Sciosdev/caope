<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultorio_reservas', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha')->index();
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->unsignedTinyInteger('consultorio_numero');
            $table->string('estrategia', 255);
            $table->foreignId('usuario_atendido_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('estratega_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['consultorio_numero', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultorio_reservas');
    }
};
