<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class DatabaseSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        try {
            (new DatabaseSeeder)->run();
            $this->fail('The demo seeder ran in production.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('local or testing', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_seeder_uses_ephemeral_passwords_and_no_remember_tokens(): void
    {
        $this->seed(DatabaseSeeder::class);

        $users = User::query()->where('email', 'like', '%@demo.local')->get();

        $this->assertCount(5, $users);

        foreach ($users as $user) {
            $this->assertFalse(Hash::check('password', $user->password));
            $this->assertNull($user->remember_token);
        }
    }

    public function test_authorization_migration_revokes_historical_demo_accounts_and_sessions(): void
    {
        $legacyPassword = Str::random(40);
        $demoEmails = [
            'admin@demo.local',
            'alumno@demo.local',
            'docente@demo.local',
            'coordinacion@demo.local',
            'paps@demo.local',
        ];
        $demoUsers = collect($demoEmails)->map(function (string $email) use ($legacyPassword): User {
            $user = User::factory()->create([
                'email' => $email,
                'password' => Hash::make($legacyPassword),
                'remember_token' => Str::random(20),
                'is_active' => true,
                'approved_at' => now(),
            ]);

            DB::table('sessions')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'historical-demo-test',
                'payload' => 'test-payload',
                'last_activity' => now()->timestamp,
            ]);
            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => Hash::make(Str::random(40)),
                'created_at' => now(),
            ]);

            return $user;
        });
        $unrelatedUser = User::factory()->create([
            'email' => 'legitimate@example.com',
            'password' => Hash::make($legacyPassword),
            'remember_token' => Str::random(20),
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_08_13_000004_ensure_base_roles_and_permissions.php');
        $migration->up();

        foreach ($demoUsers as $demoUser) {
            $demoUser->refresh();

            $this->assertFalse(Hash::check($legacyPassword, $demoUser->password));
            $this->assertNull($demoUser->remember_token);
            $this->assertFalse($demoUser->is_active);
            $this->assertNull($demoUser->approved_at);
            $this->assertDatabaseMissing('sessions', ['user_id' => $demoUser->id]);
            $this->assertDatabaseMissing('password_reset_tokens', ['email' => $demoUser->email]);
        }

        $unrelatedUser->refresh();
        $this->assertTrue(Hash::check($legacyPassword, $unrelatedUser->password));
        $this->assertTrue($unrelatedUser->is_active);
        $this->assertNotNull($unrelatedUser->approved_at);
    }
}
