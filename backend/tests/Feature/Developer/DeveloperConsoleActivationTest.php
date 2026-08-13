<?php

namespace Tests\Feature\Developer;

use App\Models\User;
use App\Services\DeveloperConsoleSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeveloperConsoleActivationTest extends TestCase
{
    use RefreshDatabase;

    private string $settingsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsPath = storage_path('app/testing/developer-console-settings.enc');
        File::delete($this->settingsPath);

        config([
            'developer_console.enabled' => false,
            'developer_console.settings_path' => $this->settingsPath,
            'developer_console.github.token' => '',
        ]);

        Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);
    }

    protected function tearDown(): void
    {
        File::delete($this->settingsPath);

        parent::tearDown();
    }

    public function test_guest_cannot_open_activation(): void
    {
        $this->get(route('developer.activation.show'))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_open_activation(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession($this->confirmedPasswordSession())
            ->get(route('developer.activation.show'))
            ->assertForbidden();
    }

    public function test_admin_must_confirm_password_before_activation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('developer.activation.show'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_confirmed_admin_sees_the_one_field_activation_flow(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession($this->confirmedPasswordSession())
            ->get(route('developer.activation.show'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Activar despliegues')
            ->assertSee('Token de GitHub')
            ->assertDontSee('DEPLOY_HOST');
    }

    public function test_valid_token_activates_console_and_is_stored_encrypted(): void
    {
        $this->fakeValidToken();
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->withSession($this->confirmedPasswordSession())
            ->post(route('developer.activation.store'), [
                'developer_user_id' => $admin->id,
                'github_token' => 'github_pat_secret-production-token',
            ]);

        $response->assertRedirect(route('developer.index'));
        $this->assertTrue($admin->fresh()->hasRole('developer'));
        $this->assertFileExists($this->settingsPath);

        $encrypted = (string) File::get($this->settingsPath);
        $this->assertStringNotContainsString('github_pat_secret-production-token', $encrypted);

        $settings = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('github_pat_secret-production-token', $settings['github']['token']);
        $this->assertSame(199102136, $settings['github']['workflow_id']);
        $this->assertSame('deploy.yml', $settings['github']['workflow']);

        Http::assertSentCount(2);
    }

    public function test_read_only_token_is_rejected_without_leaking_or_changing_access(): void
    {
        Http::fake([
            'api.github.com/repos/Sciosdev/caope/actions/workflows/deploy.yml' => Http::response([
                'id' => 199102136,
                'state' => 'active',
                'path' => '.github/workflows/deploy.yml',
            ]),
            'api.github.com/repos/Sciosdev/caope/actions/workflows/deploy.yml/dispatches' => Http::response([
                'message' => 'Resource not accessible by personal access token',
            ], 403),
        ]);
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->withSession($this->confirmedPasswordSession())
            ->from(route('developer.activation.show'))
            ->post(route('developer.activation.store'), [
                'developer_user_id' => $admin->id,
                'github_token' => 'github_pat_read-only-secret',
            ]);

        $response->assertRedirect(route('developer.activation.show'));
        $response->assertSessionHasErrors('github_token');
        $response->assertSessionMissing('_old_input.github_token');
        $this->assertFalse($admin->fresh()->hasRole('developer'));
        $this->assertFileDoesNotExist($this->settingsPath);
    }

    public function test_second_activation_cannot_overwrite_existing_settings(): void
    {
        app(DeveloperConsoleSettings::class)->storeProduction('existing-token', 199102136, 1);
        $admin = $this->admin();

        Http::fake();

        $this->actingAs($admin)
            ->withSession($this->confirmedPasswordSession())
            ->get(route('developer.activation.show'))
            ->assertNotFound();

        $this->actingAs($admin)
            ->withSession($this->confirmedPasswordSession())
            ->post(route('developer.activation.store'), [
                'developer_user_id' => $admin->id,
                'github_token' => 'github_pat_replacement-not-allowed',
            ])
            ->assertSessionHasErrors('activation');

        $this->assertSame('existing-token', app(DeveloperConsoleSettings::class)->githubToken());
        Http::assertNothingSent();
    }

    public function test_corrupt_settings_require_explicit_recovery_confirmation(): void
    {
        File::ensureDirectoryExists(dirname($this->settingsPath));
        File::put($this->settingsPath, 'not-valid-encrypted-settings');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession($this->confirmedPasswordSession())
            ->get(route('developer.activation.show'))
            ->assertOk()
            ->assertSee('Recuperación')
            ->assertSee('confirm_recovery');

        $this->actingAs($admin)
            ->withSession($this->confirmedPasswordSession())
            ->post(route('developer.activation.store'), [
                'developer_user_id' => $admin->id,
                'github_token' => 'github_pat_recovery-token-secret',
            ])
            ->assertSessionHasErrors('confirm_recovery');

        $this->assertSame('not-valid-encrypted-settings', File::get($this->settingsPath));
        $this->assertFalse($admin->fresh()->hasRole('developer'));

        $this->fakeValidToken();

        $this->actingAs($admin)
            ->withSession($this->confirmedPasswordSession())
            ->post(route('developer.activation.store'), [
                'developer_user_id' => $admin->id,
                'github_token' => 'github_pat_recovery-token-secret',
                'confirm_recovery' => '1',
            ])
            ->assertRedirect(route('developer.index'));

        $this->assertTrue($admin->fresh()->hasRole('developer'));
        $this->assertSame(
            'github_pat_recovery-token-secret',
            app(DeveloperConsoleSettings::class)->githubToken()
        );
    }

    public function test_developer_can_rotate_credentials_without_exposing_old_token(): void
    {
        $this->fakeValidToken();
        $admin = $this->admin();
        $admin->assignRole(Role::query()->firstOrCreate(['name' => 'developer', 'guard_name' => 'web']));
        app(DeveloperConsoleSettings::class)->storeProduction('old-token', 199102136, $admin->id);

        $this->actingAs($admin)
            ->withSession($this->confirmedPasswordSession())
            ->post(route('developer.credentials.rotate'), [
                'github_token' => 'github_pat_new-production-token',
            ])
            ->assertRedirect();

        $this->assertSame('github_pat_new-production-token', app(DeveloperConsoleSettings::class)->githubToken());
        $this->assertStringNotContainsString('github_pat_new-production-token', (string) File::get($this->settingsPath));
    }

    private function fakeValidToken(): void
    {
        Http::fake(function (ClientRequest $request) {
            if ($request->method() === 'GET') {
                return Http::response([
                    'id' => 199102136,
                    'state' => 'active',
                    'path' => '.github/workflows/deploy.yml',
                ]);
            }

            return Http::response(['message' => 'No ref found'], 422);
        });
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        return $admin;
    }

    /** @return array<string, int> */
    private function confirmedPasswordSession(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }
}
