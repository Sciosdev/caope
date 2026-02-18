<?php

use App\Models\CatalogoCarrera;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const CARRERA_NORMALIZADA = 'nutricion y bienestar integral';

    public function up(): void
    {
        $ids = $this->idsDeCarreraObjetivo();

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('catalogo_carreras')
            ->whereIn('id', $ids)
            ->update([
                'activo' => false,
                'updated_at' => now(),
            ]);

        CatalogoCarrera::flushCache();
    }

    public function down(): void
    {
        $ids = $this->idsDeCarreraObjetivo();

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('catalogo_carreras')
            ->whereIn('id', $ids)
            ->update([
                'activo' => true,
                'updated_at' => now(),
            ]);

        CatalogoCarrera::flushCache();
    }

    private function idsDeCarreraObjetivo()
    {
        return DB::table('catalogo_carreras')
            ->select('id', 'nombre')
            ->get()
            ->filter(function (object $carrera): bool {
                $normalizado = Str::of($carrera->nombre)
                    ->lower()
                    ->ascii()
                    ->squish()
                    ->toString();

                return $normalizado === self::CARRERA_NORMALIZADA;
            })
            ->pluck('id');
    }
};
