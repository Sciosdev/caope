<?php

namespace Database\Seeders;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CatalogoCarreraSeeder::class,
            CatalogoTurnoSeeder::class,
            CatalogoPadecimientoSeeder::class,
            CatalogoTratamientoSeeder::class,
            RoleSeeder::class,
        ]);

        $this->seedUsuariosBase();

        $this->call([
            ExpedienteSeeder::class,
        ]);
    }

    private function seedUsuariosBase(): void
    {
        $carreras = CatalogoCarrera::query()->where('activo', true)->pluck('nombre')->values();
        $turnos = CatalogoTurno::query()->where('activo', true)->pluck('nombre')->values();

        $usuarios = [
            [
                'name' => 'Administración General',
                'email' => 'admin@demo.local',
                'carrera' => null,
                'turno' => null,
            ],
            [
                'name' => 'Andrea Alumna',
                'email' => 'alumno@demo.local',
                'carrera' => $carreras->get(0),
                'turno' => $turnos->get(0),
            ],
            [
                'name' => 'Daniel Docente',
                'email' => 'docente@demo.local',
                'carrera' => $carreras->get(1),
                'turno' => $turnos->get(1),
            ],
            [
                'name' => 'Claudia Coordinación',
                'email' => 'coordinacion@demo.local',
                'carrera' => $carreras->get(2),
                'turno' => $turnos->get(2),
            ],
        ];

        $rolesPorEmail = [
            'admin@demo.local' => 'admin',
            'alumno@demo.local' => 'alumno',
            'docente@demo.local' => 'docente',
            'coordinacion@demo.local' => 'coordinador',
        ];

        foreach ($usuarios as $usuario) {
            $usuarioModelo = User::query()->updateOrCreate(
                ['email' => $usuario['email']],
                [
                    'name' => $usuario['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'remember_token' => Str::random(10),
                    'carrera' => $usuario['carrera'],
                    'turno' => $usuario['turno'],
                ]
            );

            if (isset($rolesPorEmail[$usuario['email']])) {
                $usuarioModelo->syncRoles([$rolesPorEmail[$usuario['email']]]);
            }
        }
    }
}
