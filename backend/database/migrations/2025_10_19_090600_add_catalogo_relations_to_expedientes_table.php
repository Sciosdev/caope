<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('expedientes', 'carrera')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->dropIndex(['carrera']);
                $table->dropColumn('carrera');
            });
        }

        if (Schema::hasColumn('expedientes', 'turno')) {
            Schema::table('expedientes', function (Blueprint $table) {
                $table->dropIndex(['turno']);
                $table->dropColumn('turno');
            });
        }

        Schema::table('expedientes', function (Blueprint $table) {
            $table->foreignId('carrera_id')->nullable()->after('apertura')->constrained('catalogo_carreras')->nullOnDelete();
            $table->foreignId('turno_id')->nullable()->after('carrera_id')->constrained('catalogo_turnos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('turno_id');
            $table->dropConstrainedForeignId('carrera_id');
        });

        Schema::table('expedientes', function (Blueprint $table) {
            $table->string('carrera', 60)->nullable()->index()->after('apertura');
            $table->string('turno', 40)->nullable()->index()->after('carrera');
        });
    }
};
