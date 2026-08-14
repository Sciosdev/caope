<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\UserController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_guest_is_redirected_from_user_index(): void
    {
        $response = $this->get(route('admin.users.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_user_index(): void
    {
        $this->seedRoles();

        $user = User::factory()->create();
        $user->syncRoles(['tutor']);

        $this->actingAs($user);

        $response = $this->get(route('admin.users.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_access_user_index(): void
    {
        $this->seedRoles();

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $otherUser = User::factory()->create(['name' => 'Usuario prueba']);
        $otherUser->syncRoles(['tutor']);

        $this->actingAs($admin);

        $response = $this->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Usuario prueba');
        $response->assertSee('tutor', false);
    }

    public function test_create_form_displays_paps_role_even_if_missing_in_roles_table(): void
    {
        $this->seedRoles();

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        Role::query()->where('name', 'paps')->delete();

        $this->actingAs($admin);

        $response = $this->get(route('admin.users.create'));

        $response->assertOk();
        $response->assertSee('value="paps"', false);
        $this->assertDatabaseHas('roles', ['name' => 'paps', 'guard_name' => 'web']);
    }

    public function test_admin_can_create_user_with_roles(): void
    {
        $this->seedRoles();

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $this->actingAs($admin);

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['tutor'],
            'carrera' => 'Ingeniería',
            'turno' => 'Matutino',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'nuevo@example.com',
            'carrera' => 'Ingeniería',
            'turno' => 'Matutino',
        ]);

        $created = User::where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertTrue($created->hasRole('tutor'));
    }

    public function test_developer_role_cannot_be_assigned_from_user_management(): void
    {
        $this->seedRoles();
        Role::create(['name' => 'developer']);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $this->actingAs($admin);

        $form = $this->get(route('admin.users.create'));
        $form->assertOk();
        $form->assertDontSee('value="developer"', false);

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Usuario técnico',
            'email' => 'tecnico@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['developer'],
        ]);

        $response->assertSessionHasErrors('roles.0');
        $this->assertDatabaseMissing('users', ['email' => 'tecnico@example.com']);
    }

    public function test_developer_accounts_are_hidden_and_cannot_be_modified_from_user_management(): void
    {
        $this->seedRoles();
        Role::create(['name' => 'developer']);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $developer = User::factory()->create([
            'name' => 'Cuenta técnica protegida',
            'email' => 'developer@example.com',
        ]);
        $developer->syncRoles(['developer']);

        $this->actingAs($admin);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('Cuenta técnica protegida');

        $this->get(route('admin.users.edit', $developer))->assertForbidden();
        $this->delete(route('admin.users.destroy', $developer))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $developer->id]);
    }

    public function test_admin_can_update_user_information_and_roles(): void
    {
        $this->seedRoles();

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $user = User::factory()->create(['name' => 'Antiguo Nombre', 'email' => 'antiguo@example.com']);
        $user->syncRoles(['tutor']);

        $this->actingAs($admin);

        $response = $this->put(route('admin.users.update', $user), [
            'name' => 'Nombre Actualizado',
            'email' => 'actualizado@example.com',
            'roles' => ['admin'],
            'carrera' => 'Psicología',
            'turno' => 'Vespertino',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user->refresh();

        $this->assertSame('Nombre Actualizado', $user->name);
        $this->assertSame('actualizado@example.com', $user->email);
        $this->assertSame('Psicología', $user->carrera);
        $this->assertSame('Vespertino', $user->turno);
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_paps_only_lists_users_with_roles_it_is_allowed_to_manage(): void
    {
        $this->app->setLocale('en');

        $this->seedRoles();
        Role::create(['name' => 'paps']);
        Role::create(['name' => 'developer']);

        $paps = User::factory()->create(['approved_at' => now()]);
        $paps->syncRoles(['paps']);

        $admin = User::factory()->create(['name' => 'Administrador protegido']);
        $admin->syncRoles(['admin']);

        $developer = User::factory()->create(['name' => 'Desarrollador protegido']);
        $developer->syncRoles(['developer']);

        $regularUser = User::factory()->create(['name' => 'Perfil permitido']);
        $regularUser->syncRoles(['tutor']);

        $response = $this->actingAs($paps)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Perfil permitido');
        $response->assertDontSee('Administrador protegido');
        $response->assertDontSee('Desarrollador protegido');
    }

    public function test_paps_cannot_manage_an_admin_through_direct_urls(): void
    {
        $this->seedRoles();
        Role::create(['name' => 'paps']);

        $paps = User::factory()->create(['approved_at' => now()]);
        $paps->syncRoles(['paps']);

        $admin = User::factory()->create([
            'name' => 'Administrador protegido',
            'is_active' => true,
            'approved_at' => null,
        ]);
        $admin->syncRoles(['admin']);

        $this->actingAs($paps);

        $this->get(route('admin.users.edit', $admin))->assertForbidden();
        $this->put(route('admin.users.update', $admin), [
            'name' => 'Administrador alterado',
            'email' => $admin->email,
            'roles' => ['tutor'],
        ])->assertForbidden();
        $this->patch(route('admin.users.approve', $admin))->assertForbidden();
        $this->patch(route('admin.users.toggle-active', $admin), ['is_active' => false])->assertForbidden();
        $this->delete(route('admin.users.destroy', $admin))->assertForbidden();

        $admin->refresh();
        $this->assertSame('Administrador protegido', $admin->name);
        $this->assertTrue($admin->is_active);
        $this->assertNull($admin->approved_at);
        $this->assertTrue($admin->hasRole('admin'));
    }

    public function test_approved_paps_cannot_see_manage_or_delegate_paps_accounts(): void
    {
        $this->app->setLocale('en');

        $this->seedRoles();
        Role::create(['name' => 'paps']);

        $actor = User::factory()->create(['approved_at' => now()]);
        $actor->syncRoles(['paps']);

        $protectedPaps = User::factory()->create([
            'name' => 'PAPS protegido',
            'email' => 'paps-protegido@example.com',
            'is_active' => true,
            'approved_at' => now(),
        ]);
        $protectedPaps->syncRoles(['paps']);

        $regularUser = User::factory()->create([
            'name' => 'Perfil regular',
            'email' => 'perfil-regular@example.com',
        ]);
        $regularUser->syncRoles(['tutor']);

        $this->actingAs($actor);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('PAPS protegido');

        $this->get(route('admin.users.create'))
            ->assertOk()
            ->assertDontSee('value="paps"', false);

        $this->get(route('admin.users.edit', $protectedPaps))->assertForbidden();
        $this->put(route('admin.users.update', $protectedPaps), [
            'name' => 'PAPS alterado',
            'email' => $protectedPaps->email,
            'roles' => ['tutor'],
        ])->assertForbidden();
        $this->patch(route('admin.users.approve', $protectedPaps))->assertForbidden();
        $this->patch(route('admin.users.toggle-active', $protectedPaps), ['is_active' => false])
            ->assertForbidden();
        $this->delete(route('admin.users.destroy', $protectedPaps))->assertForbidden();

        $this->post(route('admin.users.store'), [
            'name' => 'PAPS delegado',
            'email' => 'paps-delegado@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['paps'],
        ])->assertSessionHasErrors('roles.0');

        $this->put(route('admin.users.update', $regularUser), [
            'name' => $regularUser->name,
            'email' => $regularUser->email,
            'roles' => ['paps'],
        ])->assertSessionHasErrors('roles.0');

        $protectedPaps->refresh();
        $this->assertSame('PAPS protegido', $protectedPaps->name);
        $this->assertTrue($protectedPaps->is_active);
        $this->assertTrue($protectedPaps->hasRole('paps'));
        $this->assertDatabaseMissing('users', ['email' => 'paps-delegado@example.com']);
        $this->assertTrue($regularUser->fresh()->hasRole('tutor'));
        $this->assertFalse($regularUser->fresh()->hasRole('paps'));
    }

    public function test_unapproved_paps_is_forbidden_and_cannot_approve_itself(): void
    {
        $this->seedRoles();
        Role::create(['name' => 'paps']);

        $paps = User::factory()->create([
            'approved_at' => null,
            'is_active' => true,
        ]);
        $paps->syncRoles(['paps']);

        $this->actingAs($paps);

        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.users.create'))->assertForbidden();
        $this->patch(route('admin.users.approve', $paps))->assertForbidden();

        $this->assertNull($paps->fresh()->approved_at);
    }

    public function test_paps_cannot_assign_protected_roles_with_forged_payloads(): void
    {
        $this->seedRoles();
        Role::create(['name' => 'paps']);
        Role::create(['name' => 'developer']);

        $paps = User::factory()->create(['approved_at' => now()]);
        $paps->syncRoles(['paps']);

        $regularUser = User::factory()->create();
        $regularUser->syncRoles(['tutor']);

        $this->actingAs($paps);

        foreach (['admin', 'developer'] as $protectedRole) {
            $email = $protectedRole.'-forged@example.com';

            $this->post(route('admin.users.store'), [
                'name' => 'Rol protegido',
                'email' => $email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'roles' => [$protectedRole],
            ])->assertSessionHasErrors('roles.0');

            $this->assertDatabaseMissing('users', ['email' => $email]);

            $this->put(route('admin.users.update', $regularUser), [
                'name' => $regularUser->name,
                'email' => $regularUser->email,
                'roles' => [$protectedRole],
            ])->assertSessionHasErrors('roles.0');

            $this->assertTrue($regularUser->fresh()->hasRole('tutor'));
            $this->assertFalse($regularUser->fresh()->hasRole($protectedRole));
        }
    }

    public function test_paps_keeps_crud_for_allowed_profiles(): void
    {
        $this->app->setLocale('en');

        $this->seedRoles();
        Role::create(['name' => 'paps']);
        Role::create(['name' => 'alumno']);

        $paps = User::factory()->create(['approved_at' => now()]);
        $paps->syncRoles(['paps']);

        $this->actingAs($paps);

        $this->post(route('admin.users.store'), [
            'name' => 'Perfil gestionable',
            'email' => 'gestionable@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['tutor'],
            'approved' => false,
        ])->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'gestionable@example.com')->firstOrFail();

        $this->get(route('admin.users.edit', $user))->assertOk();
        $this->put(route('admin.users.update', $user), [
            'name' => 'Perfil actualizado',
            'email' => $user->email,
            'roles' => ['alumno'],
        ])->assertRedirect(route('admin.users.index'));
        $this->patch(route('admin.users.approve', $user))->assertRedirect(route('admin.users.index'));
        $this->patch(route('admin.users.toggle-active', $user), ['is_active' => false])
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertSame('Perfil actualizado', $user->name);
        $this->assertTrue($user->hasRole('alumno'));
        $this->assertNotNull($user->approved_at);
        $this->assertFalse($user->is_active);

        $this->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_paps_role_options_hide_protected_roles_and_label_alumno_as_facilitador(): void
    {
        $this->app->setLocale('en');

        $this->seedRoles();
        Role::create(['name' => 'paps']);
        Role::create(['name' => 'alumno']);
        Role::create(['name' => 'developer']);

        $paps = User::factory()->create(['approved_at' => now()]);
        $paps->syncRoles(['paps']);

        $facilitator = User::factory()->create(['name' => 'Facilitadora visible']);
        $facilitator->syncRoles(['alumno']);

        $form = $this->actingAs($paps)->get(route('admin.users.create'));

        $form->assertOk();
        $form->assertSee('value="alumno"', false);
        $form->assertSee('Facilitador');
        $form->assertDontSee('value="admin"', false);
        $form->assertDontSee('value="developer"', false);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Facilitadora visible')
            ->assertSee('Facilitador');
    }

    public function test_admin_cannot_edit_their_own_account(): void
    {
        $this->seedRoles();

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $this->actingAs($admin);

        $response = $this->get(route('admin.users.edit', $admin));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasErrors('user');
    }

    public function test_cannot_remove_last_admin_role(): void
    {
        $this->seedRoles();

        $onlyAdmin = User::factory()->create(['name' => 'Unico Admin']);
        $onlyAdmin->syncRoles(['admin']);

        $acting = User::factory()->create();
        $acting->syncRoles(['admin']);

        $this->actingAs($acting);
        $acting->syncRoles([]);

        $session = app('session.store');
        $session->start();

        $request = Request::create(route('admin.users.update', $onlyAdmin), 'PUT', [
            'name' => $onlyAdmin->name,
            'email' => $onlyAdmin->email,
            'roles' => ['tutor'],
        ]);

        $request->setLaravelSession($session);

        /** @var RedirectResponse $response */
        $response = app(UserController::class)->update($request, $onlyAdmin);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.users.edit', $onlyAdmin), $response->getTargetUrl());

        $errors = $response->getSession()->get('errors');
        $this->assertNotNull($errors);
        $this->assertArrayHasKey('roles', $errors->getMessages());
        $this->assertTrue($onlyAdmin->fresh()->hasRole('admin'));
    }

    public function test_cannot_delete_last_admin_user(): void
    {
        $this->seedRoles();

        $onlyAdmin = User::factory()->create();
        $onlyAdmin->syncRoles(['admin']);

        $acting = User::factory()->create();
        $acting->syncRoles(['admin']);

        $this->actingAs($acting);
        $acting->syncRoles([]);

        $session = app('session.store');
        $session->start();

        /** @var RedirectResponse $response */
        $response = app(UserController::class)->destroy($onlyAdmin);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.users.index'), $response->getTargetUrl());

        $errors = $response->getSession()->get('errors');
        $this->assertNotNull($errors);
        $this->assertArrayHasKey('user', $errors->getMessages());
        $this->assertTrue($onlyAdmin->fresh()->exists());
    }

    private function seedRoles(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'tutor']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
