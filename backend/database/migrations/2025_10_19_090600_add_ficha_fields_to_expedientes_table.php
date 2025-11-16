<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->string('clinica', 120)->nullable()->after('turno');
            $table->string('recibo_expediente', 120)->nullable()->after('clinica');
            $table->string('recibo_diagnostico', 120)->nullable()->after('recibo_expediente');
            $table->string('genero', 40)->nullable()->after('recibo_diagnostico');
            $table->string('estado_civil', 60)->nullable()->after('genero');
            $table->string('ocupacion', 120)->nullable()->after('estado_civil');
            $table->string('escolaridad', 120)->nullable()->after('ocupacion');
            $table->date('fecha_nacimiento')->nullable()->after('escolaridad');
            $table->string('lugar_nacimiento', 120)->nullable()->after('fecha_nacimiento');
            $table->text('domicilio_calle')->nullable()->after('lugar_nacimiento');
            $table->string('colonia', 120)->nullable()->after('domicilio_calle');
            $table->string('delegacion_municipio', 120)->nullable()->after('colonia');
            $table->string('entidad', 120)->nullable()->after('delegacion_municipio');
            $table->string('telefono_principal', 25)->nullable()->after('entidad');
            $table->date('fecha_inicio_real')->nullable()->after('telefono_principal');
            $table->text('motivo_consulta')->nullable()->after('fecha_inicio_real');
            $table->text('alerta_ingreso')->nullable()->after('motivo_consulta');
            $table->string('contacto_emergencia_nombre', 150)->nullable()->after('alerta_ingreso');
            $table->string('contacto_emergencia_parentesco', 120)->nullable()->after('contacto_emergencia_nombre');
            $table->string('contacto_emergencia_correo', 150)->nullable()->after('contacto_emergencia_parentesco');
            $table->string('contacto_emergencia_telefono', 25)->nullable()->after('contacto_emergencia_correo');
            $table->string('contacto_emergencia_horario', 120)->nullable()->after('contacto_emergencia_telefono');
            $table->string('medico_referencia_nombre', 150)->nullable()->after('contacto_emergencia_horario');
            $table->string('medico_referencia_correo', 150)->nullable()->after('medico_referencia_nombre');
            $table->string('medico_referencia_telefono', 25)->nullable()->after('medico_referencia_correo');
        });
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropColumn([
                'clinica',
                'recibo_expediente',
                'recibo_diagnostico',
                'genero',
                'estado_civil',
                'ocupacion',
                'escolaridad',
                'fecha_nacimiento',
                'lugar_nacimiento',
                'domicilio_calle',
                'colonia',
                'delegacion_municipio',
                'entidad',
                'telefono_principal',
                'fecha_inicio_real',
                'motivo_consulta',
                'alerta_ingreso',
                'contacto_emergencia_nombre',
                'contacto_emergencia_parentesco',
                'contacto_emergencia_correo',
                'contacto_emergencia_telefono',
                'contacto_emergencia_horario',
                'medico_referencia_nombre',
                'medico_referencia_correo',
                'medico_referencia_telefono',
            ]);
        });
    }
};
