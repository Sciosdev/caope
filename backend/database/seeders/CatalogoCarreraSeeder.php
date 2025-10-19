<?php

namespace Database\Seeders;

use App\Models\CatalogoCarrera;
use Illuminate\Database\Seeder;

class CatalogoCarreraSeeder extends Seeder
{
    public function run(): void
    {
        $carreras = [
            ['clave' => 'ENF', 'nombre' => 'Licenciatura en Enfermería', 'estado' => 'activo'],
            ['clave' => 'PSI', 'nombre' => 'Licenciatura en Psicología', 'estado' => 'activo'],
            ['clave' => 'ODO', 'nombre' => 'Cirujano Dentista', 'estado' => 'activo'],
        ];

        foreach ($carreras as $carrera) {
            CatalogoCarrera::query()->updateOrCreate(
                ['clave' => $carrera['clave']],
                ['nombre' => $carrera['nombre'], 'estado' => $carrera['estado']]
            );
        }
    }
}
