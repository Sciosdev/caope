<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('catalogo_carreras')
            ->where('nombre', 'Nutrición y Bienestar Integral')
            ->update([
                'activo' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('catalogo_carreras')
            ->where('nombre', 'Nutrición y Bienestar Integral')
            ->update([
                'activo' => true,
                'updated_at' => now(),
            ]);
    }
};
