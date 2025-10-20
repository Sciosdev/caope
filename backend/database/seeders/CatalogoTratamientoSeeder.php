<?php

namespace Database\Seeders;

use App\Models\CatalogoTratamiento;
use Illuminate\Database\Seeder;

class CatalogoTratamientoSeeder extends Seeder
{
    public function run(): void
    {
        $tratamientos = [
            'Psicoterapia individual',
            'Asesoría grupal',
            'Canalización médica',
            'Seguimiento telefónico',
        ];

        foreach ($tratamientos as $nombre) {
            CatalogoTratamiento::query()->updateOrCreate(
                ['nombre' => $nombre],
                ['activo' => true]
            );
        }
    }
}
