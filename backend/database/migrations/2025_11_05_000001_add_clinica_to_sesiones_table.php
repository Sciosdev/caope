<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sesiones')) {
            return;
        }

        Schema::table('sesiones', function (Blueprint $table) {
            if (! Schema::hasColumn('sesiones', 'clinica')) {
                $table->string('clinica', 120)->nullable()->after('autorizacion_estratega');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sesiones')) {
            return;
        }

        Schema::table('sesiones', function (Blueprint $table) {
            if (Schema::hasColumn('sesiones', 'clinica')) {
                $table->dropColumn('clinica');
            }
        });
    }
};
