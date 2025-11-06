<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expedientes')) {
            return;
        }

        Schema::table('expedientes', function (Blueprint $table) {
            if (! Schema::hasColumn('expedientes', 'antecedentes_familiares')) {
                $table->json('antecedentes_familiares')->nullable()->default('{}');
            }

            if (! Schema::hasColumn('expedientes', 'antecedentes_observaciones')) {
                $table->text('antecedentes_observaciones')->nullable();
            }
        });

        DB::table('expedientes')
            ->whereNull('antecedentes_familiares')
            ->update(['antecedentes_familiares' => json_encode([])]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('expedientes')) {
            return;
        }

        Schema::table('expedientes', function (Blueprint $table) {
            if (Schema::hasColumn('expedientes', 'antecedentes_familiares')) {
                $table->dropColumn('antecedentes_familiares');
            }

            if (Schema::hasColumn('expedientes', 'antecedentes_observaciones')) {
                $table->dropColumn('antecedentes_observaciones');
            }
        });
    }
};
