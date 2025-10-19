<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Expediente;

class ExpedienteSeeder extends Seeder
{
    public function run(): void
    {
        Expediente::factory()->count(40)->create();
    }
}
