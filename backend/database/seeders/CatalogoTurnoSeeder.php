<?php

namespace Database\Seeders;

use App\Models\CatalogoTurno;
use Illuminate\Database\Seeder;

class CatalogoTurnoSeeder extends Seeder
{
    public function run(): void
    {
        $turnos = [
            ['nombre' => 'Matutino', 'activo' => true],
            ['nombre' => 'Vespertino', 'activo' => true],
            ['nombre' => 'Nocturno', 'activo' => true],
        ];

        foreach ($turnos as $turno) {
            CatalogoTurno::query()->updateOrCreate(
                ['nombre' => $turno['nombre']],
                ['activo' => $turno['activo']]
            );
        }
    }
}
