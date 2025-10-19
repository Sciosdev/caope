<?php

namespace Database\Seeders;

use App\Models\Expediente;
use Illuminate\Database\Seeder;

class ExpedienteSeeder extends Seeder
{
    public function run(): void
    {
        Expediente::factory(80)->create();
    }
}
