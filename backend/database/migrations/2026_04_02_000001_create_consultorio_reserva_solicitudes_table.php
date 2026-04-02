<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultorio_reserva_solicitudes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consultorio_reserva_id')->constrained('consultorio_reservas')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->enum('tipo', ['edicion', 'baja']);
            $table->json('payload')->nullable();
            $table->enum('status', ['pendiente', 'atendida'])->default('pendiente');
            $table->timestamps();

            $table->index(['status', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultorio_reserva_solicitudes');
    }
};
