<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultorio_reservas', function (Blueprint $table): void {
            if (! Schema::hasColumn('consultorio_reservas', 'origen_expediente')) {
                $table->boolean('origen_expediente')->default(false)->after('creado_por')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('consultorio_reservas', function (Blueprint $table): void {
            if (Schema::hasColumn('consultorio_reservas', 'origen_expediente')) {
                $table->dropIndex(['origen_expediente']);
                $table->dropColumn('origen_expediente');
            }
        });
    }
};
