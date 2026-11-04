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

    public function test_admin_creates_clinical_form_and_assigned_facilitator_can_update_existing_expediente(): void
    {
        Schema::dropIfExists('timeline_eventos');

        [$carrera, $turno] = $this->createCatalogoOptions();

        $tutor = User::factory()->create();
        $coordinador = User::factory()->create();

        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');
        $admin = $this->createAdmin();

        $createPayload = $this->representativePayload($carrera, $turno, $tutor->id, $coordinador->id);
        $createPayload['facilitador_id'] = $alumno->id;

        $storeResponse = $this->actingAs($admin)->post(route('expedientes.store'), $createPayload);
        $storeStatus = $storeResponse->getStatusCode();

        $this->assertSame(302, $storeStatus, 'La creación del expediente debe redirigir correctamente.');
        $this->assertTrue($storeResponse->isRedirect(), 'La creación del expediente debería redirigir.');

        $creado = Expediente::where('no_control', $createPayload['no_control'])->first();
        $this->assertNotNull($creado, 'El expediente del alumno no se creó en la base de datos.');

        $storeResponse->assertRedirect(route('expedientes.show', $creado));
        $storeResponse->assertSessionHas('status', __('expedientes.messages.store_success'));

        $this->actingAs($alumno)
            ->get(route('expedientes.index'))
            ->assertOk()
            ->assertSee($createPayload['no_control'])
            ->assertSee($createPayload['paciente']);

        $this->assertSame($admin->id, $creado->creado_por);
        $this->assertSame($alumno->id, $creado->facilitador_id);
        $this->assertTrue($creado->antecedentes_familiares['diabetes_mellitus']['madre']);
        $this->assertTrue($creado->antecedentes_familiares['hipertension_arterial']['padre']);
        $this->assertSame(
            $createPayload['antecedentes_personales_patologicos']['asma']['fecha'],
            $creado->antecedentes_personales_patologicos['asma']['fecha']
        );
        $this->assertTrue($creado->antecedentes_personales_patologicos['asma']['padece']);
        $this->assertSame(
            $createPayload['aparatos_sistemas']['digestivo'],
            $creado->aparatos_sistemas['digestivo']
        );
        $this->assertArrayNotHasKey('nervioso', $creado->aparatos_sistemas);
        $this->assertSame(
            $createPayload['plan_accion'],
            $creado->plan_accion
        );
        $this->assertSame('Diagnóstico integral ajustado a la valoración inicial.', $creado->diagnostico);
        $this->assertSame('Observaciones clínicas adicionales proporcionadas por el alumno.', $creado->observaciones_relevantes);

        $expediente = Expediente::factory()->create([
            'no_control' => 'AL-2024-0002',
            'paciente' => 'Alumno Existente',
            'apertura' => Carbon::now()->subMonth(),
            'carrera' => $carrera->nombre,
            'turno' => $turno->nombre,
            'tutor_id' => $tutor->id,
            'coordinador_id' => $coordinador->id,
            'creado_por' => $admin->id,
            'facilitador_id' => $alumno->id,
            'estado' => 'abierto',
        ]);

        $nuevoTutor = User::factory()->create();

        $this->actingAs($alumno)->get(route('expedientes.edit', $expediente))->assertOk();

        $updatePayload = $this->representativePayload($carrera, $turno, $nuevoTutor->id, $coordinador->id);
        $updatePayload['no_control'] = $expediente->no_control;
        $updatePayload['paciente'] = 'Alumno Actualizado';
        $updatePayload['plan_accion'] = 'Plan de acción actualizado con metas claras.';
        $updatePayload['diagnostico'] = 'Diagnóstico actualizado con seguimiento interdisciplinario.';
        $updatePayload['observaciones_relevantes'] = 'Observaciones relevantes revisadas durante tutoría.';
        $updatePayload['aparatos_sistemas']['digestivo'] = 'Historia psicosocial actualizada por el facilitador asignado.';

        $updateResponse = $this->actingAs($alumno)->put(route('expedientes.update', $expediente), $updatePayload);
        $updateStatus = $updateResponse->getStatusCode();

        $this->assertSame(302, $updateStatus, 'La actualización del expediente debe redirigir correctamente.');
        $this->assertTrue($updateResponse->isRedirect(), 'La actualización del expediente debería redirigir.');

        $expediente->refresh();

        $this->assertSame('Alumno Actualizado', $expediente->paciente);
        $this->assertSame($tutor->id, $expediente->tutor_id);
        $this->assertSame($admin->id, $expediente->creado_por);
        $this->assertSame($alumno->id, $expediente->facilitador_id);
        $this->assertSame(
            $updatePayload['antecedentes_observaciones'],
            $expediente->antecedentes_observaciones
        );
        $this->assertSame(
            $updatePayload['antecedentes_personales_patologicos']['asma']['padece'] === '1',
            $expediente->antecedentes_personales_patologicos['asma']['padece']
        );
        $this->assertSame(
            $updatePayload['aparatos_sistemas']['digestivo'],
            $expediente->aparatos_sistemas['digestivo']
        );
        $this->assertArrayNotHasKey('nervioso', $expediente->aparatos_sistemas);
        $this->assertSame(
            $updatePayload['plan_accion'],
            $expediente->plan_accion
        );
        $this->assertSame($updatePayload['diagnostico'], $expediente->diagnostico);
        $this->assertSame($updatePayload['observaciones_relevantes'], $expediente->observaciones_relevantes);
    }

    public function test_admin_can_create_with_boolean_strings_in_family_history(): void
    {
        Schema::dropIfExists('timeline_eventos');

        [$carrera, $turno] = $this->createCatalogoOptions();

        $tutor = User::factory()->create();
        $coordinador = User::factory()->create();

        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');
        $admin = $this->createAdmin();

        $payload = $this->representativePayload($carrera, $turno, $tutor->id, $coordinador->id);
        $payload['facilitador_id'] = $alumno->id;

        $payload['antecedentes_familiares'] = collect(Expediente::defaultFamilyHistory())
            ->map(function (array $members) {
                return collect($members)
                    ->map(fn (bool $value) => $value ? 'true' : 'false')
                    ->all();
            })
            ->all();

        $payload['antecedentes_familiares']['diabetes_mellitus']['madre'] = 'true';
        $payload['antecedentes_familiares']['hipertension_arterial']['padre'] = 'true';

        $response = $this->actingAs($admin)->post(route('expedientes.store'), $payload);

        $response->assertSessionDoesntHaveErrors();

        $expediente = Expediente::where('no_control', $payload['no_control'])->firstOrFail();

        $this->assertTrue($expediente->antecedentes_familiares['diabetes_mellitus']['madre']);
        $this->assertTrue($expediente->antecedentes_familiares['hipertension_arterial']['padre']);
        $this->assertFalse($expediente->antecedentes_familiares['diabetes_mellitus']['padre']);
    }

    public function test_admin_can_create_with_all_family_history_checkboxes_off(): void
    {
        Schema::dropIfExists('timeline_eventos');

        [$carrera, $turno] = $this->createCatalogoOptions();

        $tutor = User::factory()->create();
        $coordinador = User::factory()->create();

        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');
        $admin = $this->createAdmin();

        $payload = $this->representativePayload($carrera, $turno, $tutor->id, $coordinador->id);
        $payload['facilitador_id'] = $alumno->id;

        $payload['antecedentes_familiares'] = collect(Expediente::defaultFamilyHistory())
            ->map(fn (array $members) => collect($members)->map(fn () => '0')->all())
            ->all();

        $response = $this->actingAs($admin)->post(route('expedientes.store'), $payload);

        $response->assertSessionDoesntHaveErrors();

        $expediente = Expediente::where('no_control', $payload['no_control'])->firstOrFail();

        $this->assertFalse($expediente->antecedentes_familiares['diabetes_mellitus']['madre']);
        $this->assertFalse($expediente->antecedentes_familiares['hipertension_arterial']['padre']);
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create(['approved_at' => now()]);
        $admin->assignRole('admin');

        return $admin;
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
            'diagnostico' => '  Diagnóstico integral ajustado a la valoración inicial.  ',
            'observaciones_relevantes' => '  Observaciones clínicas adicionales proporcionadas por el alumno.  ',
        ];
    }
}
