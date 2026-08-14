<?php

namespace Tests\Feature\Expedientes;

use App\Models\Anexo;
use App\Models\Comentario;
use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteVisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
    }

    public function test_each_profile_only_queries_its_assigned_expedientes(): void
    {
        $facilitador = $this->userWithRole('alumno');
        $docente = $this->userWithRole('docente');
        $coordinador = $this->userWithRole('coordinador');
        $admin = $this->userWithRole('admin');
        $paps = $this->userWithRole('paps');
        $paps->update(['approved_at' => now()]);
        $neutral = User::factory()->create();

        $facilitado = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
            'tutor_id' => $neutral->id,
            'coordinador_id' => $neutral->id,
        ]);
        $tutorado = Expediente::factory()->create([
            'creado_por' => $neutral->id,
            'tutor_id' => $docente->id,
            'coordinador_id' => $neutral->id,
        ]);
        $coordinado = Expediente::factory()->create([
            'creado_por' => $neutral->id,
            'tutor_id' => $neutral->id,
            'coordinador_id' => $coordinador->id,
        ]);
        $ajeno = Expediente::factory()->create([
            'creado_por' => $neutral->id,
            'tutor_id' => $neutral->id,
            'coordinador_id' => $neutral->id,
        ]);

        $this->assertVisibleIds($facilitador, [$facilitado->id]);
        $this->assertVisibleIds($docente, [$tutorado->id]);
        $this->assertVisibleIds($coordinador, [$coordinado->id]);
        $this->assertVisibleIds($admin, [$facilitado->id, $tutorado->id, $coordinado->id, $ajeno->id]);
        $this->assertVisibleIds($paps, [$facilitado->id, $tutorado->id, $coordinado->id, $ajeno->id]);
    }

    public function test_coordinator_can_open_and_edit_only_assigned_expedientes(): void
    {
        $coordinador = $this->userWithRole('coordinador');
        $neutral = User::factory()->create();

        $asignado = Expediente::factory()->create([
            'creado_por' => $neutral->id,
            'coordinador_id' => $coordinador->id,
        ]);
        $ajeno = Expediente::factory()->create([
            'creado_por' => $neutral->id,
            'coordinador_id' => $neutral->id,
        ]);

        $this->assertTrue(Gate::forUser($coordinador)->allows('view', $asignado));
        $this->assertTrue(Gate::forUser($coordinador)->allows('update', $asignado));
        $this->assertFalse(Gate::forUser($coordinador)->allows('view', $ajeno));
        $this->assertFalse(Gate::forUser($coordinador)->allows('update', $ajeno));

        $this->actingAs($coordinador)
            ->get(route('expedientes.show', $ajeno))
            ->assertForbidden();
        $this->actingAs($coordinador)
            ->get(route('expedientes.edit', $ajeno))
            ->assertForbidden();
    }

    public function test_facilitator_edit_only_receives_current_assignment_options(): void
    {
        $facilitador = $this->userWithRole('alumno');
        $assignedTutor = $this->userWithRole('docente');
        $foreignTutor = $this->userWithRole('docente');
        $assignedCoordinator = $this->userWithRole('coordinador');
        $foreignCoordinator = $this->userWithRole('coordinador');

        $expediente = Expediente::factory()->create([
            'estado' => 'abierto',
            'creado_por' => $facilitador->id,
            'tutor_id' => $assignedTutor->id,
            'coordinador_id' => $assignedCoordinator->id,
        ]);

        $response = $this->actingAs($facilitador)->get(route('expedientes.edit', $expediente));

        $response->assertOk();
        $response->assertViewHas('tutores', fn ($users) => $users->pluck('id')->all() === [$assignedTutor->id]);
        $response->assertViewHas('coordinadores', fn ($users) => $users->pluck('id')->all() === [$assignedCoordinator->id]);
        $response->assertDontSeeText($foreignTutor->name);
        $response->assertDontSeeText($foreignCoordinator->name);
    }

    public function test_multiple_restricted_roles_combine_assignments_without_global_access(): void
    {
        $user = User::factory()->create();
        $user->assignRole(['alumno', 'coordinador']);
        $neutral = User::factory()->create();

        $creado = Expediente::factory()->create([
            'creado_por' => $user->id,
            'coordinador_id' => $neutral->id,
        ]);
        $coordinado = Expediente::factory()->create([
            'creado_por' => $neutral->id,
            'coordinador_id' => $user->id,
        ]);
        Expediente::factory()->create([
            'creado_por' => $neutral->id,
            'coordinador_id' => $neutral->id,
        ]);

        $this->assertVisibleIds($user, [$creado->id, $coordinado->id]);
    }

    public function test_facilitator_coordinator_cannot_use_coordinator_actions_when_only_assigned_as_creator(): void
    {
        $user = User::factory()->create();
        $user->assignRole(['alumno', 'coordinador']);

        $otherUser = User::factory()->create();

        $expediente = Expediente::factory()->create([
            'estado' => 'abierto',
            'creado_por' => $user->id,
            'tutor_id' => $otherUser->id,
            'coordinador_id' => $otherUser->id,
        ]);
        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $user->id,
            'status_revision' => 'validada',
            'validada_por' => $otherUser->id,
        ]);
        $anexo = Anexo::factory()->create([
            'expediente_id' => $expediente->id,
            'subido_por' => $otherUser->id,
        ]);
        $comentario = Comentario::factory()->create([
            'user_id' => $otherUser->id,
            'comentable_type' => Expediente::class,
            'comentable_id' => $expediente->id,
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('view', $expediente));
        $this->assertVisibleIds($user, [$expediente->id]);
        $this->assertFalse(Gate::forUser($user)->allows('changeState', $expediente));
        $this->assertFalse(Gate::forUser($user)->allows('validate', $sesion));
        $this->assertFalse(Gate::forUser($user)->allows('observe', $sesion));
        $this->assertFalse(Gate::forUser($user)->allows('update', $sesion));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $anexo));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $comentario));

        $this->actingAs($user);

        $this->post(route('expedientes.change-state', $expediente), [
            'estado' => 'revision',
        ])->assertForbidden();
        $this->post(route('expedientes.sesiones.validate', [$expediente, $sesion]))
            ->assertForbidden();
        $this->post(route('expedientes.sesiones.observe', [$expediente, $sesion]), [
            'observaciones' => 'Intento sin asignación de coordinación.',
        ])->assertForbidden();
        $this->get(route('expedientes.sesiones.edit', [$expediente, $sesion]))
            ->assertForbidden();
        $this->delete(route('expedientes.anexos.destroy', [$expediente, $anexo]))
            ->assertForbidden();
        $this->deleteJson(route('api.comentarios.destroy', $comentario))
            ->assertForbidden();

        $this->assertSame('abierto', $expediente->fresh()->estado);
        $this->assertSame('validada', $sesion->fresh()->status_revision);
        $this->assertDatabaseHas('anexos', ['id' => $anexo->id]);
        $this->assertDatabaseHas('comentarios', ['id' => $comentario->id]);
    }

    public function test_restricted_multi_role_creator_cannot_self_assign_privileged_relationships(): void
    {
        foreach (['coordinador', 'docente'] as $privilegedRole) {
            $user = User::factory()->create();
            $user->assignRole(['alumno', $privilegedRole]);

            $originalTutor = User::factory()->create();
            $originalCoordinator = User::factory()->create();

            $expediente = Expediente::factory()->create([
                'estado' => 'abierto',
                'creado_por' => $user->id,
                'tutor_id' => $originalTutor->id,
                'coordinador_id' => $originalCoordinator->id,
            ]);
            $session = Sesion::factory()->create([
                'expediente_id' => $expediente->id,
                'realizada_por' => $user->id,
                'status_revision' => 'pendiente',
                'validada_por' => null,
            ]);

            $this->actingAs($user)
                ->put(route('expedientes.update', $expediente), [
                    'tutor_id' => $user->id,
                    'coordinador_id' => $user->id,
                ])
                ->assertRedirect();

            $expediente->refresh();

            $this->assertSame($originalTutor->id, $expediente->tutor_id);
            $this->assertSame($originalCoordinator->id, $expediente->coordinador_id);
            $this->assertFalse($user->isTutorOf($expediente));
            $this->assertFalse($user->isCoordinatorOf($expediente));

            $this->actingAs($user)
                ->post(route('expedientes.change-state', $expediente), [
                    'estado' => 'revision',
                ])
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('expedientes.sesiones.validate', [$expediente, $session]))
                ->assertForbidden();

            $this->assertSame('abierto', $expediente->fresh()->estado);
            $this->assertSame('pendiente', $session->fresh()->status_revision);
        }
    }

    public function test_facilitator_direct_update_cannot_change_protected_workflow_fields(): void
    {
        $facilitador = $this->userWithRole('alumno');

        $originalSummary = Expediente::defaultClinicalSummary();
        $originalSummary['cubiculo'] = 1;

        $expediente = Expediente::factory()->create([
            'estado' => 'abierto',
            'creado_por' => $facilitador->id,
            'resumen_clinico' => $originalSummary,
        ]);
        $urgency = $expediente->registroUrgencia()->create([
            'nivel_riesgo' => 'bajo',
            'motivo' => 'Motivo original',
            'canalizacion_inmediata' => false,
            'observaciones' => 'Observaciones originales',
        ]);

        $this->actingAs($facilitador)
            ->put(route('expedientes.update', $expediente), [
                'estado' => 'cerrado',
                'resumen_clinico' => [
                    'cubiculo' => 2,
                    'nota_alta' => 'Contenido permitido del resumen',
                ],
                'registro_urgencia' => [
                    'nivel_riesgo' => 'alto',
                    'motivo' => 'Intento de alteración',
                    'canalizacion_inmediata' => true,
                    'observaciones' => 'Intento de alteración',
                ],
            ])
            ->assertRedirect();

        $expediente->refresh();
        $urgency->refresh();

        $this->assertSame('abierto', $expediente->estado);
        $this->assertSame(1, data_get($expediente->resumen_clinico, 'cubiculo'));
        $this->assertSame('bajo', $urgency->nivel_riesgo);
        $this->assertSame('Motivo original', $urgency->motivo);
        $this->assertFalse($urgency->canalizacion_inmediata);
        $this->assertSame('Observaciones originales', $urgency->observaciones);
    }

    public function test_reassignment_revokes_access_to_nested_records_and_own_comments(): void
    {
        $facilitador = $this->userWithRole('alumno');
        $nuevoFacilitador = $this->userWithRole('alumno');

        $expediente = Expediente::factory()->create([
            'estado' => 'abierto',
            'creado_por' => $facilitador->id,
        ]);
        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $facilitador->id,
            'status_revision' => 'pendiente',
        ]);
        $anexo = Anexo::factory()->create([
            'expediente_id' => $expediente->id,
            'subido_por' => $facilitador->id,
        ]);
        $consentimiento = Consentimiento::factory()->create([
            'expediente_id' => $expediente->id,
            'subido_por' => $facilitador->id,
        ]);
        $comentario = Comentario::factory()->create([
            'user_id' => $facilitador->id,
            'comentable_type' => Expediente::class,
            'comentable_id' => $expediente->id,
        ]);

        $this->assertTrue(Gate::forUser($facilitador)->allows('view', $sesion));
        $this->assertTrue(Gate::forUser($facilitador)->allows('view', $anexo));
        $this->assertTrue(Gate::forUser($facilitador)->allows('view', $consentimiento));
        $this->assertTrue(Gate::forUser($facilitador)->allows('update', $comentario));

        $expediente->update(['creado_por' => $nuevoFacilitador->id]);
        $sesion->unsetRelation('expediente');
        $anexo->unsetRelation('expediente');
        $consentimiento->unsetRelation('expediente');
        $comentario->unsetRelation('comentable');

        $this->assertFalse(Gate::forUser($facilitador)->allows('view', $sesion));
        $this->assertFalse(Gate::forUser($facilitador)->allows('update', $sesion));
        $this->assertFalse(Gate::forUser($facilitador)->allows('delete', $sesion));
        $this->assertFalse(Gate::forUser($facilitador)->allows('view', $anexo));
        $this->assertFalse(Gate::forUser($facilitador)->allows('delete', $anexo));
        $this->assertFalse(Gate::forUser($facilitador)->allows('view', $consentimiento));
        $this->assertFalse(Gate::forUser($facilitador)->allows('update', $consentimiento));
        $this->assertFalse(Gate::forUser($facilitador)->allows('update', $comentario));
        $this->assertFalse(Gate::forUser($facilitador)->allows('delete', $comentario));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<int, int>  $expected
     */
    private function assertVisibleIds(User $user, array $expected): void
    {
        $actual = Expediente::query()
            ->visibleTo($user)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        sort($expected);

        $this->assertSame($expected, $actual);
    }
}
