<?php

namespace Tests\Feature\Expedientes;

use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
    }

    public function test_paps_puede_ver_los_mismos_expedientes_que_admin_en_el_indice(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $paps = User::factory()->create();
        $paps->assignRole('paps');

        $expedienteUno = Expediente::factory()->create([
            'no_control' => 'CA-2026-0001',
            'paciente' => 'Paciente Uno',
        ]);
        $expedienteDos = Expediente::factory()->create([
            'no_control' => 'CA-2026-0002',
            'paciente' => 'Paciente Dos',
        ]);

        $adminResponse = $this->actingAs($admin)->get(route('expedientes.index'));
        $adminResponse->assertOk();
        $adminResponse->assertSee($expedienteUno->no_control);
        $adminResponse->assertSee($expedienteDos->no_control);

        $papsResponse = $this->actingAs($paps)->get(route('expedientes.index'));
        $papsResponse->assertOk();
        $papsResponse->assertSee($expedienteUno->no_control);
        $papsResponse->assertSee($expedienteDos->no_control);
    }
}
