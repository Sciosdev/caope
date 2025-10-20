<?php

namespace Database\Seeders;

use App\Models\CatalogoTurno;
use Illuminate\Database\Seeder;

class CatalogoTurnoSeeder extends Seeder
{
    public function run(): void
    {
        $turnos = [
            'Matutino',
            'Vespertino',
            'Mixto',
        ];

        foreach ($turnos as $nombre) {
            CatalogoTurno::query()->updateOrCreate(
                ['nombre' => $nombre],
                ['activo' => true]
            );
        }
    }
}
