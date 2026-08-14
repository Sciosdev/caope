<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultorio_reserva_solicitudes', function (Blueprint $table): void {
            $table->dropForeign(['consultorio_reserva_id']);
            $table->unsignedBigInteger('consultorio_reserva_id')->nullable()->change();
            $table->foreign('consultorio_reserva_id')
                ->references('id')
                ->on('consultorio_reservas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('consultorio_reserva_solicitudes')
            ->whereNull('consultorio_reserva_id')
            ->delete();

        Schema::table('consultorio_reserva_solicitudes', function (Blueprint $table): void {
            $table->dropForeign(['consultorio_reserva_id']);
            $table->unsignedBigInteger('consultorio_reserva_id')->nullable(false)->change();
            $table->foreign('consultorio_reserva_id')
                ->references('id')
                ->on('consultorio_reservas')
                ->cascadeOnDelete();
        });
    }
};
