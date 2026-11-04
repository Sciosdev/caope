<?php

namespace Tests\Feature\Admin;

use App\Models\CatalogoEstrategia;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserMultiAssignmentFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'paps', 'coordinador', 'estratega', 'docente', 'alumno', 'developer'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->userWithRoles(['admin']))
            ->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_create_form_offers_independent_role_and_strategy_checkboxes(): void
    {
        $first = $this->strategy('Primera estrategia');
        $second = $this->strategy('Segunda estrategia');

        $response = $this->get(route('admin.users.create'))->assertOk();

        $roles = $this->assertCheckboxGroup($response, 'roles', 'roles[]', []);
        $strategies = $this->assertCheckboxGroup($response, 'estrategia_ids', 'estrategia_ids[]', []);

        foreach (['coordinador', 'estratega', 'docente', 'alumno'] as $role) {
            $this->assertArrayHasKey($role, $roles);
        }
        $this->assertArrayNotHasKey('developer', $roles);
        $this->assertEqualsCanonicalizing([(string) $first->id, (string) $second->id], array_keys($strategies));
        $this->assertPresenceMarkers($response);
    }

    public function test_edit_form_checks_all_existing_roles_and_multiple_strategies(): void
    {
        $first = $this->strategy('Primera estrategia');
        $second = $this->strategy('Segunda estrategia');
        $this->strategy('No asignada');
        $user = $this->userWithRoles(['coordinador', 'estratega', 'alumno']);
        $user->estrategiasCoordinadas()->attach([$first->id, $second->id]);

        $response = $this->get(route('admin.users.edit', $user))->assertOk();

        $this->assertCheckboxGroup($response, 'roles', 'roles[]', ['coordinador', 'estratega', 'alumno']);
        $this->assertCheckboxGroup($response, 'estrategia_ids', 'estrategia_ids[]', [$first->id, $second->id]);
        $this->assertPresenceMarkers($response);
        $this->assertAssignments($user, ['coordinador', 'estratega', 'alumno'], [$first->id, $second->id]);
    }

    public function test_create_form_restores_multiple_roles_and_strategies_after_validation(): void
    {
        $first = $this->strategy('Primera estrategia');
        $second = $this->strategy('Segunda estrategia');
        $this->strategy('No seleccionada');

        $response = $this->withSession(['_old_input' => [
            'roles_present' => '1',
            'roles' => ['coordinador', 'estratega'],
            'estrategia_ids_present' => '1',
            'estrategia_ids' => [(string) $first->id, (string) $second->id],
        ]])->get(route('admin.users.create'))->assertOk();

        $this->assertCheckboxGroup($response, 'roles', 'roles[]', ['coordinador', 'estratega']);
        $this->assertCheckboxGroup($response, 'estrategia_ids', 'estrategia_ids[]', [$first->id, $second->id]);
    }

    public function test_edit_form_uses_old_selections_instead_of_restoring_saved_assignments(): void
    {
        $saved = $this->strategy('Anterior');
        $selected = $this->strategy('Seleccionada');
        $user = $this->userWithRoles(['coordinador', 'estratega']);
        $user->estrategiasCoordinadas()->attach($saved);

        $response = $this->withSession(['_old_input' => [
            'roles_present' => '1',
            'roles' => ['coordinador', 'docente'],
            'estrategia_ids_present' => '1',
            'estrategia_ids' => [(string) $selected->id],
        ]])->get(route('admin.users.edit', $user))->assertOk();

        $this->assertCheckboxGroup($response, 'roles', 'roles[]', ['coordinador', 'docente']);
        $this->assertCheckboxGroup($response, 'estrategia_ids', 'estrategia_ids[]', [$selected->id]);
        $this->assertAssignments($user, ['coordinador', 'estratega'], [$saved->id]);
    }

    public function test_validation_can_redisplay_both_groups_empty_without_reselecting_saved_values(): void
    {
        $strategy = $this->strategy('Asignada');
        $user = $this->userWithRoles(['coordinador', 'estratega']);
        $user->estrategiasCoordinadas()->attach($strategy);

        $response = $this->withSession(['_old_input' => [
            'roles_present' => '1',
            'estrategia_ids_present' => '1',
        ]])->get(route('admin.users.edit', $user))->assertOk();

        $this->assertCheckboxGroup($response, 'roles', 'roles[]', []);
        $this->assertCheckboxGroup($response, 'estrategia_ids', 'estrategia_ids[]', []);
        $this->assertAssignments($user, ['coordinador', 'estratega'], [$strategy->id]);
    }

    public function test_admin_can_create_a_coordinator_and_strategist_with_two_strategies(): void
    {
        $first = $this->strategy('Primera estrategia');
        $second = $this->strategy('Segunda estrategia');

        $this->post(route('admin.users.store'), $this->creationPayload([
            'estrategia_ids' => [$first->id, $second->id],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'multi-asignacion@example.com')->firstOrFail();
        $this->assertAssignments($created, ['coordinador', 'estratega'], [$first->id, $second->id]);
    }

    public function test_adding_a_role_and_strategy_retains_other_checked_assignments(): void
    {
        $first = $this->strategy('Conservar');
        $second = $this->strategy('Agregar');
        $user = $this->userWithRoles(['coordinador', 'alumno']);
        $user->estrategiasCoordinadas()->attach($first);
        $passwordHash = $user->getRawOriginal('password');

        $this->put(route('admin.users.update', $user), $this->updatePayload($user, [
            'roles' => ['coordinador', 'alumno', 'estratega'],
            'estrategia_ids' => [$first->id, $second->id],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $this->assertAssignments($user, ['coordinador', 'alumno', 'estratega'], [$first->id, $second->id]);
        $this->assertSame($passwordHash, $user->fresh()->getRawOriginal('password'));
    }

    public function test_unchecking_all_strategies_clears_them_without_removing_the_selected_roles(): void
    {
        $strategy = $this->strategy('Quitar');
        $user = $this->userWithRoles(['coordinador', 'estratega']);
        $user->estrategiasCoordinadas()->attach($strategy);

        $this->put(route('admin.users.update', $user), $this->updatePayload($user))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $this->assertAssignments($user, ['coordinador', 'estratega'], []);
    }

    public function test_missing_or_empty_roles_are_rejected_without_updating_the_user_or_assignments(): void
    {
        $strategy = $this->strategy('Conservar');
        $user = $this->userWithRoles(['coordinador', 'estratega']);
        $user->estrategiasCoordinadas()->attach($strategy);
        $originalName = $user->name;

        foreach ([null, []] as $roles) {
            $payload = $this->updatePayload($user, ['name' => 'No debe guardarse']);
            if ($roles === null) {
                unset($payload['roles']);
            } else {
                $payload['roles'] = $roles;
            }

            $this->from(route('admin.users.edit', $user))
                ->put(route('admin.users.update', $user), $payload)
                ->assertSessionHasErrors('roles')
                ->assertRedirect(route('admin.users.edit', $user));

            $this->assertSame($originalName, $user->fresh()->name);
            $this->assertAssignments($user, ['coordinador', 'estratega'], [$strategy->id]);

            $response = $this->get(route('admin.users.edit', $user))->assertOk();
            $this->assertCheckboxGroup($response, 'roles', 'roles[]', []);
            $this->assertCheckboxGroup($response, 'estrategia_ids', 'estrategia_ids[]', []);
        }
    }

    public function test_invalid_role_is_rejected_without_replacing_existing_assignments(): void
    {
        $saved = $this->strategy('Conservar');
        $replacement = $this->strategy('No guardar');
        $user = $this->userWithRoles(['coordinador', 'estratega']);
        $user->estrategiasCoordinadas()->attach($saved);

        $this->put(route('admin.users.update', $user), $this->updatePayload($user, [
            'roles' => ['coordinador', 'rol-inexistente'],
            'estrategia_ids' => [$replacement->id],
        ]))->assertSessionHasErrors('roles.1');

        $this->assertAssignments($user, ['coordinador', 'estratega'], [$saved->id]);
    }

    public function test_approved_paps_can_use_the_multiple_assignment_controls_without_protected_roles(): void
    {
        $strategy = $this->strategy('Asignada');
        $user = $this->userWithRoles(['coordinador', 'estratega']);
        $user->estrategiasCoordinadas()->attach($strategy);
        $this->actingAs($this->userWithRoles(['paps']));

        foreach (['admin.users.create', 'admin.users.edit'] as $routeName) {
            $response = $this->get(route($routeName, $routeName === 'admin.users.edit' ? $user : []))->assertOk();
            $expectedRoles = $routeName === 'admin.users.edit' ? ['coordinador', 'estratega'] : [];
            $options = $this->assertCheckboxGroup($response, 'roles', 'roles[]', $expectedRoles);

            foreach (['admin', 'developer', 'paps'] as $protectedRole) {
                $this->assertArrayNotHasKey($protectedRole, $options);
            }
        }
    }

    public function test_paps_cannot_forge_admin_or_developer_checkboxes_in_create_or_update_requests(): void
    {
        $strategy = $this->strategy('Conservar');
        $user = $this->userWithRoles(['coordinador', 'estratega']);
        $user->estrategiasCoordinadas()->attach($strategy);
        $this->actingAs($this->userWithRoles(['paps']));

        foreach (['admin', 'developer'] as $protectedRole) {
            $email = $protectedRole.'-no-permitido@example.com';
            $this->post(route('admin.users.store'), $this->creationPayload([
                'email' => $email,
                'roles' => [$protectedRole, 'coordinador'],
                'estrategia_ids' => [$strategy->id],
            ]))->assertSessionHasErrors('roles.0');
            $this->assertDatabaseMissing('users', ['email' => $email]);

            $this->put(route('admin.users.update', $user), $this->updatePayload($user, [
                'roles' => [$protectedRole, 'coordinador'],
            ]))->assertSessionHasErrors('roles.0');
            $this->assertAssignments($user, ['coordinador', 'estratega'], [$strategy->id]);
        }
    }

    private function userWithRoles(array $roles): User
    {
        $user = User::factory()->create(['approved_at' => now(), 'is_active' => true]);
        $user->syncRoles($roles);

        return $user;
    }

    private function strategy(string $name): CatalogoEstrategia
    {
        return CatalogoEstrategia::create(['nombre' => $name, 'activo' => true]);
    }

    private function creationPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Usuario de múltiples asignaciones',
            'email' => 'multi-asignacion@example.com',
            'password' => 'Prueba-Segura-2026!',
            'password_confirmation' => 'Prueba-Segura-2026!',
            'roles_present' => '1',
            'roles' => ['coordinador', 'estratega'],
            'estrategia_ids_present' => '1',
            'is_active' => true,
            'approved' => true,
        ], $overrides);
    }

    private function updatePayload(User $user, array $overrides = []): array
    {
        return array_replace([
            'name' => $user->name,
            'email' => $user->email,
            'roles_present' => '1',
            'roles' => ['coordinador', 'estratega'],
            'estrategia_ids_present' => '1',
        ], $overrides);
    }

    private function assertAssignments(User $user, array $expectedRoles, array $expectedStrategies): void
    {
        $this->assertEqualsCanonicalizing($expectedRoles, $user->fresh()->getRoleNames()->all());
        $this->assertEqualsCanonicalizing($expectedStrategies, $user->estrategiasCoordinadas()->pluck('catalogo_estrategias.id')->all());
    }

    private function assertCheckboxGroup(TestResponse $response, string $groupId, string $inputName, array $checkedValues): array
    {
        $xpath = $this->xpath($response);
        $group = $xpath->query('//*[@id="'.$groupId.'"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $group);
        $this->assertSame(1, $xpath->query('ancestor-or-self::fieldset', $group)->length);
        $this->assertSame(0, $xpath->query('//select[@name="'.$inputName.'"]')->length);

        $options = [];
        foreach ($xpath->query('.//input[@type="checkbox" and @name="'.$inputName.'"]', $group) as $checkbox) {
            $options[$checkbox->getAttribute('value')] = $checkbox->hasAttribute('checked');
        }
        $this->assertNotEmpty($options);
        $actualChecked = array_map('strval', array_keys(array_filter($options)));
        $this->assertEqualsCanonicalizing(array_map('strval', $checkedValues), $actualChecked);

        return $options;
    }

    private function assertPresenceMarkers(TestResponse $response): void
    {
        $xpath = $this->xpath($response);
        foreach (['roles_present', 'estrategia_ids_present'] as $name) {
            $this->assertSame(1, $xpath->query('//input[@type="hidden" and @name="'.$name.'" and @value="1"]')->length);
        }
    }

    private function xpath(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }
}
