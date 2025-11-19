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
use Illuminate\Testing\Fluent\AssertableJson;
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

    public function test_admin_captura_ficha_clinica_completa(): void
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
            'no_control' => 'CA-2025-0600',
            'paciente' => 'Paciente Completo',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'clinica' => '  Clínica Norte  ',
            'recibo_expediente' => 'EXP-123',
            'recibo_diagnostico' => 'DX-999',
            'genero' => 'femenino',
            'estado_civil' => 'soltero',
            'ocupacion' => 'Estudiante',
            'escolaridad' => 'Licenciatura',
            'fecha_nacimiento' => '1999-04-10',
            'lugar_nacimiento' => 'CDMX',
            'domicilio_calle' => 'Calle Uno #22',
            'colonia' => 'Centro',
            'delegacion_municipio' => 'Cuauhtémoc',
            'entidad' => 'Ciudad de México',
            'telefono_principal' => '+52 55 1234 5678',
            'fecha_inicio_real' => Carbon::now()->toDateString(),
            'motivo_consulta' => 'Presenta síntomas de ansiedad.',
            'alerta_ingreso' => 'Alergia a la penicilina.',
            'contacto_emergencia_nombre' => 'Juan Pérez',
            'contacto_emergencia_parentesco' => 'Hermano',
            'contacto_emergencia_correo' => 'contacto@example.com',
            'contacto_emergencia_telefono' => '+52 55 9876 5432',
            'contacto_emergencia_horario' => '  9 a 18 horas  ',
            'medico_referencia_nombre' => 'Dra. Méndez',
            'medico_referencia_correo' => 'medica@example.com',
            'medico_referencia_telefono' => '+52 55 9999 1111',
        ];

        $response = $this->actingAs($admin)->post(route('expedientes.store'), $payload);

        $response->assertSessionHasNoErrors();

        $expediente = Expediente::where('no_control', $payload['no_control'])->first();
        $this->assertNotNull($expediente);

        $this->assertSame('Clínica Norte', $expediente->clinica);
        $this->assertSame($payload['recibo_expediente'], $expediente->recibo_expediente);
        $this->assertSame($payload['recibo_diagnostico'], $expediente->recibo_diagnostico);
        $this->assertSame($payload['genero'], $expediente->genero);
        $this->assertSame($payload['estado_civil'], $expediente->estado_civil);
        $this->assertSame($payload['ocupacion'], $expediente->ocupacion);
        $this->assertSame($payload['escolaridad'], $expediente->escolaridad);
        $this->assertSame($payload['fecha_nacimiento'], $expediente->fecha_nacimiento?->toDateString());
        $this->assertSame($payload['lugar_nacimiento'], $expediente->lugar_nacimiento);
        $this->assertSame($payload['domicilio_calle'], $expediente->domicilio_calle);
        $this->assertSame($payload['colonia'], $expediente->colonia);
        $this->assertSame($payload['delegacion_municipio'], $expediente->delegacion_municipio);
        $this->assertSame($payload['entidad'], $expediente->entidad);
        $this->assertSame($payload['telefono_principal'], $expediente->telefono_principal);
        $this->assertSame($payload['fecha_inicio_real'], $expediente->fecha_inicio_real?->toDateString());
        $this->assertSame($payload['motivo_consulta'], $expediente->motivo_consulta);
        $this->assertSame($payload['alerta_ingreso'], $expediente->alerta_ingreso);
        $this->assertSame($payload['contacto_emergencia_nombre'], $expediente->contacto_emergencia_nombre);
        $this->assertSame($payload['contacto_emergencia_parentesco'], $expediente->contacto_emergencia_parentesco);
        $this->assertSame($payload['contacto_emergencia_correo'], $expediente->contacto_emergencia_correo);
        $this->assertSame($payload['contacto_emergencia_telefono'], $expediente->contacto_emergencia_telefono);
        $this->assertSame('9 a 18 horas', $expediente->contacto_emergencia_horario);
        $this->assertSame($payload['medico_referencia_nombre'], $expediente->medico_referencia_nombre);
        $this->assertSame($payload['medico_referencia_correo'], $expediente->medico_referencia_correo);
        $this->assertSame($payload['medico_referencia_telefono'], $expediente->medico_referencia_telefono);
    }

    public function test_validacion_alerta_campos_de_ficha_clinica(): void
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
            'no_control' => 'CA-2025-0601',
            'paciente' => 'Paciente con errores',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'genero' => 'invalid',
            'telefono_principal' => '123',
            'contacto_emergencia_correo' => 'no-es-correo',
        ];

        $response = $this->actingAs($admin)
            ->from(route('expedientes.create'))
            ->post(route('expedientes.store'), $payload);

        $response->assertSessionHasErrors([
            'genero',
            'telefono_principal',
            'contacto_emergencia_correo',
        ]);

        $this->assertDatabaseMissing('expedientes', ['no_control' => $payload['no_control']]);
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
        Schema::shouldReceive('getColumnListing')->andReturn([]);
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
        $response->assertJson(fn (AssertableJson $json) => $json
            ->has('context.columns')
            ->etc()
        );

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
        Schema::shouldReceive('getColumnListing')->andReturn([]);

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

    public function test_admin_can_store_extended_profile_fields(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Nutrición',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Mixto',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $fechaNacimiento = Carbon::now()->subYears(22)->toDateString();
        $fechaInicioReal = Carbon::now()->subWeeks(1)->toDateString();

        $payload = [
            'no_control' => 'CA-2025-5555',
            'paciente' => 'Paciente Integral',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'clinica' => 'Clínica Norte',
            'recibo_expediente' => 'EXP-2025-55',
            'recibo_diagnostico' => 'DX-2025-33',
            'genero' => 'femenino',
            'estado_civil' => 'soltero',
            'ocupacion' => 'Estudiante',
            'escolaridad' => 'Licenciatura',
            'fecha_nacimiento' => $fechaNacimiento,
            'lugar_nacimiento' => 'Ciudad de México',
            'domicilio_calle' => 'Av. Siempre Viva 742',
            'colonia' => 'Centro',
            'delegacion_municipio' => 'Cuauhtémoc',
            'entidad' => 'CDMX',
            'telefono_principal' => '(55) 2424-8260',
            'fecha_inicio_real' => $fechaInicioReal,
            'motivo_consulta' => 'Motivo extendido para la consulta del paciente.',
            'alerta_ingreso' => 'Alergia a penicilina',
            'contacto_emergencia' => [
                'nombre' => 'María Pérez',
                'parentesco' => 'Madre',
                'correo' => 'maria@example.com',
                'telefono' => '(33) 1234-5678',
                'horario' => '9:00 - 18:00',
            ],
            'medico_referencia' => [
                'nombre' => 'Dr. Juan Pérez',
                'correo' => 'doctor@example.com',
                'telefono' => '+52 (81) 5555-4444',
            ],
        ];

        $response = $this->actingAs($admin)->post(route('expedientes.store'), $payload);

        $response->assertSessionHasNoErrors();

        $expediente = Expediente::where('no_control', $payload['no_control'])->first();
        $this->assertNotNull($expediente);

        $this->assertSame('Clínica Norte', $expediente->clinica);
        $this->assertSame('EXP-2025-55', $expediente->recibo_expediente);
        $this->assertSame('DX-2025-33', $expediente->recibo_diagnostico);
        $this->assertSame('femenino', $expediente->genero);
        $this->assertSame('soltero', $expediente->estado_civil);
        $this->assertSame('Estudiante', $expediente->ocupacion);
        $this->assertSame('Licenciatura', $expediente->escolaridad);
        $this->assertSame($fechaNacimiento, optional($expediente->fecha_nacimiento)->format('Y-m-d'));
        $this->assertSame('Ciudad de México', $expediente->lugar_nacimiento);
        $this->assertSame('Av. Siempre Viva 742', $expediente->domicilio_calle);
        $this->assertSame('Centro', $expediente->colonia);
        $this->assertSame('Cuauhtémoc', $expediente->delegacion_municipio);
        $this->assertSame('CDMX', $expediente->entidad);
        $this->assertSame('(55) 2424-8260', $expediente->telefono_principal);
        $this->assertSame($fechaInicioReal, optional($expediente->fecha_inicio_real)->format('Y-m-d'));
        $this->assertSame('Motivo extendido para la consulta del paciente.', $expediente->motivo_consulta);
        $this->assertSame('Alergia a penicilina', $expediente->alerta_ingreso);
        $this->assertSame('María Pérez', $expediente->contacto_emergencia_nombre);
        $this->assertSame('Madre', $expediente->contacto_emergencia_parentesco);
        $this->assertSame('maria@example.com', $expediente->contacto_emergencia_correo);
        $this->assertSame('(33) 1234-5678', $expediente->contacto_emergencia_telefono);
        $this->assertSame('9:00 - 18:00', $expediente->contacto_emergencia_horario);
        $this->assertSame('Dr. Juan Pérez', $expediente->medico_referencia_nombre);
        $this->assertSame('doctor@example.com', $expediente->medico_referencia_correo);
        $this->assertSame('+52 (81) 5555-4444', $expediente->medico_referencia_telefono);
    }

    /**
     * @dataProvider phoneFormatProvider
     */
    public function test_phone_fields_accept_common_formats(string $phoneNumber): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Terapia Física',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Nocturno',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $payload = [
            'no_control' => 'CA-2025-8585',
            'paciente' => 'Paciente con Teléfonos',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'telefono_principal' => $phoneNumber,
            'contacto_emergencia' => [
                'nombre' => 'Contacto Válido',
                'telefono' => $phoneNumber,
            ],
            'medico_referencia' => [
                'nombre' => 'Médico Válido',
                'telefono' => $phoneNumber,
            ],
        ];

        $response = $this->actingAs($admin)->post(route('expedientes.store'), $payload);

        $response->assertSessionHasNoErrors();

        $expediente = Expediente::where('no_control', $payload['no_control'])->first();
        $this->assertNotNull($expediente);

        $this->assertSame($phoneNumber, $expediente->telefono_principal);
        $this->assertSame($phoneNumber, $expediente->contacto_emergencia_telefono);
        $this->assertSame($phoneNumber, $expediente->medico_referencia_telefono);
    }

    public function test_store_rejects_invalid_extended_profile_fields(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $carrera = CatalogoCarrera::create([
            'nombre' => 'Licenciatura en Psicología Clínica',
            'activo' => true,
        ]);

        $turno = CatalogoTurno::create([
            'nombre' => 'Vespertino',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $payload = [
            'no_control' => 'CA-2025-9090',
            'paciente' => 'Paciente Inválido',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'genero' => 'desconocido',
            'estado_civil' => 'complicado',
            'fecha_nacimiento' => Carbon::now()->addDay()->toDateString(),
            'telefono_principal' => 'abc123',
            'contacto_emergencia' => [
                'correo' => 'correo-invalido',
                'telefono' => '(55 1111-2222',
            ],
            'medico_referencia' => [
                'correo' => 'doctor-invalido',
                'telefono' => '55 3333-4444 ext',
            ],
        ];

        $response = $this->actingAs($admin)->post(route('expedientes.store'), $payload);

        $response->assertSessionHasErrors([
            'genero',
            'estado_civil',
            'fecha_nacimiento',
            'telefono_principal',
            'contacto_emergencia_correo',
            'contacto_emergencia_telefono',
            'medico_referencia_correo',
            'medico_referencia_telefono',
        ]);
    }

    public static function phoneFormatProvider(): array
    {
        return [
            'parentheses_and_dash' => ['(55) 2424-8260'],
            'country_code_and_spaces' => ['+52 (55) 1234 5678'],
            'dots_and_extension' => ['55.1234.5678 ext 123'],
        ];
    }
}
