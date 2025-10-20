<?php

namespace Database\Seeders;

use App\Models\CatalogoPadecimiento;
use Illuminate\Database\Seeder;

class CatalogoPadecimientoSeeder extends Seeder
{
    public function run(): void
    {
        $padecimientos = [
            'Estrés académico',
            'Ansiedad generalizada',
            'Depresión leve',
            'Trastornos del sueño',
        ];

        foreach ($padecimientos as $nombre) {
            CatalogoPadecimiento::query()->updateOrCreate(
                ['nombre' => $nombre],
                ['activo' => true]
            );
        }
    }
}
