<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->text('antecedente_padecimiento_actual')
                ->nullable()
                ->after('antecedentes_personales_observaciones');
            $table->json('aparatos_sistemas')
                ->nullable()
                ->after('antecedente_padecimiento_actual');
        });

        $defaultSystems = json_encode([
            'digestivo' => null,
            'respiratorio' => null,
            'cardiovascular' => null,
        ], JSON_UNESCAPED_UNICODE);

        DB::table('expedientes')
            ->whereNull('aparatos_sistemas')
            ->update(['aparatos_sistemas' => $defaultSystems]);
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropColumn(['aparatos_sistemas', 'antecedente_padecimiento_actual']);
        });
    }
};
