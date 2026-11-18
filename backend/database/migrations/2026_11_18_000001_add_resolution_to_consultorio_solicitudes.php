<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('consultorio_reserva_solicitudes', 'resolucion')) {
            Schema::table('consultorio_reserva_solicitudes', function (Blueprint $table): void {
                $table->json('resolucion')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('consultorio_reserva_solicitudes', function (Blueprint $table): void {
            $table->dropColumn('resolucion');
        });
    }
};
