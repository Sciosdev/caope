<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Expediente;

class ExpedienteSeeder extends Seeder
{
    public function run(): void
    {
        \App\Models\Expediente::factory(80)->create();
    }
}
