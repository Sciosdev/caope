<?php

namespace Tests\Feature\Developer;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageDeveloperAccessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_grants_and_revokes_developer_access(): void
    {
        $user = User::factory()->create();

        $this->artisan('caope:developer-access', ['email' => $user->email])
            ->expectsOutputToContain('Acceso de desarrollador concedido')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->hasRole('developer'));

        $this->artisan('caope:developer-access', [
            'email' => $user->email,
            '--revoke' => true,
        ])
            ->expectsOutputToContain('Acceso de desarrollador retirado')
            ->assertSuccessful();

        $this->assertFalse($user->fresh()->hasRole('developer'));
    }

    public function test_command_fails_for_unknown_user(): void
    {
        $this->artisan('caope:developer-access', ['email' => 'missing@example.com'])
            ->expectsOutput('No existe un usuario con ese correo.')
            ->assertFailed();
    }
}
