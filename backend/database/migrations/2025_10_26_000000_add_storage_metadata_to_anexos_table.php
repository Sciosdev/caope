<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('anexos')) {
            return;
        }

        if (! Schema::hasColumn('anexos', 'disk')) {
            Schema::table('anexos', function (Blueprint $table) {
                $table->string('disk', 32)->default('private')->after('ruta');
            });

            DB::table('anexos')->whereNull('disk')->update(['disk' => 'private']);
        }

        if (! Schema::hasColumn('anexos', 'es_privado')) {
            Schema::table('anexos', function (Blueprint $table) {
                $table->boolean('es_privado')->default(true)->after('disk');
            });

            DB::table('anexos')->whereNull('es_privado')->update(['es_privado' => true]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('anexos')) {
            return;
        }

        if (Schema::hasColumn('anexos', 'es_privado')) {
            Schema::table('anexos', function (Blueprint $table) {
                $table->dropColumn('es_privado');
            });
        }

        if (Schema::hasColumn('anexos', 'disk')) {
            Schema::table('anexos', function (Blueprint $table) {
                $table->dropColumn('disk');
            });
        }
    }
};
