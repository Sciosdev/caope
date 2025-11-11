<?php

use App\Models\Expediente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            if (! Schema::hasColumn('expedientes', 'antecedentes_familiares')) {
                $table->json('antecedentes_familiares')
                    ->after('coordinador_id')
                    ->nullable();
            }

            if (! Schema::hasColumn('expedientes', 'antecedentes_observaciones')) {
                $table->text('antecedentes_observaciones')
                    ->after('antecedentes_familiares')
                    ->nullable();
            }
        });

        DB::table('expedientes')
            ->whereNull('antecedentes_familiares')
            ->update([
                'antecedentes_familiares' => json_encode(
                    Expediente::defaultFamilyHistory(),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
                'antecedentes_observaciones' => null,
            ]);

        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE `expedientes` MODIFY `antecedentes_familiares` JSON NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            if (Schema::hasColumn('expedientes', 'antecedentes_observaciones')) {
                $table->dropColumn('antecedentes_observaciones');
            }

            if (Schema::hasColumn('expedientes', 'antecedentes_familiares')) {
                $table->dropColumn('antecedentes_familiares');
            }
        });
    }
};
