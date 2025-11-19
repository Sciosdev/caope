<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesiones', function (Blueprint $table) {
            if (! Schema::hasColumn('sesiones', 'hora_atencion')) {
                $table->time('hora_atencion')->nullable()->after('fecha');
            }

            if (! Schema::hasColumn('sesiones', 'estrategia')) {
                $table->text('estrategia')->nullable()->after('nota');
            }

            if (! Schema::hasColumn('sesiones', 'interconsulta')) {
                $table->string('interconsulta', 120)->nullable()->after('estrategia');
            }

            if (! Schema::hasColumn('sesiones', 'especialidad_referida')) {
                $table->string('especialidad_referida', 120)->nullable()->after('interconsulta');
            }

            if (! Schema::hasColumn('sesiones', 'motivo_referencia')) {
                $table->text('motivo_referencia')->nullable()->after('especialidad_referida');
            }

            if (! Schema::hasColumn('sesiones', 'nombre_facilitador')) {
                $table->string('nombre_facilitador', 120)->nullable()->after('motivo_referencia');
            }

            if (! Schema::hasColumn('sesiones', 'autorizacion_estratega')) {
                $table->string('autorizacion_estratega', 120)->nullable()->after('nombre_facilitador');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sesiones', function (Blueprint $table) {
            $table->dropColumn([
                'hora_atencion',
                'estrategia',
                'interconsulta',
                'especialidad_referida',
                'motivo_referencia',
                'nombre_facilitador',
                'autorizacion_estratega',
            ]);
        });
    }
};
