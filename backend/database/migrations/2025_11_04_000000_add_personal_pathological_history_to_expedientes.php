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
            $table->json('antecedentes_personales_patologicos')
                ->after('antecedentes_observaciones')
                ->nullable();
            $table->text('antecedentes_personales_observaciones')
                ->after('antecedentes_personales_patologicos')
                ->nullable();
        });

        DB::table('expedientes')->update([
            'antecedentes_personales_patologicos' => json_encode(Expediente::defaultPersonalPathologicalHistory(), JSON_UNESCAPED_UNICODE),
            'antecedentes_personales_observaciones' => null,
        ]);

        Schema::table('expedientes', function (Blueprint $table) {
            $table->json('antecedentes_personales_patologicos')
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropColumn([
                'antecedentes_personales_patologicos',
                'antecedentes_personales_observaciones',
            ]);
        });
    }
};
