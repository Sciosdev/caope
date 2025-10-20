<?php

namespace Database\Seeders;

use App\Models\CatalogoTratamiento;
use Illuminate\Database\Seeder;

class CatalogoTratamientoSeeder extends Seeder
{
    public function run(): void
    {
        $tratamientos = [
            ['nombre' => 'Terapia cognitivo conductual', 'activo' => true],
            ['nombre' => 'Terapia familiar estructural', 'activo' => true],
            ['nombre' => 'Terapia psicodinámica breve', 'activo' => true],
        ];

        foreach ($tratamientos as $tratamiento) {
            CatalogoTratamiento::query()->updateOrCreate(
                ['nombre' => $tratamiento['nombre']],
                ['activo' => $tratamiento['activo']]
            );
        }
    }
}
