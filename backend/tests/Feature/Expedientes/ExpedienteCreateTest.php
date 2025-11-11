<?php

namespace Tests\Feature\Expedientes;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteCreateTest extends TestCase
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

    public function test_admin_crea_expediente_registra_timeline_y_redirige(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Enfermería',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Matutino',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $payload = [
            'no_control' => 'CA-2025-0001',
            'paciente' => 'Paciente Demo',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
        ];

        $response = $this->actingAs($admin)->post(route('expedientes.store'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Expediente creado correctamente.');

        $expediente = Expediente::where('no_control', $payload['no_control'])->first();
        $this->assertNotNull($expediente);

        $response->assertRedirect(route('expedientes.show', $expediente));

        $this->assertSame($admin->id, $expediente->creado_por);
        $this->assertSame($payload['paciente'], $expediente->paciente);
        $this->assertSame($payload['carrera'], $expediente->carrera);
        $this->assertSame($payload['turno'], $expediente->turno);

        $this->assertDatabaseHas('timeline_eventos', [
            'expediente_id' => $expediente->id,
            'actor_id' => $admin->id,
            'evento' => 'expediente.creado',
        ]);

        $evento = $expediente->timelineEventos()
            ->where('evento', 'expediente.creado')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($evento);
        $this->assertSame($payload['no_control'], $evento->payload['datos']['no_control']);
        $this->assertSame($payload['paciente'], $evento->payload['datos']['paciente']);
        $this->assertSame($payload['turno'], $evento->payload['datos']['turno']);
    }

    public function test_creacion_con_json_incompletos_normaliza_con_defaults(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Psicología',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Vespertino',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $payload = [
            'no_control' => 'CA-2025-0300',
            'paciente' => 'Paciente con Historial',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'antecedentes_familiares' => [
                'diabetes_mellitus' => [
                    'madre' => 'si',
                    'padre' => 'no',
                ],
            ],
            'antecedentes_personales_patologicos' => [
                'asma' => [
                    'padece' => 'Yes',
                    'fecha' => '2024-01-10',
                ],
                'cancer' => [
                    'padece' => '',
                ],
            ],
            'aparatos_sistemas' => [
                'digestivo' => '   ',
                'respiratorio' => 'Sin alteraciones respiratorias',
                'nervioso' => 0,
            ],
        ];

        $response = $this->actingAs($admin)->post(route('expedientes.store'), $payload);

        $response->assertSessionHasNoErrors();

        $expediente = Expediente::where('no_control', $payload['no_control'])->first();
        $this->assertNotNull($expediente);

        $defaultsFamily = Expediente::defaultFamilyHistory();
        $this->assertSame(array_keys($defaultsFamily), array_keys($expediente->antecedentes_familiares));
        $this->assertSame(array_keys($defaultsFamily['diabetes_mellitus']), array_keys($expediente->antecedentes_familiares['diabetes_mellitus']));
        $this->assertTrue($expediente->antecedentes_familiares['diabetes_mellitus']['madre']);
        $this->assertFalse($expediente->antecedentes_familiares['diabetes_mellitus']['padre']);
        $this->assertFalse($expediente->antecedentes_familiares['diabetes_mellitus']['hermanos']);

        $defaultsPersonal = Expediente::defaultPersonalPathologicalHistory();
        $this->assertSame(array_keys($defaultsPersonal), array_keys($expediente->antecedentes_personales_patologicos));
        $this->assertTrue($expediente->antecedentes_personales_patologicos['asma']['padece']);
        $this->assertSame('2024-01-10', $expediente->antecedentes_personales_patologicos['asma']['fecha']);
        $this->assertFalse($expediente->antecedentes_personales_patologicos['cancer']['padece']);
        $this->assertNull($expediente->antecedentes_personales_patologicos['cancer']['fecha']);
        $this->assertFalse($expediente->antecedentes_personales_patologicos['varicela']['padece']);
        $this->assertNull($expediente->antecedentes_personales_patologicos['varicela']['fecha']);

        $defaultsSystems = Expediente::defaultSystemsReview();
        $this->assertSame(array_keys($defaultsSystems), array_keys($expediente->aparatos_sistemas));
        $this->assertNull($expediente->aparatos_sistemas['digestivo']);
        $this->assertSame('Sin alteraciones respiratorias', $expediente->aparatos_sistemas['respiratorio']);
        $this->assertNull($expediente->aparatos_sistemas['nervioso']);
        $this->assertNull($expediente->plan_accion);
    }

    public function test_store_returns_json_payload_with_loaded_relations(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Enfermería',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Matutino',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $payload = [
            'no_control' => 'CA-2025-0400',
            'paciente' => 'Paciente API',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
        ];

        $response = $this->actingAs($admin)->postJson(route('expedientes.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('message', 'Expediente creado correctamente.');
        $response->assertJsonPath('student_error_message', __('expedientes.messages.student_save_error'));

        $expediente = Expediente::where('no_control', $payload['no_control'])->first();
        $this->assertNotNull($expediente);

        $response->assertJsonPath('expediente.id', $expediente->id);
        $response->assertJsonPath('expediente.alumno.id', $admin->id);
        $response->assertJsonPath('expediente.anexos', []);
    }

    public function test_store_includes_context_and_logs_when_columns_are_missing(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Derecho',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Nocturno',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        Schema::shouldReceive('hasColumn')->andReturnFalse();
        Log::spy();

        $payload = [
            'no_control' => 'CA-2025-0500',
            'paciente' => 'Paciente con error',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
        ];

        $response = $this->actingAs($admin)
            ->from(route('expedientes.create'))
            ->postJson(route('expedientes.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('context.reason', 'missing_columns');
        $response->assertJson(fn ($json) => $json->has('context.columns'));

        Log::shouldHaveReceived('info')->with(
            'Received request to create expediente',
            Mockery::on(fn ($context) => ($context['user_id'] ?? null) === $admin->id)
        )->once();
        Log::shouldHaveReceived('debug')->with(
            'Validated expediente data for creation',
            Mockery::on(fn ($context) => ($context['validated_keys'] ?? []) !== [])
        )->once();
        Log::shouldHaveReceived('error')->with(
            'Expediente creation aborted due to missing columns',
            Mockery::on(fn ($context) => ($context['missing_columns'] ?? null) !== null)
        )->once();
    }

    public function test_store_redirects_with_context_when_columns_are_missing(): void
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

        Schema::shouldReceive('hasColumn')->andReturnFalse();

        $payload = [
            'no_control' => 'CA-2025-0600',
            'paciente' => 'Paciente formulario',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
        ];

        $response = $this->actingAs($admin)
            ->from(route('expedientes.create'))
            ->post(route('expedientes.store'), $payload);

        $response->assertRedirect(route('expedientes.create'));
        $response->assertSessionHasErrors('expediente');
        $response->assertSessionHas('expediente_error_context.reason', 'missing_columns');
    }
}
