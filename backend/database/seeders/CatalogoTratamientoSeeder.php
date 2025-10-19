<?php

namespace Database\Seeders;

use App\Models\CatalogoTratamiento;
use Illuminate\Database\Seeder;

class CatalogoTratamientoSeeder extends Seeder
{
    public function run(): void
    {
        $tratamientos = [
            ['clave' => 'TCC', 'nombre' => 'Terapia cognitivo conductual', 'estado' => 'activo'],
            ['clave' => 'TFE', 'nombre' => 'Terapia familiar estructural', 'estado' => 'activo'],
            ['clave' => 'TPP', 'nombre' => 'Terapia psicodinámica breve', 'estado' => 'activo'],
        ];

        foreach ($tratamientos as $tratamiento) {
            CatalogoTratamiento::query()->updateOrCreate(
                ['clave' => $tratamiento['clave']],
                ['nombre' => $tratamiento['nombre'], 'estado' => $tratamiento['estado']]
            );
        }
    }
}
