<?php

namespace Database\Seeders;

use App\Models\CatalogoPadecimiento;
use Illuminate\Database\Seeder;

class CatalogoPadecimientoSeeder extends Seeder
{
    public function run(): void
    {
        $padecimientos = [
            ['clave' => 'ANS', 'nombre' => 'Ansiedad generalizada', 'estado' => 'activo'],
            ['clave' => 'DEP', 'nombre' => 'Trastorno depresivo', 'estado' => 'activo'],
            ['clave' => 'EST', 'nombre' => 'Estrés postraumático', 'estado' => 'activo'],
        ];

        foreach ($padecimientos as $padecimiento) {
            CatalogoPadecimiento::query()->updateOrCreate(
                ['clave' => $padecimiento['clave']],
                ['nombre' => $padecimiento['nombre'], 'estado' => $padecimiento['estado']]
            );
        }
    }
}
