<?php

namespace Tests\Feature\Expedientes;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteAlumnoFormSubmissionTest extends TestCase
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

    public function test_alumno_form_submissions_to_store_and_update_return_server_error(): void
    {
        Schema::dropIfExists('timeline_eventos');

        [$carrera, $turno] = $this->createCatalogoOptions();

        $tutor = User::factory()->create();
        $coordinador = User::factory()->create();

        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');

        $createPayload = $this->representativePayload($carrera, $turno, $tutor->id, $coordinador->id);

        $storeResponse = $this->actingAs($alumno)->post(route('expedientes.store'), $createPayload);
        $storeStatus = $storeResponse->getStatusCode();

        $this->assertSame(302, $storeStatus, 'La creación del expediente debe redirigir correctamente.');
        $this->assertTrue($storeResponse->isRedirect(), 'La creación del expediente debería redirigir.');

        $creado = Expediente::where('no_control', $createPayload['no_control'])->first();
        $this->assertNotNull($creado, 'El expediente del alumno no se creó en la base de datos.');
        $this->assertSame($alumno->id, $creado->creado_por);
        $this->assertTrue($creado->antecedentes_familiares['diabetes_mellitus']['madre']);
        $this->assertTrue($creado->antecedentes_familiares['hipertension_arterial']['padre']);
        $this->assertSame(
            $createPayload['antecedentes_personales_patologicos']['asma']['fecha'],
            $creado->antecedentes_personales_patologicos['asma']['fecha']
        );
        $this->assertTrue($creado->antecedentes_personales_patologicos['asma']['padece']);
        $this->assertSame(
            $createPayload['aparatos_sistemas']['nervioso'],
            $creado->aparatos_sistemas['nervioso']
        );
        $this->assertSame(
            $createPayload['plan_accion'],
            $creado->plan_accion
        );

        $expediente = Expediente::factory()->create([
            'no_control' => 'AL-2024-0002',
            'paciente' => 'Alumno Existente',
            'apertura' => Carbon::now()->subMonth(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'tutor_id' => $tutor->id,
            'coordinador_id' => $coordinador->id,
            'creado_por' => $alumno->id,
            'estado' => 'abierto',
        ]);

        $nuevoTutor = User::factory()->create();

        $updatePayload = $this->representativePayload($carrera, $turno, $nuevoTutor->id, $coordinador->id);
        $updatePayload['no_control'] = $expediente->no_control;
        $updatePayload['paciente'] = 'Alumno Actualizado';
        $updatePayload['plan_accion'] = 'Plan de acción actualizado con metas claras.';

        $updateResponse = $this->actingAs($alumno)->put(route('expedientes.update', $expediente), $updatePayload);
        $updateStatus = $updateResponse->getStatusCode();

        $this->assertSame(302, $updateStatus, 'La actualización del expediente debe redirigir correctamente.');
        $this->assertTrue($updateResponse->isRedirect(), 'La actualización del expediente debería redirigir.');

        $expediente->refresh();

        $this->assertSame('Alumno Actualizado', $expediente->paciente);
        $this->assertSame($nuevoTutor->id, $expediente->tutor_id);
        $this->assertSame(
            $updatePayload['antecedentes_observaciones'],
            $expediente->antecedentes_observaciones
        );
        $this->assertSame(
            $updatePayload['antecedentes_personales_patologicos']['asma']['padece'] === '1',
            $expediente->antecedentes_personales_patologicos['asma']['padece']
        );
        $this->assertSame(
            $updatePayload['aparatos_sistemas']['nervioso'],
            $expediente->aparatos_sistemas['nervioso']
        );
        $this->assertSame(
            $updatePayload['plan_accion'],
            $expediente->plan_accion
        );
    }

    private function createCatalogoOptions(): array
    {
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

        return [$carrera, $turno];
    }

    private function representativePayload(CatalogoCarrera $carrera, CatalogoTurno $turno, int $tutorId, int $coordinadorId): array
    {
        return [
            'no_control' => 'AL-2024-0001',
            'paciente' => 'Alumno de Prueba',
            'apertura' => Carbon::now()->toDateString(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'tutor_id' => $tutorId,
            'coordinador_id' => $coordinadorId,
            'antecedentes_familiares' => [
                'diabetes_mellitus' => [
                    'madre' => '1',
                    'padre' => '0',
                    'hermanos' => '0',
                ],
                'hipertension_arterial' => [
                    'madre' => '0',
                    'padre' => '1',
                ],
            ],
            'antecedentes_observaciones' => 'Observaciones de antecedentes familiares registradas por el alumno.',
            'antecedentes_personales_patologicos' => [
                'asma' => [
                    'padece' => '1',
                    'fecha' => Carbon::now()->subYears(2)->toDateString(),
                ],
                'cancer' => [
                    'padece' => '0',
                    'fecha' => '',
                ],
            ],
            'antecedentes_personales_observaciones' => 'Observaciones de antecedentes personales proporcionadas por el alumno.',
            'antecedente_padecimiento_actual' => 'Descripción del padecimiento actual reportado en el formulario.',
            'aparatos_sistemas' => [
                'digestivo' => 'Sin alteraciones digestivas reportadas.',
                'nervioso' => 'Refiere episodios de estrés académico.',
            ],
            'plan_accion' => 'Plan de acción colaborativo con el tutor.',
        ];
    }
}
