<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $carrerasRequeridas = [
            'Licenciatura en Medico Cirujano',
            'Licenciatura en Biologia',
            'Licenciatura en Ecologia',
            'Otro plantel de la UNAM.',
        ];

        foreach ($carrerasRequeridas as $carrera) {
            DB::table('catalogo_carreras')->updateOrInsert(
                ['nombre' => $carrera],
                [
                    'activo' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        DB::table('catalogo_carreras')
            ->where('nombre', 'Otro plantel de la UNAM')
            ->update([
                'nombre' => 'Otro plantel de la UNAM.',
                'activo' => true,
                'updated_at' => now(),
            ]);

        DB::table('users')
            ->where('carrera', 'Otro plantel de la UNAM')
            ->update(['carrera' => 'Otro plantel de la UNAM.']);

        DB::table('expedientes')
            ->where('carrera', 'Otro plantel de la UNAM')
            ->update(['carrera' => 'Otro plantel de la UNAM.']);
    }

    public function down(): void
    {
        DB::table('catalogo_carreras')
            ->whereIn('nombre', [
                'Licenciatura en Medico Cirujano',
                'Licenciatura en Biologia',
                'Licenciatura en Ecologia',
                'Otro plantel de la UNAM.',
            ])
            ->delete();

        DB::table('catalogo_carreras')->updateOrInsert(
            ['nombre' => 'Otro plantel de la UNAM'],
            [
                'activo' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        DB::table('users')
            ->where('carrera', 'Otro plantel de la UNAM.')
            ->update(['carrera' => 'Otro plantel de la UNAM']);

        DB::table('expedientes')
            ->where('carrera', 'Otro plantel de la UNAM.')
            ->update(['carrera' => 'Otro plantel de la UNAM']);
    }
};
