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
