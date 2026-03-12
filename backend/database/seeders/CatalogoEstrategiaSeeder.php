<?php

namespace Database\Seeders;

use App\Models\CatalogoEstrategia;
use Illuminate\Database\Seeder;

class CatalogoEstrategiaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Intervención breve', 'Terapia individual', 'Terapia grupal', 'Canalización externa'] as $estrategia) {
            CatalogoEstrategia::query()->updateOrCreate(
                ['nombre' => $estrategia],
                ['activo' => true]
            );
        }
    }
}
