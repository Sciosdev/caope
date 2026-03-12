<?php

namespace Database\Seeders;

use App\Models\CatalogoConsultorio;
use Illuminate\Database\Seeder;

class CatalogoConsultorioSeeder extends Seeder
{
    public function run(): void
    {
        for ($numero = 1; $numero <= 14; $numero++) {
            CatalogoConsultorio::query()->updateOrCreate(
                ['numero' => $numero],
                [
                    'nombre' => 'Consultorio '.$numero,
                    'activo' => true,
                ]
            );
        }
    }
}
