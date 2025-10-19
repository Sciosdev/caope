<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            CatalogoCarreraSeeder::class,
            CatalogoTurnoSeeder::class,
            CatalogoPadecimientoSeeder::class,
            CatalogoTratamientoSeeder::class,
            ExpedienteSeeder::class,
        ]);
    }
}
