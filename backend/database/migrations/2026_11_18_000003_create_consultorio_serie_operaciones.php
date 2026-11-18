<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consultorio_serie_operaciones')) {
            Schema::create('consultorio_serie_operaciones', function (Blueprint $table): void {
                $table->id();
                // Historical identifiers: an explicit cancellation removes the reservation,
                // but must not remove evidence of what was cancelled or who did so.
                $table->unsignedBigInteger('actor_id');
                $table->unsignedBigInteger('reserva_origen_id');
                $table->uuid('serie_uuid')->nullable();
                $table->string('accion', 15);
                $table->string('alcance', 15);
                $table->string('motivo', 300);
                $table->json('cambios');
                $table->dateTime('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consultorio_serie_operaciones');
    }
};
