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
            $table->json('antecedentes_familiares')
                ->after('coordinador_id')
                ->default(json_encode($this->defaultFamilyHistory(), JSON_UNESCAPED_UNICODE));
            $table->text('antecedentes_observaciones')
                ->after('antecedentes_familiares')
                ->nullable();
        });

        DB::table('expedientes')->update([
            'antecedentes_familiares' => json_encode($this->defaultFamilyHistory(), JSON_UNESCAPED_UNICODE),
            'antecedentes_observaciones' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropColumn(['antecedentes_familiares', 'antecedentes_observaciones']);
        });
    }

    private function defaultFamilyHistory(): array
    {
        $members = [
            'madre',
            'padre',
            'hermanos',
            'abuelos',
            'tios',
            'otros',
        ];

        $conditions = [
            'diabetes_mellitus',
            'hipertension_arterial',
            'cardiopatias',
            'cancer',
            'obesidad',
            'enfermedad_renal',
            'trastornos_mentales',
        ];

        $defaults = [];

        foreach ($conditions as $condition) {
            $defaults[$condition] = [];

            foreach ($members as $member) {
                $defaults[$condition][$member] = false;
            }
        }

        return $defaults;
    }
};
