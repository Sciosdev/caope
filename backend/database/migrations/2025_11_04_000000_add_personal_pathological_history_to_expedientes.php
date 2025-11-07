<?php

return new class extends \Illuminate\Database\Migrations\Migration
{
    public function up(): void
    {
        \Illuminate\Support\Facades\Schema::table('expedientes', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->json('antecedentes_personales_patologicos')
                ->after('antecedentes_observaciones')
                ->default(json_encode($this->defaultPersonalPathologicalHistory(), JSON_UNESCAPED_UNICODE));
            $table->text('antecedentes_personales_observaciones')
                ->after('antecedentes_personales_patologicos')
                ->nullable();
        });

        \Illuminate\Support\Facades\DB::table('expedientes')->update([
            'antecedentes_personales_patologicos' => json_encode($this->defaultPersonalPathologicalHistory(), JSON_UNESCAPED_UNICODE),
            'antecedentes_personales_observaciones' => null,
        ]);
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\Schema::table('expedientes', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->dropColumn([
                'antecedentes_personales_patologicos',
                'antecedentes_personales_observaciones',
            ]);
        });
    }

    private function defaultPersonalPathologicalHistory(): array
    {
        $conditions = [
            'varicela',
            'rubeola',
            'sarampion',
            'parotiditis',
            'tosferina',
            'escarlatina',
            'parasitosis',
            'hepatitis',
            'sida',
            'asma',
            'disfunciones_endocrinas',
            'hipertension',
            'cancer',
            'enfermedades_transmision_sexual',
            'epilepsia',
            'amigdalitis_repeticion',
            'tuberculosis',
            'fiebre_reumatica',
            'diabetes',
            'enfermedades_cardiovasculares',
            'artritis',
            'traumatismos_con_secuelas',
            'intervenciones_quirurgicas',
            'transfusiones_sanguineas',
            'alergias',
        ];

        return collect($conditions)
            ->mapWithKeys(function (string $condition) {
                return [
                    $condition => [
                        'padece' => false,
                        'fecha' => null,
                    ],
                ];
            })
            ->all();
    }
};
