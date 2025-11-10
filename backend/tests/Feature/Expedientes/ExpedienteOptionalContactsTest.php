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

        $payload = [
            'no_control' => 'CA-2025-0999',
            'paciente' => 'Paciente sin asignaciones',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'tutor_id' => '0',
            'coordinador_id' => '0',
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
