<?php

namespace Database\Seeders;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException('DatabaseSeeder contains demo data and may only run in local or testing environments.');
        }

        $this->call([
            ParametroSeeder::class,
            CatalogoCarreraSeeder::class,
            CatalogoTurnoSeeder::class,
            CatalogoConsultorioSeeder::class,
            CatalogoEstrategiaSeeder::class,
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
        $localCredentials = [];

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
            [
                'name' => 'PAPS Demo',
                'email' => 'paps@demo.local',
                'carrera' => null,
                'turno' => null,
            ],
        ];

        $rolesPorEmail = [
            'admin@demo.local' => 'admin',
            'alumno@demo.local' => 'alumno',
            'docente@demo.local' => 'docente',
            'coordinacion@demo.local' => 'coordinador',
            'paps@demo.local' => 'paps',
        ];

        foreach ($usuarios as $usuario) {
            $password = Str::password(32);
            $usuarioModelo = User::query()->updateOrCreate(
                ['email' => $usuario['email']],
                [
                    'name' => $usuario['name'],
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'remember_token' => null,
                    'carrera' => $usuario['carrera'],
                    'turno' => $usuario['turno'],
                ]
            );

            $localCredentials[] = [$usuario['email'], $password];

            if (isset($rolesPorEmail[$usuario['email']])) {
                $usuarioModelo->syncRoles([$rolesPorEmail[$usuario['email']]]);
            }
        }

        if (app()->environment('local') && $this->command !== null) {
            $this->command->warn('Credenciales efímeras para esta carga demo; cambiarán al volver a ejecutar el seeder.');
            $this->command->table(['Correo', 'Contraseña temporal'], $localCredentials);
        }
    }
}
