<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expedientes')) {
            return;
        }

        Schema::table('expedientes', function (Blueprint $table) {
            if (! Schema::hasColumn('expedientes', 'consentimientos_observaciones_path')) {
                $table->string('consentimientos_observaciones_path', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('expedientes')) {
            return;
        }

        Schema::table('expedientes', function (Blueprint $table) {
            if (Schema::hasColumn('expedientes', 'consentimientos_observaciones_path')) {
                $table->dropColumn('consentimientos_observaciones_path');
            }
        });
    }
};
