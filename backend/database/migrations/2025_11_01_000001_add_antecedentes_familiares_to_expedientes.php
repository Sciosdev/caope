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

    /**
     * @return array<string, bool>
     */
    private function defaultFamilyHistory(): array
    {
        return [
            'madre' => false,
            'padre' => false,
            'hermanos' => false,
            'abuelos' => false,
            'tios' => false,
            'otros' => false,
        ];
    }
};
