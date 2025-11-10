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
            $table->text('antecedente_padecimiento_actual')
                ->nullable()
                ->after('antecedentes_personales_observaciones');
            $table->json('aparatos_sistemas')
                ->nullable()
                ->default(json_encode(Expediente::defaultSystemsReview(), JSON_UNESCAPED_UNICODE))
                ->after('antecedente_padecimiento_actual');
        });

        $defaultSystems = json_encode(Expediente::defaultSystemsReview(), JSON_UNESCAPED_UNICODE);

        DB::table('expedientes')
            ->whereNull('aparatos_sistemas')
            ->update(['aparatos_sistemas' => $defaultSystems]);

        Schema::table('expedientes', function (Blueprint $table) use ($defaultSystems) {
            $table->json('aparatos_sistemas')
                ->default($defaultSystems)
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropColumn(['aparatos_sistemas', 'antecedente_padecimiento_actual']);
        });
    }
};
