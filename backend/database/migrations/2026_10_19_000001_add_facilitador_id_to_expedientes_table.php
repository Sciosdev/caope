<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('expedientes', 'facilitador_id')) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->unsignedBigInteger('facilitador_id')->nullable();
            });
        }

        if (! Schema::hasIndex('expedientes', ['facilitador_id'])) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->index('facilitador_id');
            });
        }

        $hasForeignKey = collect(Schema::getForeignKeys('expedientes'))
            ->contains(fn (array $key): bool => $key['columns'] === ['facilitador_id']);

        if (! $hasForeignKey) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->foreign('facilitador_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Conserva el acceso anterior sin deducir identidades a partir de nombres.
        DB::table('expedientes')->whereNull('facilitador_id')
            ->update(['facilitador_id' => DB::raw('creado_por')]);
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table): void {
            $table->dropForeign(['facilitador_id']);
            $table->dropIndex(['facilitador_id']);
            $table->dropColumn('facilitador_id');
        });
    }
};
