<?php

namespace Tests\Feature\Consentimientos;

use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\Sequence;
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

    public function test_plantilla_pdf_muestra_datos_del_consentimiento(): void
    {
        $expediente = Expediente::factory()->create([
            'no_control' => 'CA-2024-0002',
            'paciente' => 'María López',
        ]);

        $consentimientos = Consentimiento::factory()
            ->for($expediente)
            ->count(2)
            ->state(new Sequence(
                ['tratamiento' => 'Limpieza dental', 'requerido' => true, 'aceptado' => true, 'fecha' => Carbon::parse('2024-01-10')],
                ['tratamiento' => 'Extracción', 'requerido' => false, 'aceptado' => false, 'fecha' => Carbon::parse('2024-02-05')],
            ))
            ->create();

        $html = view('consentimientos.pdf', [
            'expediente' => $expediente->fresh(['tutor', 'coordinador']),
            'consentimientos' => $consentimientos,
            'fechaEmision' => Carbon::parse('2024-03-15 10:00'),
            'logoPath' => public_path('assets/images/others/logo-placeholder.png'),
            'textoIntroduccion' => '',
            'textoCierre' => '',
        ])->render();

        $this->assertStringContainsString('María López', $html);
        $this->assertStringContainsString('CA-2024-0002', $html);
        $this->assertStringContainsString('15/03/2024', $html);
        $this->assertStringContainsString('Limpieza dental', $html);
        $this->assertStringContainsString('Extracción', $html);
        $this->assertStringContainsString('Sí', $html);
        $this->assertStringContainsString('No', $html);
        $this->assertStringContainsString('10/01/2024', $html);
        $this->assertStringContainsString('05/02/2024', $html);
    }
}
