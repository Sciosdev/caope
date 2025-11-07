<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // La estructura de antecedentes clínicos se consolidó en antecedentes familiares hereditarios.
        // Esta migración se mantiene por compatibilidad pero no realiza cambios adicionales.
    }

    public function down(): void
    {
        // Sin acciones necesarias; no se agregaron columnas nuevas en esta versión.
    }
};
