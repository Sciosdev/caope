<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consentimientos')) {
            return;
        }

        Schema::table('consentimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('consentimientos', 'subido_por')) {
                $table->foreignId('subido_por')
                    ->nullable()
                    ->after('archivo_path')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('consentimientos')) {
            return;
        }

        Schema::table('consentimientos', function (Blueprint $table) {
            if (Schema::hasColumn('consentimientos', 'subido_por')) {
                $table->dropForeign(['subido_por']);
                $table->dropColumn('subido_por');
            }
        });
    }
};
