<?php

namespace Database\Seeders;

use App\Models\CatalogoCarrera;
use Illuminate\Database\Seeder;

class CatalogoCarreraSeeder extends Seeder
{
    public function run(): void
    {
        $carreras = [
            ['nombre' => 'Licenciatura en Enfermería', 'activo' => true],
            ['nombre' => 'Licenciatura en Psicología', 'activo' => true],
            ['nombre' => 'Cirujano Dentista', 'activo' => true],
        ];

        foreach ($carreras as $carrera) {
            CatalogoCarrera::query()->updateOrCreate(
                ['nombre' => $carrera['nombre']],
                ['activo' => $carrera['activo']]
            );
        }
    }
}
