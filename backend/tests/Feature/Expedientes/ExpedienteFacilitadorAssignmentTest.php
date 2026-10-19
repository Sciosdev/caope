<?php

namespace Tests\Feature\Expedientes;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoEstrategia;
use App\Models\CatalogoTurno;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteFacilitadorAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        CatalogoCarrera::query()->create(['nombre' => 'Psicología', 'activo' => true]);
        CatalogoTurno::query()->create(['nombre' => 'Matutino', 'activo' => true]);
        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();
    }

    #[DataProvider('globalManagerRoles')]
    public function test_manager_can_create_an_expediente_for_another_facilitator(string $role): void
    {
        $manager = $this->userWithRole($role);
        $facilitador = $this->userWithRole('alumno');
        $ajeno = $this->userWithRole('alumno');

        $this->actingAs($manager)
            ->get(route('expedientes.create'))
            ->assertOk()
            ->assertSee('name="facilitador_id"', false);

        $this->actingAs($manager)
            ->post(route('expedientes.store'), $this->createPayload([
                'facilitador_id' => $facilitador->id,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $expediente = Expediente::query()->firstOrFail();

        $this->assertSame($manager->id, $expediente->creado_por);
        $this->assertSame($facilitador->id, (int) $expediente->facilitador_id);
        $this->assertTrue($facilitador->isFacilitatorOf($expediente));
        $this->assertFalse($ajeno->isFacilitatorOf($expediente));
        $this->assertVisibleIds($facilitador, [$expediente->id]);
        $this->assertVisibleIds($ajeno, []);

        $this->actingAs($facilitador)
            ->get(route('expedientes.index'))
            ->assertOk()
            ->assertSee($expediente->no_control);
        $this->actingAs($facilitador)
            ->get(route('expedientes.show', $expediente))
            ->assertOk();
        $this->actingAs($ajeno)
            ->get(route('expedientes.show', $expediente))
            ->assertForbidden();

        $this->assertTrue(Gate::forUser($facilitador)->allows('create', [Sesion::class, $expediente]));
    }

    public function test_facilitator_cannot_assign_a_new_expediente_to_another_user(): void
    {
        $facilitador = $this->userWithRole('alumno');
        $ajeno = $this->userWithRole('alumno');

        $this->actingAs($facilitador)
            ->post(route('expedientes.store'), $this->createPayload([
                'facilitador_id' => $ajeno->id,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $expediente = Expediente::query()->firstOrFail();

        $this->assertSame($facilitador->id, $expediente->creado_por);
        $this->assertTrue($facilitador->isFacilitatorOf($expediente));
        $this->assertFalse($ajeno->isFacilitatorOf($expediente));
        $this->assertVisibleIds($facilitador, [$expediente->id]);
        $this->assertVisibleIds($ajeno, []);
    }

    #[DataProvider('assignmentManagerRoles')]
    public function test_manager_can_reassign_facilitator_without_changing_authorship(string $role): void
    {
        $manager = $this->userWithRole($role);
        $original = $this->userWithRole('alumno');
        $nuevo = $this->userWithRole('alumno');
        $estrategia = CatalogoEstrategia::query()->create(['nombre' => 'Estrategia de asignación', 'activo' => true]);
        if ($role === 'coordinador') {
            $manager->estrategiasCoordinadas()->attach($estrategia);
        }
        $expediente = $this->expediente([
            'estrategia_id' => $estrategia->id,
            'creado_por' => $original->id,
            'facilitador_id' => $original->id,
            'coordinador_id' => $role === 'coordinador' ? $manager->id : null,
        ]);

        $this->actingAs($manager)
            ->get(route('expedientes.edit', $expediente))
            ->assertOk()
            ->assertSee('name="facilitador_id"', false);
        $this->actingAs($manager)
            ->put(route('expedientes.update', $expediente), ['facilitador_id' => $nuevo->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $expediente->refresh();

        $this->assertSame($original->id, $expediente->creado_por);
        $this->assertSame($nuevo->id, (int) $expediente->facilitador_id);
        $this->assertFalse(Gate::forUser($original)->allows('view', $expediente));
        $this->assertTrue(Gate::forUser($nuevo)->allows('view', $expediente));
        $this->assertVisibleIds($original, []);
        $this->assertVisibleIds($nuevo, [$expediente->id]);
        $this->actingAs($original)
            ->get(route('expedientes.show', $expediente))
            ->assertForbidden();
    }

    #[DataProvider('restrictedAssignmentRoles')]
    public function test_restricted_user_cannot_reassign_facilitator(string $role): void
    {
        $facilitador = $this->userWithRole('alumno');
        $docente = $this->userWithRole('docente');
        $nuevo = $this->userWithRole('alumno');
        $actor = $role === 'alumno' ? $facilitador : $docente;
        $expediente = $this->expediente([
            'creado_por' => $facilitador->id,
            'facilitador_id' => $facilitador->id,
            'tutor_id' => $docente->id,
        ]);

        $this->actingAs($actor)
            ->put(route('expedientes.update', $expediente), ['facilitador_id' => $nuevo->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame($facilitador->id, (int) $expediente->fresh()->facilitador_id);
        $this->assertVisibleIds($nuevo, []);
    }

    public function test_unassigned_coordinator_cannot_assign_another_expediente_to_self_or_a_facilitator(): void
    {
        $coordinador = $this->userWithRole('coordinador');
        $asignado = $this->userWithRole('coordinador');
        $facilitador = $this->userWithRole('alumno');
        $expediente = $this->expediente(['coordinador_id' => $asignado->id]);

        $this->actingAs($coordinador)
            ->put(route('expedientes.update', $expediente), [
                'facilitador_id' => $facilitador->id,
                'coordinador_id' => $coordinador->id,
            ])
            ->assertForbidden();

        $this->assertNull($expediente->fresh()->facilitador_id);
        $this->assertSame($asignado->id, $expediente->fresh()->coordinador_id);
    }

    public function test_assignment_rejects_users_without_active_facilitator_role(): void
    {
        $admin = $this->userWithRole('admin');
        $original = $this->userWithRole('alumno');
        $docente = $this->userWithRole('docente');
        $inactivo = $this->userWithRole('alumno');
        $inactivo->forceFill(['is_active' => false])->save();
        $expediente = $this->expediente(['facilitador_id' => $original->id]);

        foreach ([$docente, $inactivo] as $invalido) {
            $this->actingAs($admin)
                ->putJson(route('expedientes.update', $expediente), ['facilitador_id' => $invalido->id])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('facilitador_id');

            $this->assertSame($original->id, (int) $expediente->fresh()->facilitador_id);
        }
    }

    #[DataProvider('existingFacilitatorProfiles')]
    public function test_edit_preserves_current_facilitator_when_inactive_or_from_a_legacy_role(string $role, bool $active): void
    {
        $admin = $this->userWithRole('admin');
        $actual = $this->userWithRole($role);
        $actual->forceFill(['is_active' => $active])->save();
        $nuevo = $this->userWithRole('alumno');
        $otroInactivo = $this->userWithRole('alumno');
        $otroInactivo->forceFill(['is_active' => false])->save();
        $expediente = $this->expediente([
            'creado_por' => $actual->id,
            'facilitador_id' => $actual->id,
        ]);

        $this->actingAs($admin)
            ->get(route('expedientes.edit', $expediente))
            ->assertOk()
            ->assertViewHas('facilitadores', fn ($users) => $users->contains('id', $actual->id)
                && $users->contains('id', $nuevo->id)
                && ! $users->contains('id', $otroInactivo->id))
            ->assertSee(sprintf('<option value="%d" selected>', $actual->id), false);

        $this->actingAs($admin)
            ->put(route('expedientes.update', $expediente), ['paciente' => 'Nombre actualizado'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame($actual->id, (int) $expediente->fresh()->facilitador_id);
        $this->assertSame($actual->id, $expediente->fresh()->creado_por);

        $this->actingAs($admin)
            ->put(route('expedientes.update', $expediente), [
                'paciente' => 'Otra actualización',
                'facilitador_id' => $actual->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame($actual->id, (int) $expediente->fresh()->facilitador_id);
        $this->assertSame('Otra actualización', $expediente->fresh()->paciente);

        $this->actingAs($admin)
            ->putJson(route('expedientes.update', $expediente), ['facilitador_id' => $otroInactivo->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('facilitador_id');

        $this->assertSame($actual->id, (int) $expediente->fresh()->facilitador_id);
    }

    public function test_legacy_creation_defaults_facilitator_to_creator_until_reassigned(): void
    {
        $creador = $this->userWithRole('alumno');
        $asignado = $this->userWithRole('alumno');
        $legacy = Expediente::factory()->create([
            'estado' => 'abierto',
            'creado_por' => $creador->id,
            'tutor_id' => null,
            'coordinador_id' => null,
        ]);
        $reasignado = $this->expediente([
            'creado_por' => $creador->id,
            'facilitador_id' => $asignado->id,
        ]);

        $this->assertSame($creador->id, (int) $legacy->facilitador_id);
        $this->assertTrue($creador->isFacilitatorOf($legacy));
        $this->assertFalse($creador->isFacilitatorOf($reasignado));
        $this->assertTrue($asignado->isFacilitatorOf($reasignado));
        $this->assertVisibleIds($creador, [$legacy->id]);
        $this->assertVisibleIds($asignado, [$reasignado->id]);
    }

    public function test_migration_backfills_existing_expedientes_from_their_creator(): void
    {
        $creador = $this->userWithRole('alumno');
        $admin = $this->userWithRole('admin');
        $legacy = $this->expediente(['creado_por' => $creador->id]);
        $capturadoPorAdmin = $this->expediente([
            'creado_por' => $admin->id,
            'resumen_clinico' => array_merge(Expediente::defaultClinicalSummary(), ['facilitador' => $creador->name]),
        ]);
        $migration = require database_path('migrations/2026_10_19_000001_add_facilitador_id_to_expedientes_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('expedientes', 'facilitador_id'));
        $migration->up();

        $this->assertSame($creador->id, (int) $legacy->fresh()->facilitador_id);
        $this->assertSame($admin->id, (int) $capturadoPorAdmin->fresh()->facilitador_id);
        $this->assertSame($creador->id, $legacy->fresh()->creado_por);
        $this->assertSame($admin->id, $capturadoPorAdmin->fresh()->creado_por);
        $this->assertVisibleIds($creador, [$legacy->id]);
    }

    public function test_eager_loading_keeps_authors_and_assigned_facilitators_separate(): void
    {
        $admin = $this->userWithRole('admin');
        $primero = $this->userWithRole('alumno');
        $segundo = $this->userWithRole('alumno');
        $capturadoPorAdmin = $this->expediente([
            'creado_por' => $admin->id,
            'facilitador_id' => $primero->id,
        ]);
        $reasignado = $this->expediente([
            'creado_por' => $primero->id,
            'facilitador_id' => $segundo->id,
        ]);
        $sinAsignar = $this->expediente(['creado_por' => $primero->id]);

        $loaded = Expediente::query()->with(['creadoPor', 'facilitador'])->get()->keyBy('id');

        foreach ($loaded as $expediente) {
            $this->assertTrue($expediente->relationLoaded('facilitador'));
            $this->assertTrue($expediente->relationLoaded('creadoPor'));
        }

        $this->assertSame($admin->id, $loaded[$capturadoPorAdmin->id]->creadoPor->id);
        $this->assertSame($primero->id, $loaded[$capturadoPorAdmin->id]->facilitador->id);
        $this->assertSame($primero->id, $loaded[$reasignado->id]->creadoPor->id);
        $this->assertSame($segundo->id, $loaded[$reasignado->id]->facilitador->id);
        $this->assertSame($primero->id, $loaded[$sinAsignar->id]->creadoPor->id);
        $this->assertNull($loaded[$sinAsignar->id]->facilitador);
    }

    public function test_explicitly_clearing_assignment_does_not_restore_creator_access(): void
    {
        $admin = $this->userWithRole('admin');
        $creador = $this->userWithRole('alumno');
        $asignado = $this->userWithRole('alumno');
        $expediente = $this->expediente([
            'creado_por' => $creador->id,
            'facilitador_id' => $asignado->id,
        ]);

        $this->actingAs($admin)
            ->put(route('expedientes.update', $expediente), ['facilitador_id' => null])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $expediente->refresh();

        $this->assertNull($expediente->facilitador_id);
        $this->assertFalse($creador->isFacilitatorOf($expediente));
        $this->assertFalse($asignado->isFacilitatorOf($expediente));
        $this->assertVisibleIds($creador, []);
        $this->assertVisibleIds($asignado, []);
    }

    public function test_session_scope_and_policies_follow_assigned_facilitator_and_revoke_former_creator(): void
    {
        $creador = $this->userWithRole('alumno');
        $asignado = $this->userWithRole('alumno');
        $expediente = $this->expediente([
            'creado_por' => $creador->id,
            'facilitador_id' => $asignado->id,
        ]);
        $propia = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $asignado->id,
            'status_revision' => 'pendiente',
        ]);
        $anterior = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $creador->id,
            'status_revision' => 'pendiente',
        ]);

        $this->assertSame([$propia->id], Sesion::query()->visibleTo($asignado)->pluck('id')->all());
        $this->assertSame([], Sesion::query()->visibleTo($creador)->pluck('id')->all());
        $this->assertTrue(Gate::forUser($asignado)->allows('view', $propia));
        $this->assertTrue(Gate::forUser($asignado)->allows('update', $propia));
        $this->assertFalse(Gate::forUser($asignado)->allows('view', $anterior));
        $this->assertFalse(Gate::forUser($creador)->allows('view', $anterior));
        $this->assertFalse(Gate::forUser($creador)->allows('update', $anterior));
        $this->assertFalse(Gate::forUser($creador)->allows('create', [Sesion::class, $expediente]));
    }

    public static function globalManagerRoles(): array
    {
        return [['admin'], ['paps']];
    }

    public static function assignmentManagerRoles(): array
    {
        return [['admin'], ['paps'], ['coordinador']];
    }

    public static function restrictedAssignmentRoles(): array
    {
        return [['alumno'], ['docente']];
    }

    public static function existingFacilitatorProfiles(): array
    {
        return [
            'facilitador inactivo' => ['alumno', false],
            'autor admin anterior' => ['admin', true],
            'autor PAPS anterior' => ['paps', true],
        ];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['approved_at' => $role === 'paps' ? now() : null]);
        $user->assignRole($role);

        return $user;
    }

    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'no_control' => sprintf('CA-%s-8201', now()->format('Y')),
            'paciente' => 'Alumno para asignación',
            'apertura' => now()->toDateString(),
            'carrera' => 'Psicología',
            'turno' => 'Matutino',
        ], $overrides);
    }

    private function expediente(array $overrides = []): Expediente
    {
        return Expediente::factory()->create(array_merge([
            'estado' => 'abierto',
            'carrera' => 'Psicología',
            'turno' => 'Matutino',
            'facilitador_id' => null,
            'tutor_id' => null,
            'coordinador_id' => null,
        ], $overrides));
    }

    private function assertVisibleIds(User $user, array $expected): void
    {
        sort($expected);

        $this->assertSame($expected, Expediente::query()->visibleTo($user)->orderBy('id')->pluck('id')->all());
    }
}
