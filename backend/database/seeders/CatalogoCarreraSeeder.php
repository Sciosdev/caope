<?php

namespace Database\Seeders;

use App\Models\CatalogoCarrera;
use Illuminate\Database\Seeder;

class CatalogoCarreraSeeder extends Seeder
{
    public function run(): void
    {
        $carreras = [
            'Licenciatura en Enfermería',
            'Licenciatura en Psicología',
            'Cirujano Dentista',
            'Licenciatura en Medico Cirujano',
            'Licenciatura en Biologia',
            'Licenciatura en Ecologia',
            'Otro plantel de la UNAM.',
        ];

        foreach ($carreras as $nombre) {
            CatalogoCarrera::query()->updateOrCreate(
                ['nombre' => $nombre],
                ['activo' => true]
            );
        }
    }
}
