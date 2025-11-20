<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            if (! Schema::hasColumn('expedientes', 'resumen_clinico')) {
                $table->json('resumen_clinico')->nullable()->after('observaciones_relevantes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            if (Schema::hasColumn('expedientes', 'resumen_clinico')) {
                $table->dropColumn('resumen_clinico');
            }
        });
    }
};
