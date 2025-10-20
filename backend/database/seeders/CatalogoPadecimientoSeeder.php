<?php

namespace Database\Seeders;

use App\Models\CatalogoPadecimiento;
use Illuminate\Database\Seeder;

class CatalogoPadecimientoSeeder extends Seeder
{
    public function run(): void
    {
        $padecimientos = [
            ['nombre' => 'Ansiedad generalizada', 'activo' => true],
            ['nombre' => 'Trastorno depresivo', 'activo' => true],
            ['nombre' => 'Estrés postraumático', 'activo' => true],
        ];

        foreach ($padecimientos as $padecimiento) {
            CatalogoPadecimiento::query()->updateOrCreate(
                ['nombre' => $padecimiento['nombre']],
                ['activo' => $padecimiento['activo']]
            );
        }
    }
}
