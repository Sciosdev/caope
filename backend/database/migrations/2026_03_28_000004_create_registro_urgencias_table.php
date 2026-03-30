<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('registro_urgencias')) {
            return;
        }

        Schema::create('registro_urgencias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expediente_id')->unique()->constrained('expedientes')->cascadeOnDelete();
            $table->string('nivel_riesgo', 30)->nullable();
            $table->text('motivo')->nullable();
            $table->boolean('canalizacion_inmediata')->default(false);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_urgencias');
    }
};
