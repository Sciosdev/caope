<?php

namespace Database\Seeders;

use App\Models\CatalogoTurno;
use Illuminate\Database\Seeder;

class CatalogoTurnoSeeder extends Seeder
{
    public function run(): void
    {
        $turnos = [
            ['clave' => 'MAT', 'nombre' => 'Matutino', 'estado' => 'activo'],
            ['clave' => 'VES', 'nombre' => 'Vespertino', 'estado' => 'activo'],
            ['clave' => 'NOC', 'nombre' => 'Nocturno', 'estado' => 'activo'],
        ];

        foreach ($turnos as $turno) {
            CatalogoTurno::query()->updateOrCreate(
                ['clave' => $turno['clave']],
                ['nombre' => $turno['nombre'], 'estado' => $turno['estado']]
            );
        }
    }
}
