<?php

namespace Tests\Feature\Developer;

use App\Models\DeploymentRun;
use App\Models\User;
use App\Services\DeveloperHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\File;
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

    public function test_web_process_without_backup_metadata_access_does_not_block_deployment(): void
    {
        config(['backup.backup.destination.disks' => ['missing-backup-disk']]);
        $developer = $this->developer();

        $response = $this->actingAs($developer)
            ->withSession($this->confirmedPasswordSession())
            ->get(route('developer.index'));

        $response->assertOk();
        $response->assertSee('No fue posible revisar los respaldos desde la web.');
        $response->assertSee('Advertencia');
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

    public function test_cache_maintenance_blocks_dispatch_before_contacting_github(): void
    {
        config(['app.maintenance.driver' => 'cache']);

        $this->actingAs($this->developer())
            ->withSession($this->confirmedPasswordSession())
            ->post(route('developer.deploy'), ['confirmation' => 'DESPLEGAR'])
            ->assertSessionHasErrors('deployment');

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

    public function test_uncertain_github_dispatch_remains_pending_to_prevent_duplicate_attempts(): void
    {
        $this->dispatchStatus = 503;
        $developer = $this->developer();
        $this->actingAs($developer)->withSession($this->confirmedPasswordSession())
            ->post(route('developer.deploy'), ['confirmation' => 'DESPLEGAR'])
            ->assertSessionHasErrors('deployment');

        $deployment = DeploymentRun::query()->sole();
        $this->assertSame('requested', $deployment->status);
        $this->assertTrue($deployment->isActive());
        $this->assertStringContainsString('No se pudo confirmar', $deployment->error_message);

        $this->post(route('developer.deploy'), ['confirmation' => 'DESPLEGAR'])
            ->assertSessionHasErrors('deployment');
        $this->assertDatabaseCount('deployment_runs', 1);
        $this->assertCount(1, Http::recorded(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/dispatches')));
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

    public function test_failed_github_status_keeps_the_local_agent_diagnostic(): void
    {
        $developer = $this->developer();
        $deployment = DeploymentRun::query()->create([
            'request_id' => 'd062783e-3538-46ba-a56b-1c35e215ee04',
            'requested_by' => $developer->id,
            'ref' => 'main',
            'status' => 'in_progress',
            'error_message' => 'El agente local no pudo completar el despliegue.',
        ]);

        $this->workflowRuns = [[
            'id' => 12345,
            'display_title' => "Deploy production main [{$deployment->request_id}]",
            'status' => 'completed',
            'conclusion' => 'failure',
            'html_url' => 'https://github.com/Sciosdev/caope/actions/runs/12345',
            'head_sha' => str_repeat('a', 40),
        ]];

        $this->actingAs($developer)
            ->withSession($this->confirmedPasswordSession())
            ->get(route('developer.index'))
            ->assertOk()
            ->assertSeeText('El agente local no pudo completar el despliegue.');

        $this->assertDatabaseHas('deployment_runs', [
            'id' => $deployment->id,
            'conclusion' => 'failure',
            'error_message' => 'El agente local no pudo completar el despliegue.',
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

    public function test_old_missing_request_is_reconciled_consistently_for_screen_and_dispatch(): void
    {
        $this->healthyChecks();
        $this->dispatchBody = null;
        $this->dispatchStatus = 204;
        $developer = $this->developer();
        $deployment = DeploymentRun::query()->create([
            'request_id' => '60f3c860-81e5-4c91-92d5-acd2197a1a81',
            'ref' => 'main',
            'status' => 'requested',
        ]);
        $deployment->forceFill(['created_at' => now()->subHours(7)])->save();

        $this->actingAs($developer)->withSession($this->confirmedPasswordSession())
            ->getJson(route('developer.index'))
            ->assertOk()->assertJsonPath('active', false)->assertJsonPath('deploy_disabled', false);

        $this->post(route('developer.deploy'), ['confirmation' => 'DESPLEGAR'])
            ->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertDatabaseCount('deployment_runs', 2);
    }

    public function test_active_deployment_outside_visible_history_still_blocks_screen_and_dispatch(): void
    {
        $this->healthyChecks();
        $developer = $this->developer();
        $active = DeploymentRun::query()->create([
            'request_id' => '60f3c860-81e5-4c91-92d5-acd2197a1a81',
            'ref' => 'main',
            'status' => 'completed',
            'conclusion' => 'cancelled',
            'agent_status' => 'running',
            'agent_stage' => 'backup',
            'agent_heartbeat_at' => now(),
        ]);
        $active->forceFill(['created_at' => now()->subHours(7)])->save();
        for ($i = 0; $i < 21; $i++) {
            DeploymentRun::query()->create([
                'request_id' => (string) \Illuminate\Support\Str::uuid(),
                'ref' => 'main',
                'status' => 'completed',
                'conclusion' => 'success',
            ]);
        }

        $response = $this->actingAs($developer)->withSession($this->confirmedPasswordSession())
            ->getJson(route('developer.index'))
            ->assertOk()->assertJsonPath('active', true)->assertJsonPath('deploy_disabled', true);

        $this->assertStringContainsString('Creando respaldo', $response->json('progress'));
        $this->post(route('developer.deploy'), ['confirmation' => 'DESPLEGAR'])->assertSessionHasErrors('deployment');
        Http::assertNotSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/dispatches'));
    }

    public function test_live_status_contains_only_diagnostic_fragments_and_never_replaces_forms(): void
    {
        $this->healthyChecks();
        $response = $this->actingAs($this->developer())->withSession($this->confirmedPasswordSession())
            ->getJson(route('developer.index'))->assertOk()
            ->assertJsonStructure(['health', 'progress', 'history', 'deploy_disabled', 'active']);

        $this->assertStringContainsString('Versión instalada', $response->json('progress'));
        $this->assertStringNotContainsString('<form', $response->getContent());
        $this->assertStringNotContainsString('test-token', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_console_imports_durable_shell_recovery_before_presenting_remote_success(): void
    {
        $this->healthyChecks();
        $deployment = DeploymentRun::query()->create([
            'request_id' => '60f3c860-81e5-4c91-92d5-acd2197a1a81',
            'ref' => 'main',
            'status' => 'in_progress',
            'agent_status' => 'running',
        ]);
        $this->workflowRuns = [[
            'id' => 12345,
            'display_title' => "Deploy production main [{$deployment->request_id}]",
            'status' => 'completed',
            'conclusion' => 'success',
        ]];
        $path = storage_path('app/deployment/result.json');
        $original = File::exists($path) ? File::get($path) : null;
        File::ensureDirectoryExists(dirname($path));

        try {
            File::put($path, json_encode([
                'schema' => 1,
                'request_id' => $deployment->request_id,
                'finished_at' => now()->toIso8601String(),
                'status' => 'failed',
                'stage' => 'rollback',
                'restored' => true,
            ], JSON_THROW_ON_ERROR));

            $response = $this->actingAs($this->developer())->withSession($this->confirmedPasswordSession())
                ->getJson(route('developer.index'))->assertOk()->assertJsonPath('active', false);

            $this->assertSame('failed', $deployment->refresh()->agent_status);
            $this->assertStringContainsString('restaurada automáticamente', $response->json('history'));
            $this->assertStringContainsString('Falló en el servidor', $response->json('progress'));
        } finally {
            $original === null ? File::delete($path) : File::put($path, $original);
        }
    }

    private function healthyChecks(): void
    {
        $this->mock(DeveloperHealthService::class)->shouldReceive('run')->andReturn([
            ['id' => 'version', 'label' => 'Versión desplegada', 'status' => 'ok', 'summary' => 'abcdef123456', 'details' => null],
        ]);
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
