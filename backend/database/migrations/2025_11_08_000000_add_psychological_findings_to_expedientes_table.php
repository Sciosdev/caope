<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            if (! Schema::hasColumn('expedientes', 'diagnostico')) {
                $table->text('diagnostico')->nullable()->after('coordinador_id');
            }

            if (! Schema::hasColumn('expedientes', 'dsm_tr')) {
                $table->string('dsm_tr', 255)->nullable()->after('diagnostico');
            }

            if (! Schema::hasColumn('expedientes', 'observaciones_relevantes')) {
                $table->text('observaciones_relevantes')->nullable()->after('dsm_tr');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            if (Schema::hasColumn('expedientes', 'observaciones_relevantes')) {
                $table->dropColumn('observaciones_relevantes');
            }

            if (Schema::hasColumn('expedientes', 'dsm_tr')) {
                $table->dropColumn('dsm_tr');
            }

            if (Schema::hasColumn('expedientes', 'diagnostico')) {
                $table->dropColumn('diagnostico');
            }
        });
    }
};
