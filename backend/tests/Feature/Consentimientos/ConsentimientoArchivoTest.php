<?php

namespace Tests\Feature\Consentimientos;

use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConsentimientoArchivoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'expedientes.manage']);
        Permission::firstOrCreate(['name' => 'expedientes.view']);
        Role::firstOrCreate(['name' => 'alumno']);
    }

    public function test_usuario_autorizado_puede_ver_archivo_de_consentimiento(): void
    {
        Storage::fake('private');

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('expedientes.manage');

        $expediente = Expediente::factory()->create([
            'creado_por' => $usuario->id,
        ]);

        $consentimiento = Consentimiento::factory()->for($expediente)->create([
            'archivo_path' => 'expedientes/'.$expediente->id.'/consentimientos/consentimiento.pdf',
        ]);

        Storage::disk('private')->put($consentimiento->archivo_path, 'contenido');

        $response = $this->actingAs($usuario)->get(route('consentimientos.archivo', $consentimiento));

        $response->assertOk();
    }

    public function test_usuario_no_autorizado_no_puede_ver_archivo_de_consentimiento(): void
    {
        Storage::fake('private');

        $creador = User::factory()->create();
        $creador->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'creado_por' => $creador->id,
        ]);

        $consentimiento = Consentimiento::factory()->for($expediente)->create([
            'archivo_path' => 'expedientes/'.$expediente->id.'/consentimientos/consentimiento.pdf',
        ]);

        Storage::disk('private')->put($consentimiento->archivo_path, 'contenido');

        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario)->get(route('consentimientos.archivo', $consentimiento));

        $response->assertForbidden();
    }
}
