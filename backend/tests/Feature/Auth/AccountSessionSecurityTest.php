<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountSessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_state_cannot_be_changed_through_generic_mass_assignment(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'approved_at' => null,
        ]);

        $user->fill([
            'name' => 'Updated safely',
            'is_active' => false,
            'approved_at' => now(),
        ])->save();

        $user->refresh();
        $this->assertSame('Updated safely', $user->name);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->approved_at);
    }

    public function test_email_change_requires_password_and_revokes_other_sessions_and_old_reset_tokens(): void
    {
        $user = User::factory()->create();
        $originalEmail = $user->email;
        $originalRememberToken = $user->remember_token;
        $this->createDatabaseSession($user, 'old-browser');
        DB::table('password_reset_tokens')->insert([
            'email' => $originalEmail,
            'token' => 'old-reset-token',
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'secured@example.com',
                'current_password' => 'password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('secured@example.com', $user->email);
        $this->assertNotSame($originalRememberToken, $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-browser']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $originalEmail]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_change_revokes_other_sessions_and_persistent_login(): void
    {
        $user = User::factory()->create();
        $originalRememberToken = $user->remember_token;
        $this->createDatabaseSession($user, 'browser-one');
        $this->createDatabaseSession($user, 'browser-two');

        $this->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertDatabaseMissing('sessions', ['id' => 'browser-one']);
        $this->assertDatabaseMissing('sessions', ['id' => 'browser-two']);
        $this->assertNotSame($originalRememberToken, $user->fresh()->remember_token);
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_reset_revokes_every_existing_session(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $originalRememberToken = $user->remember_token;
        $this->createDatabaseSession($user, 'stolen-browser');

        $this->post('/forgot-password', ['email' => $user->email]);

        /** @var ResetPassword $notification */
        $notification = Notification::sent($user, ResetPassword::class)->first();

        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('sessions', ['id' => 'stolen-browser']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertNotSame($originalRememberToken, $user->fresh()->remember_token);
    }

    public function test_blocking_user_revokes_existing_sessions_without_approving_or_unapproving(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'tutor']);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $target = User::factory()->create([
            'is_active' => true,
            'approved_at' => now(),
        ]);
        $target->syncRoles(['tutor']);
        $originalRememberToken = $target->remember_token;
        $this->createDatabaseSession($target, 'target-browser');

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('admin.users.toggle-active', $target), ['is_active' => false])
            ->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertFalse($target->is_active);
        $this->assertNotNull($target->approved_at);
        $this->assertNotSame($originalRememberToken, $target->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-browser']);
    }

    public function test_administrator_password_reset_revokes_target_sessions(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'tutor']);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $target = User::factory()->create();
        $target->syncRoles(['tutor']);
        $originalRememberToken = $target->remember_token;
        $this->createDatabaseSession($target, 'target-password-browser');

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('admin.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'password' => 'changed-password',
                'password_confirmation' => 'changed-password',
                'roles' => ['tutor'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('sessions', ['id' => 'target-password-browser']);
        $this->assertNotSame($originalRememberToken, $target->fresh()->remember_token);
    }

    private function createDatabaseSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Security test',
            'payload' => 'test-payload',
            'last_activity' => time(),
        ]);
    }
}
