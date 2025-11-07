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
            $table->json('antecedentes_clinicos')
                ->after('antecedentes_observaciones')
                ->default(json_encode($this->defaultClinicalHistory(), JSON_UNESCAPED_UNICODE));
            $table->string('antecedentes_clinicos_otros', 120)
                ->after('antecedentes_clinicos')
                ->nullable();
            $table->text('antecedentes_clinicos_observaciones')
                ->after('antecedentes_clinicos_otros')
                ->nullable();
        });

        DB::table('expedientes')->update([
            'antecedentes_clinicos' => json_encode($this->defaultClinicalHistory(), JSON_UNESCAPED_UNICODE),
            'antecedentes_clinicos_otros' => null,
            'antecedentes_clinicos_observaciones' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropColumn([
                'antecedentes_clinicos',
                'antecedentes_clinicos_otros',
                'antecedentes_clinicos_observaciones',
            ]);
        });
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function defaultClinicalHistory(): array
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
            'diabetes',
            'hipertension_arterial',
            'enfermedad_cardiaca',
            'cancer',
            'obesidad',
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
