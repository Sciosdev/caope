<?php

namespace Tests\Feature\Admin;

use App\Models\CatalogoEstrategia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CoordinatorStrategyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'paps', 'coordinador', 'alumno'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_admin_can_create_a_coordinator_with_multiple_strategies(): void
    {
        $first = $this->strategy('Estrategia primera');
        $second = $this->strategy('Estrategia segunda');

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.users.store'), $this->creationPayload([
                'estrategia_ids' => [$first->id, $second->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'coordinador@example.com')->firstOrFail();
        $this->assertTrue($created->hasRole('coordinador'));
        $this->assertStrategies($created, [$first->id, $second->id]);
    }

    public function test_approved_paps_can_replace_a_coordinators_strategies_and_revoke_sessions(): void
    {
        $first = $this->strategy('Primera');
        $second = $this->strategy('Segunda');
        $coordinator = $this->userWithRole('coordinador');
        $coordinator->estrategiasCoordinadas()->attach($first);
        $rememberToken = $coordinator->remember_token;
        $this->createSession($coordinator);

        $this->actingAs($this->userWithRole('paps'))
            ->put(route('admin.users.update', $coordinator), $this->updatePayload($coordinator, [
                'estrategia_ids' => [$second->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertStrategies($coordinator, [$second->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'coordinator-browser']);
        $this->assertNotSame($rememberToken, $coordinator->fresh()->remember_token);
    }

    public function test_forms_include_active_and_current_inactive_strategies_without_assigning_on_read(): void
    {
        $active = $this->strategy('Estrategia disponible');
        $assignedInactive = $this->strategy('Estrategia anterior', false);
        $unassignedInactive = $this->strategy('Estrategia no disponible', false);
        $coordinator = $this->userWithRole('coordinador');
        $coordinator->estrategiasCoordinadas()->attach($assignedInactive);

        $this->actingAs($this->userWithRole('admin'));
        $this->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('name="estrategia_ids[]"', false)
            ->assertSee($active->nombre)
            ->assertDontSee($assignedInactive->nombre)
            ->assertDontSee($unassignedInactive->nombre);
        $this->get(route('admin.users.edit', $coordinator))
            ->assertOk()
            ->assertSee('name="estrategia_ids_present"', false)
            ->assertSee($active->nombre)
            ->assertSee($assignedInactive->nombre)
            ->assertDontSee($unassignedInactive->nombre);
        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee($assignedInactive->nombre);

        $this->assertStrategies($coordinator, [$assignedInactive->id]);
    }

    public function test_new_assignments_reject_inactive_and_missing_strategies(): void
    {
        $inactive = $this->strategy('Inactiva', false);
        $coordinator = $this->userWithRole('coordinador');
        $this->actingAs($this->userWithRole('admin'));

        foreach ([$inactive->id, 999999] as $id) {
            $this->post(route('admin.users.store'), $this->creationPayload([
                'estrategia_ids' => [$id],
            ]))->assertSessionHasErrors('estrategia_ids.0');

            $this->put(route('admin.users.update', $coordinator), $this->updatePayload($coordinator, [
                'estrategia_ids' => [$id],
            ]))->assertSessionHasErrors('estrategia_ids.0');
        }

        $this->assertDatabaseMissing('users', ['email' => 'coordinador@example.com']);
        $this->assertStrategies($coordinator, []);
    }

    public function test_an_existing_inactive_assignment_can_be_retained(): void
    {
        $inactive = $this->strategy('Inactiva', false);
        $coordinator = $this->userWithRole('coordinador');
        $coordinator->estrategiasCoordinadas()->attach($inactive);

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('admin.users.update', $coordinator), $this->updatePayload($coordinator, [
                'estrategia_ids' => [$inactive->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertStrategies($coordinator, [$inactive->id]);
    }

    public function test_omitting_strategy_fields_preserves_existing_coordinator_assignments(): void
    {
        $strategy = $this->strategy('Conservar');
        $coordinator = $this->userWithRole('coordinador');
        $coordinator->estrategiasCoordinadas()->attach($strategy);
        $rememberToken = $coordinator->remember_token;
        $this->createSession($coordinator);

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('admin.users.update', $coordinator), $this->updatePayload($coordinator))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertStrategies($coordinator, [$strategy->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'coordinator-browser']);
        $this->assertSame($rememberToken, $coordinator->fresh()->remember_token);
    }

    public function test_empty_html_selection_revokes_all_strategies_and_sessions(): void
    {
        $strategy = $this->strategy('Revocar');
        $coordinator = $this->userWithRole('coordinador');
        $coordinator->estrategiasCoordinadas()->attach($strategy);
        $rememberToken = $coordinator->remember_token;
        $this->createSession($coordinator);

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('admin.users.update', $coordinator), $this->updatePayload($coordinator, [
                'estrategia_ids_present' => '1',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertStrategies($coordinator, []);
        $this->assertTrue($coordinator->fresh()->hasRole('coordinador'));
        $this->assertDatabaseMissing('sessions', ['id' => 'coordinator-browser']);
        $this->assertNotSame($rememberToken, $coordinator->fresh()->remember_token);
    }

    public function test_explicit_empty_api_selection_revokes_all_strategies(): void
    {
        $strategy = $this->strategy('Revocar');
        $coordinator = $this->userWithRole('coordinador');
        $coordinator->estrategiasCoordinadas()->attach($strategy);

        $this->actingAs($this->userWithRole('admin'))
            ->putJson(route('admin.users.update', $coordinator), $this->updatePayload($coordinator, [
                'estrategia_ids' => [],
            ]))
            ->assertRedirect(route('admin.users.index'));

        $this->assertStrategies($coordinator, []);
    }

    public function test_unchanged_assignment_does_not_revoke_sessions(): void
    {
        $first = $this->strategy('Primera');
        $second = $this->strategy('Segunda');
        $coordinator = $this->userWithRole('coordinador');
        $coordinator->estrategiasCoordinadas()->attach([$first->id, $second->id]);
        $rememberToken = $coordinator->remember_token;
        $this->createSession($coordinator);

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('admin.users.update', $coordinator), $this->updatePayload($coordinator, [
                'estrategia_ids' => [(string) $second->id, (string) $first->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('sessions', ['id' => 'coordinator-browser']);
        $this->assertSame($rememberToken, $coordinator->fresh()->remember_token);
    }

    public function test_removing_the_coordinator_role_also_removes_its_strategy_assignments(): void
    {
        $strategy = $this->strategy('Eliminar con rol');
        $coordinator = $this->userWithRole('coordinador');
        $coordinator->estrategiasCoordinadas()->attach($strategy);

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('admin.users.update', $coordinator), $this->updatePayload($coordinator, [
                'roles' => ['alumno'],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertStrategies($coordinator, []);
        $this->assertFalse($coordinator->fresh()->hasRole('coordinador'));
    }

    public function test_strategy_payloads_cannot_assign_strategies_without_the_coordinator_role(): void
    {
        $strategy = $this->strategy('No conceder');
        $facilitator = $this->userWithRole('alumno');
        $this->actingAs($this->userWithRole('admin'));

        $this->post(route('admin.users.store'), $this->creationPayload([
            'roles' => ['alumno'],
            'estrategia_ids' => [$strategy->id],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'coordinador@example.com')->firstOrFail();
        $this->assertStrategies($created, []);

        $this->put(route('admin.users.update', $facilitator), $this->updatePayload($facilitator, [
            'roles' => ['alumno'],
            'estrategia_ids' => [$strategy->id],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $this->assertStrategies($facilitator, []);
    }

    public function test_coordinator_cannot_expand_own_or_other_users_assignments_through_admin_endpoints(): void
    {
        $strategy = $this->strategy('Protegida');
        $coordinator = $this->userWithRole('coordinador');
        $other = $this->userWithRole('coordinador');
        $this->actingAs($coordinator);

        foreach ([$coordinator, $other] as $target) {
            $this->putJson(route('admin.users.update', $target), $this->updatePayload($target, [
                'estrategia_ids' => [$strategy->id],
            ]))->assertForbidden();

            $this->assertStrategies($target, []);
        }

        $this->postJson(route('admin.users.store'), $this->creationPayload([
            'estrategia_ids' => [$strategy->id],
        ]))->assertForbidden();
    }

    public function test_forged_profile_fields_cannot_expand_coordinator_assignments(): void
    {
        $strategy = $this->strategy('Protegida');
        $coordinator = $this->userWithRole('coordinador');

        $this->actingAs($coordinator)
            ->patchJson(route('profile.update'), [
                'name' => $coordinator->name,
                'email' => $coordinator->email,
                'roles' => ['admin', 'coordinador'],
                'estrategia_ids' => [$strategy->id],
                'estrategia_ids_present' => true,
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertStrategies($coordinator, []);
        $this->assertFalse($coordinator->fresh()->hasRole('admin'));
    }

    public function test_admin_coordinator_cannot_assign_strategies_to_itself(): void
    {
        $strategy = $this->strategy('No autoasignar');
        $admin = $this->userWithRole('admin');
        $admin->assignRole('coordinador');

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), $this->updatePayload($admin, [
                'roles' => ['admin', 'coordinador'],
                'estrategia_ids' => [$strategy->id],
            ]))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors('user');

        $this->assertStrategies($admin, []);
    }

    public function test_unapproved_paps_cannot_assign_coordinator_strategies(): void
    {
        $strategy = $this->strategy('Protegida');
        $paps = $this->userWithRole('paps');
        $paps->forceFill(['approved_at' => null])->save();
        $coordinator = $this->userWithRole('coordinador');

        $this->actingAs($paps)
            ->putJson(route('admin.users.update', $coordinator), $this->updatePayload($coordinator, [
                'estrategia_ids' => [$strategy->id],
            ]))->assertForbidden();

        $this->assertStrategies($coordinator, []);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    private function strategy(string $name, bool $active = true): CatalogoEstrategia
    {
        return CatalogoEstrategia::create(['nombre' => $name, 'activo' => $active]);
    }

    private function creationPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Coordinador nuevo',
            'email' => 'coordinador@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['coordinador'],
            'is_active' => true,
            'approved' => true,
        ], $overrides);
    }

    private function updatePayload(User $user, array $overrides = []): array
    {
        return array_replace([
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['coordinador'],
        ], $overrides);
    }

    private function assertStrategies(User $user, array $expected): void
    {
        sort($expected);

        $this->assertSame($expected, $user->estrategiasCoordinadas()
            ->pluck('catalogo_estrategias.id')->map(fn ($id) => (int) $id)->sort()->values()->all());
    }

    private function createSession(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => 'coordinator-browser',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Coordinator strategy management test',
            'payload' => 'test-payload',
            'last_activity' => time(),
        ]);
    }
}
