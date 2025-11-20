<?php

namespace Tests\Feature;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\CatalogoCarreraSeeder;
use Database\Seeders\CatalogoTurnoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExpedienteAlumnoStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(CatalogoCarreraSeeder::class);
        $this->seed(CatalogoTurnoSeeder::class);
    }

    public function test_student_can_create_expediente_when_schema_complete(): void
    {
        $student = $this->createStudent();

        $payload = [
            'no_control' => 'NC-0001',
            'paciente' => 'Alumno Demo',
            'apertura' => now()->format('Y-m-d'),
            'carrera' => $student->carrera,
            'turno' => $student->turno,
            'plan_accion' => 'Plan inicial',
            'diagnostico' => 'Diagnóstico demo',
            'observaciones_relevantes' => 'Observaciones demo',
        ];

        $response = $this->actingAs($student)->post(route('expedientes.store'), $payload);

        $response->assertRedirect();

        $expediente = Expediente::where('no_control', 'NC-0001')->first();
        $this->assertNotNull($expediente);
        $this->assertSame($student->id, $expediente->creado_por);

        $response->assertRedirect(route('expedientes.show', $expediente));
    }

    public function test_student_receives_error_when_optional_columns_missing(): void
    {
        $student = $this->createStudent();

        $columnsToDrop = array_values(array_filter([
            Schema::hasColumn('expedientes', 'plan_accion') ? 'plan_accion' : null,
            Schema::hasColumn('expedientes', 'diagnostico') ? 'diagnostico' : null,
            Schema::hasColumn('expedientes', 'observaciones_relevantes') ? 'observaciones_relevantes' : null,
        ]));

        if (! empty($columnsToDrop)) {
            Schema::table('expedientes', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }

        $payload = [
            'no_control' => 'NC-0002',
            'paciente' => 'Alumno Demo',
            'apertura' => now()->format('Y-m-d'),
            'carrera' => $student->carrera,
            'turno' => $student->turno,
            'plan_accion' => 'Plan faltante',
        ];

        $response = $this->from(route('expedientes.create'))
            ->actingAs($student)
            ->post(route('expedientes.store'), $payload);

        $response->assertRedirect(route('expedientes.create'));
        $response->assertSessionHasErrors([
            'expediente' => __('expedientes.messages.student_save_error'),
        ]);

        $this->assertDatabaseMissing('expedientes', [
            'no_control' => 'NC-0002',
        ]);
    }

    public function test_student_receives_error_when_form_columns_missing_in_schema(): void
    {
        $student = $this->createStudent();

        Schema::table('expedientes', function (Blueprint $table): void {
            if (Schema::hasColumn('expedientes', 'contacto_emergencia_horario')) {
                $table->dropColumn('contacto_emergencia_horario');
            }
        });

        $payload = [
            'no_control' => 'NC-0100',
            'paciente' => 'Alumno Completo',
            'apertura' => now()->format('Y-m-d'),
            'carrera' => $student->carrera,
            'turno' => $student->turno,
            'clinica' => 'Clínica Norte',
            'fecha_inicio_real' => now()->format('Y-m-d'),
            'recibo_expediente' => 'REC-EXP-01',
            'recibo_diagnostico' => 'REC-DIAG-02',
            'genero' => 'femenino',
            'estado_civil' => 'soltero',
            'ocupacion' => 'Estudiante',
            'escolaridad' => 'Licenciatura',
            'fecha_nacimiento' => now()->subYears(20)->format('Y-m-d'),
            'lugar_nacimiento' => 'Ciudad de México',
            'domicilio_calle' => 'Calle Falsa 123',
            'colonia' => 'Centro',
            'delegacion_municipio' => 'Benito Juárez',
            'entidad' => 'CDMX',
            'telefono_principal' => '+52 5512345678',
            'motivo_consulta' => 'Motivo de prueba',
            'alerta_ingreso' => 'Alerta de prueba',
            'contacto_emergencia_nombre' => 'Contacto Emergencia',
            'contacto_emergencia_parentesco' => 'Hermano',
            'contacto_emergencia_correo' => 'contacto@example.com',
            'contacto_emergencia_telefono' => '+52 5587654321',
            'contacto_emergencia_horario' => '9am - 5pm',
            'medico_referencia_nombre' => 'Dr. Demo',
            'medico_referencia_correo' => 'doctor@example.com',
            'medico_referencia_telefono' => '+52 5543216789',
        ];

        $response = $this->from(route('expedientes.create'))
            ->actingAs($student)
            ->post(route('expedientes.store'), $payload);

        $response->assertRedirect(route('expedientes.create'));
        $response->assertSessionHasErrors([
            'expediente' => __('expedientes.messages.student_save_error'),
        ]);

        $response->assertSessionHas('expediente_error_context', function ($context) {
            return ($context['reason'] ?? null) === 'missing_columns'
                && in_array('contacto_emergencia_horario', $context['columns'] ?? [], true);
        });

        $this->assertDatabaseMissing('expedientes', [
            'no_control' => 'NC-0100',
        ]);
    }

    public function test_student_update_returns_error_when_optional_columns_missing(): void
    {
        $student = $this->createStudent();

        $expediente = Expediente::factory()->create([
            'no_control' => 'NC-0003',
            'paciente' => 'Alumno Existente',
            'apertura' => now()->subDay(),
            'carrera' => $student->carrera,
            'turno' => $student->turno,
            'creado_por' => $student->id,
            'estado' => 'abierto',
            'plan_accion' => 'Plan previo',
            'diagnostico' => 'Diagnóstico previo',
            'observaciones_relevantes' => 'Observaciones previas',
        ]);

        Schema::table('expedientes', function (Blueprint $table): void {
            if (Schema::hasColumn('expedientes', 'plan_accion')) {
                $table->dropColumn('plan_accion');
            }
        });

        $payload = [
            'no_control' => $expediente->no_control,
            'paciente' => 'Alumno Actualizado',
            'apertura' => now()->format('Y-m-d'),
            'carrera' => $student->carrera,
            'turno' => $student->turno,
            'plan_accion' => 'Nuevo plan',
        ];

        $response = $this->from(route('expedientes.edit', $expediente))
            ->actingAs($student)
            ->put(route('expedientes.update', $expediente), $payload);

        $response->assertRedirect(route('expedientes.edit', $expediente));
        $response->assertSessionHasErrors([
            'expediente' => __('expedientes.messages.student_save_error'),
        ]);
    }

    private function createStudent(): User
    {
        $student = User::factory()->create([
            'carrera' => CatalogoCarrera::query()->first()->nombre,
            'turno' => CatalogoTurno::query()->first()->nombre,
        ]);

        $student->assignRole('alumno');

        return $student;
    }
}
