<?php

namespace Tests\Feature\Consentimientos;

use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\Sequence;
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

        $response = $this->actingAs($usuario)->get(route('expedientes.consentimientos.pdf', $expediente));

        $response->assertOk();
        $response->assertViewIs('consentimientos.pdf');
        $response->assertViewHas('expediente', function ($value) use ($expediente) {
            return $value->id === $expediente->id;
        });
        $response->assertViewHas('consentimientos', function ($value) use ($consentimiento) {
            return $value->contains($consentimiento);
        });
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
