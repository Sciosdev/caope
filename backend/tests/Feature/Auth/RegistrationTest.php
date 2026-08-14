<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_fresh_database_contains_the_base_authorization_matrix(): void
    {
        $expected = [
            'admin' => ['expedientes.view', 'expedientes.manage', 'usuarios.manage', 'reportes.view', 'sesiones.validate'],
            'coordinador' => ['expedientes.view', 'expedientes.manage', 'reportes.view', 'sesiones.validate'],
            'docente' => ['expedientes.view', 'sesiones.validate'],
            'estratega' => ['expedientes.view', 'sesiones.validate'],
            'alumno' => ['expedientes.view'],
            'paps' => ['expedientes.view', 'expedientes.manage', 'usuarios.manage', 'reportes.view', 'sesiones.validate'],
            'developer' => [],
        ];

        foreach ($expected as $roleName => $permissions) {
            $role = Role::findByName($roleName);

            $this->assertEqualsCanonicalizing(
                $permissions,
                $role->permissions()->pluck('name')->all(),
                "Unexpected permissions for role {$roleName}."
            );
        }
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        $response
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'is_active' => false,
            'approved_at' => null,
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('alumno'));
    }

    public function test_registration_rolls_back_when_the_default_role_is_missing(): void
    {
        Event::fake([Registered::class]);
        Role::findByName('alumno')->delete();
        $this->withoutExceptionHandling();

        try {
            $this->post('/register', [
                'name' => 'Incomplete User',
                'email' => 'incomplete@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);
            $this->fail('Registration succeeded without the required role.');
        } catch (RoleDoesNotExist) {
            // The transaction must roll the user insert back.
        }

        $this->assertDatabaseMissing('users', ['email' => 'incomplete@example.com']);
        Event::assertNotDispatched(Registered::class);
    }
}
