<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User; // <-- IMPORTANTE

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Usuario de prueba (opcional)
        User::factory()->create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Nuestros datos fake de expedientes
        $this->call([
            ExpedienteSeeder::class,
        ]);
    }
}
