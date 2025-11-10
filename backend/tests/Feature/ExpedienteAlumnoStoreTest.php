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
            'dsm_tr' => 'F32.0',
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
            Schema::hasColumn('expedientes', 'dsm_tr') ? 'dsm_tr' : null,
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
