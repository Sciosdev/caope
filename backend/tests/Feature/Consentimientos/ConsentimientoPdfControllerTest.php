<?php

namespace Tests\Feature\Consentimientos;

use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConsentimientoPdfControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'expedientes.view']);
        Role::firstOrCreate(['name' => 'alumno']);
    }

    public function test_usuario_puede_descargar_pdf_de_consentimientos(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('expedientes.view');
        $usuario->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'no_control' => 'CA-2024-0001',
            'paciente' => 'Juan Pérez',
            'creado_por' => $usuario->id,
        ]);

        $consentimiento = Consentimiento::factory()
            ->for($expediente)
            ->create([
                'tratamiento' => 'Consentimiento informado',
                'requerido' => true,
                'aceptado' => true,
                'fecha' => now()->startOfDay(),
            ]);

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('consentimientos.pdf', Mockery::on(function (array $data) use ($expediente, $consentimiento) {
                $this->assertArrayHasKey('expediente', $data);
                $this->assertArrayHasKey('consentimientos', $data);
                $this->assertArrayHasKey('fechaEmision', $data);
                $this->assertSame($expediente->id, $data['expediente']->id);
                $this->assertTrue($data['consentimientos']->contains($consentimiento));

                return true;
            }))
            ->andReturnSelf();

        Pdf::shouldReceive('setPaper')
            ->once()
            ->with('letter')
            ->andReturnSelf();

        Pdf::shouldReceive('stream')
            ->once()
            ->with(sprintf('expediente-%s-consentimientos.pdf', $expediente->no_control))
            ->andReturn(new Response('PDF CONTENT', 200, ['Content-Type' => 'application/pdf']));

        $response = $this->actingAs($usuario)->get(route('expedientes.consentimientos.pdf', $expediente));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_usuario_sin_permiso_no_puede_descargar_pdf(): void
    {
        $creador = User::factory()->create();
        $expediente = Expediente::factory()->create([
            'creado_por' => $creador->id,
        ]);

        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario)->get(route('expedientes.consentimientos.pdf', $expediente));

        $response->assertForbidden();
    }
}
