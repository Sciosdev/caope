<?php

namespace Tests\Feature\Expedientes;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteOptionalContactsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();
    }

    public function test_admin_can_store_expediente_without_tutor_or_coordinator_selection(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Psicología',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Matutino',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $idVariants = [
            ['tutor_id' => '0', 'coordinador_id' => '0'],
            ['tutor_id' => ' 0 ', 'coordinador_id' => ' 0 '],
            ['tutor_id' => '000', 'coordinador_id' => '000'],
        ];

        foreach ($idVariants as $index => $ids) {
            $payload = [
                'no_control' => sprintf('CA-2025-%04d', 9990 + $index),
                'paciente' => 'Paciente sin asignaciones',
                'apertura' => Carbon::now()->toDateString(),
                'carrera' => $carrera->nombre,
                'turno' => $turno->nombre,
                'tutor_id' => $ids['tutor_id'],
                'coordinador_id' => $ids['coordinador_id'],
            ];

            $response = $this->actingAs($admin)->post(route('expedientes.store'), $payload);

            $response->assertStatus(302);
            $response->assertSessionHasNoErrors();

            $expediente = Expediente::where('no_control', $payload['no_control'])->first();
            $this->assertNotNull($expediente, 'El expediente no se creó en la base de datos.');
            $this->assertNull($expediente->tutor_id, 'El tutor debe quedar sin asignar cuando el formulario envía 0.');
            $this->assertNull($expediente->coordinador_id, 'El coordinador debe quedar sin asignar cuando el formulario envía 0.');
            $this->assertSame($admin->id, $expediente->creado_por);
        }
    }

    public function test_contact_payloads_accept_json_and_optional_values(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Trabajo Social',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Mixto',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $payload = [
            'no_control' => 'CA-2025-7777',
            'paciente' => 'Paciente Contactos',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'contacto_emergencia' => json_encode([
                'nombre' => '   ',
                'parentesco' => '',
                'correo' => '  ',
                'telefono' => '',
                'horario' => 'Noches',
            ]),
            'medico_referencia' => json_encode([
                'nombre' => 'Dr. JSON',
                'correo' => 'json@example.com',
                'telefono' => '(55) 2424-8260',
            ]),
        ];

        $response = $this->actingAs($admin)->post(route('expedientes.store'), $payload);

        $response->assertSessionHasNoErrors();

        $expediente = Expediente::where('no_control', $payload['no_control'])->first();
        $this->assertNotNull($expediente);

        $this->assertNull($expediente->contacto_emergencia_nombre);
        $this->assertNull($expediente->contacto_emergencia_parentesco);
        $this->assertNull($expediente->contacto_emergencia_correo);
        $this->assertNull($expediente->contacto_emergencia_telefono);
        $this->assertSame('Noches', $expediente->contacto_emergencia_horario);
        $this->assertSame('Dr. JSON', $expediente->medico_referencia_nombre);
        $this->assertSame('json@example.com', $expediente->medico_referencia_correo);
        $this->assertSame('(55) 2424-8260', $expediente->medico_referencia_telefono);
    }
}
