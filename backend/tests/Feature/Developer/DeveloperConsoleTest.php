<?php

namespace Tests\Feature\Developer;

use App\Models\DeploymentRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeveloperConsoleTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $workflowRuns = [];

    private int $dispatchStatus = 404;

    /** @var array<string, mixed>|null */
    private ?array $dispatchBody = [];

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->firstOrCreate(['name' => 'developer', 'guard_name' => 'web']);

        config([
            'developer_console.enabled' => true,
            'developer_console.allowed_ips' => [],
            'developer_console.target_label' => 'pruebas',
            'developer_console.github.repository' => 'Sciosdev/caope',
            'developer_console.github.workflow' => 'deploy.yml',
            'developer_console.github.ref' => 'main',
            'developer_console.github.token' => 'test-token',
            'app.env' => 'staging',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.url' => 'https://caope.ayudafesi.com',
            'security.trusted_hosts' => ['caope.ayudafesi.com'],
            'session.driver' => 'database',
            'session.encrypt' => true,
            'session.secure' => true,
            'backup.backup.password' => 'testing-backup-password-32-characters',
        ]);
        $this->withServerVariables([
            'HTTP_HOST' => 'caope.ayudafesi.com',
            'HTTPS' => 'on',
            'SERVER_PORT' => 443,
        ]);
        URL::forceRootUrl('https://caope.ayudafesi.com');
        URL::forceScheme('https');

        Http::fake(function (ClientRequest $request) {
            if (str_contains($request->url(), '/actions/workflows/deploy.yml/runs')) {
                return Http::response(['workflow_runs' => $this->workflowRuns]);
            }

            if (str_ends_with($request->url(), '/dispatches')) {
                return Http::response($this->dispatchBody, $this->dispatchStatus);
            }

            return Http::response([], 404);
        });
    }

    public function test_console_is_not_exposed_when_disabled(): void
    {
        config(['developer_console.enabled' => false]);
        $developer = $this->developer();

        $response = $this->actingAs($developer)
            ->withSession($this->confirmedPasswordSession())
            ->get(route('developer.index'));

        $response->assertNotFound();
    }

    public function test_user_without_developer_role_cannot_access_console(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession($this->confirmedPasswordSession())
            ->get(route('developer.index'));

        $response->assertForbidden();
    }

    public function test_developer_must_confirm_password_again(): void
    {
        $developer = $this->developer();

        $response = $this->actingAs($developer)->get(route('developer.index'));

        $response->assertRedirect(route('password.confirm'));
    }

    public function test_developer_can_view_health_checks_after_confirming_password(): void
    {
        $developer = $this->developer();

        $response = $this->actingAs($developer)
            ->withSession($this->confirmedPasswordSession())
            ->get(route('developer.index'));

        $response->assertOk();
        $response->assertSee('Consola del desarrollador');
        $response->assertSee('Base de datos');
        $response->assertSee('Desplegar pruebas');
    }

    public function test_developer_can_dispatch_audited_deployment(): void
    {
        $this->dispatchBody = null;
        $this->dispatchStatus = 204;

        $developer = $this->developer();

        $response = $this->actingAs($developer)
            ->withSession($this->confirmedPasswordSession())
            ->post(route('developer.deploy'), [
                'confirmation' => 'DESPLEGAR',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $deployment = DeploymentRun::query()->sole();
        $this->assertSame($developer->id, $deployment->requested_by);
        $this->assertSame('main', $deployment->ref);
        $this->assertSame('requested', $deployment->status);

        Http::assertSent(function (ClientRequest $request) use ($deployment): bool {
            return str_ends_with($request->url(), '/dispatches')
                && $request['ref'] === 'main'
                && data_get($request->data(), 'inputs.request_id') === $deployment->request_id;
        });
    }

    public function test_deployment_requires_explicit_confirmation(): void
    {
        $developer = $this->developer();

        $response = $this->actingAs($developer)
            ->withSession($this->confirmedPasswordSession())
            ->post(route('developer.deploy'), [
                'confirmation' => 'SI',
            ]);

        $response->assertSessionHasErrors('confirmation');
        $this->assertDatabaseCount('deployment_runs', 0);
    }

    public function test_unsafe_configuration_blocks_dispatch_before_recording_a_deployment(): void
    {
        config(['backup.backup.password' => null]);
        $developer = $this->developer();

        $response = $this->actingAs($developer)
            ->withSession($this->confirmedPasswordSession())
            ->post(route('developer.deploy'), [
                'confirmation' => 'DESPLEGAR',
            ]);

        $response->assertSessionHasErrors('deployment');
        $this->assertDatabaseCount('deployment_runs', 0);
        Http::assertNothingSent();
    }

    public function test_failed_dispatch_is_kept_in_audit_history(): void
    {
        $this->dispatchBody = ['message' => 'Forbidden'];
        $this->dispatchStatus = 403;

        $developer = $this->developer();

        $response = $this->actingAs($developer)
            ->withSession($this->confirmedPasswordSession())
            ->post(route('developer.deploy'), [
                'confirmation' => 'DESPLEGAR',
            ]);

        $response->assertSessionHasErrors('deployment');
        $this->assertDatabaseHas('deployment_runs', [
            'requested_by' => $developer->id,
            'status' => 'failed_to_dispatch',
        ]);
    }

    public function test_existing_active_deployment_blocks_a_duplicate_dispatch(): void
    {
        $developer = $this->developer();
        DeploymentRun::query()->create([
            'request_id' => '60f3c860-81e5-4c91-92d5-acd2197a1a81',
            'requested_by' => $developer->id,
            'ref' => 'main',
            'status' => 'waiting',
        ]);

        $response = $this->actingAs($developer)
            ->withSession($this->confirmedPasswordSession())
            ->post(route('developer.deploy'), [
                'confirmation' => 'DESPLEGAR',
            ]);

        $response->assertSessionHasErrors('deployment');
        $this->assertDatabaseCount('deployment_runs', 1);
        Http::assertNotSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/dispatches'));
    }

    public function test_console_synchronizes_a_github_workflow_status(): void
    {
        $developer = $this->developer();
        $deployment = DeploymentRun::query()->create([
            'request_id' => 'd062783e-3538-46ba-a56b-1c35e215ee04',
            'requested_by' => $developer->id,
            'ref' => 'main',
            'status' => 'requested',
        ]);

        $this->workflowRuns = [[
            'id' => 12345,
            'display_title' => "Deploy production main [{$deployment->request_id}]",
            'status' => 'completed',
            'conclusion' => 'success',
            'html_url' => 'https://github.com/Sciosdev/caope/actions/runs/12345',
            'head_sha' => str_repeat('a', 40),
        ]];

        $this->actingAs($developer)
            ->withSession($this->confirmedPasswordSession())
            ->get(route('developer.index'))
            ->assertOk()
            ->assertSee('Completado');

        $this->assertDatabaseHas('deployment_runs', [
            'id' => $deployment->id,
            'status' => 'completed',
            'conclusion' => 'success',
            'workflow_run_id' => 12345,
            'commit_sha' => str_repeat('a', 40),
        ]);
    }

    public function test_ip_allow_list_is_enforced(): void
    {
        config(['developer_console.allowed_ips' => ['203.0.113.10']]);
        $developer = $this->developer();

        $response = $this->actingAs($developer)
            ->withSession($this->confirmedPasswordSession())
            ->get(route('developer.index'));

        $response->assertForbidden();
    }

    private function developer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('developer');

        return $user;
    }

    /**
     * @return array<string, int>
     */
    private function confirmedPasswordSession(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }
}
