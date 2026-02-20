<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultorio_reservas', function (Blueprint $table): void {
            $table->unsignedTinyInteger('cubiculo_numero')->default(1)->after('consultorio_numero');
            $table->index(['consultorio_numero', 'cubiculo_numero', 'fecha'], 'consultorio_cubiculo_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::table('consultorio_reservas', function (Blueprint $table): void {
            $table->dropIndex('consultorio_cubiculo_fecha_idx');
            $table->dropColumn('cubiculo_numero');
        });
    }
};
