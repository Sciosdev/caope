<?php

namespace Tests\Feature\Developer;

use App\Models\DeploymentRun;
use App\Services\GitHubDeploymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeploymentSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'developer_console.github.repository' => 'Sciosdev/caope',
            'developer_console.github.workflow' => 'deploy.yml',
            'developer_console.github.ref' => 'main',
            'developer_console.github.token' => 'test-token',
        ]);
        Http::preventStrayRequests();
    }

    public function test_old_known_run_is_queried_directly_when_outside_recent_history(): void
    {
        $deployment = $this->deployment(['workflow_run_id' => 12345, 'status' => 'waiting']);
        Http::fake([
            '*/actions/workflows/deploy.yml/runs*' => Http::response(['workflow_runs' => []]),
            '*/actions/runs/12345' => Http::response($this->githubRun($deployment, 'completed', 'cancelled')),
        ]);

        $this->assertNull(app(GitHubDeploymentService::class)->synchronize());
        $this->assertFalse($deployment->refresh()->isActive());
        $this->assertSame('cancelled', $deployment->conclusion);
        Http::assertSentCount(2);
    }

    public function test_old_known_run_is_never_unblocked_on_remote_error(): void
    {
        $deployment = $this->deployment(['workflow_run_id' => 12345, 'status' => 'waiting']);
        Http::fake([
            '*/actions/workflows/deploy.yml/runs*' => Http::response(['workflow_runs' => []]),
            '*/actions/runs/12345' => Http::response([], 503),
        ]);

        $this->assertNotNull(app(GitHubDeploymentService::class)->synchronize());
        $this->assertTrue($deployment->refresh()->isActive());
        $this->assertSame('waiting', $deployment->status);
    }

    public function test_missing_dispatch_expires_only_after_successful_exhaustive_lookup(): void
    {
        $deployment = $this->deployment();
        Http::fake(['*' => Http::response(['workflow_runs' => [], 'total_count' => 0])]);

        $this->assertNull(app(GitHubDeploymentService::class)->synchronize());
        $this->assertFalse($deployment->refresh()->isActive());
        $this->assertSame('failed_to_dispatch', $deployment->status);
        Http::assertSent(fn (Request $request): bool => isset($request['created']) && (int) $request['page'] === 1);
    }

    public function test_incomplete_history_does_not_expire_a_missing_dispatch(): void
    {
        $deployment = $this->deployment();
        Http::fake(function (Request $request) {
            if (! isset($request['created'])) {
                return Http::response(['workflow_runs' => []]);
            }
            if ((int) $request['page'] === 1) {
                return Http::response(['workflow_runs' => array_fill(0, 100, ['id' => 2]), 'total_count' => 101]);
            }

            return Http::response([], 503);
        });

        $this->assertNotNull(app(GitHubDeploymentService::class)->synchronize());
        $this->assertTrue($deployment->refresh()->isActive());
        $this->assertSame('requested', $deployment->status);
    }

    public function test_dispatch_found_on_later_page_stays_active(): void
    {
        $deployment = $this->deployment();
        Http::fake(function (Request $request) use ($deployment) {
            if (! isset($request['created'])) {
                return Http::response(['workflow_runs' => []]);
            }

            return Http::response([
                'workflow_runs' => (int) $request['page'] === 1
                    ? array_fill(0, 100, ['id' => 2])
                    : [$this->githubRun($deployment, 'waiting')],
                'total_count' => 101,
            ]);
        });

        $this->assertNull(app(GitHubDeploymentService::class)->synchronize());
        $this->assertTrue($deployment->refresh()->isActive());
        $this->assertSame(12345, $deployment->workflow_run_id);
        $this->assertSame('waiting', $deployment->status);
    }

    public function test_github_success_never_erases_a_failed_recovery(): void
    {
        $deployment = $this->deployment([
            'agent_status' => 'recovery_required',
            'agent_stage' => 'rollback',
            'error_message' => 'No se pudo verificar la recuperación.',
        ]);
        Http::fake(['*' => Http::response(['workflow_runs' => [$this->githubRun($deployment, 'completed', 'success')]])]);

        $this->assertNull(app(GitHubDeploymentService::class)->synchronize());
        $this->assertTrue($deployment->refresh()->isActive());
        $this->assertSame('recovery_required', $deployment->agent_status);
        $this->assertSame('No se pudo verificar la recuperación.', $deployment->error_message);
        $this->assertSame('Recuperación incompleta', $deployment->statusLabel());
    }

    public function test_running_agent_blocks_even_after_github_cancellation(): void
    {
        $deployment = $this->deployment(['agent_status' => 'running', 'agent_heartbeat_at' => now()]);
        Http::fake(['*' => Http::response(['workflow_runs' => [$this->githubRun($deployment, 'completed', 'cancelled')]])]);

        app(GitHubDeploymentService::class)->synchronize();
        $this->assertTrue($deployment->refresh()->isActive());
        $this->assertSame('running', $deployment->agent_status);
    }

    public function test_agent_failure_is_not_revived_by_a_late_in_progress_response(): void
    {
        $deployment = $this->deployment(['agent_status' => 'timed_out', 'error_message' => 'Tiempo agotado.']);
        Http::fake(['*' => Http::response(['workflow_runs' => [$this->githubRun($deployment, 'in_progress')]])]);

        app(GitHubDeploymentService::class)->synchronize();
        $this->assertSame('timed_out', $deployment->refresh()->agent_status);
        $this->assertSame('Tiempo agotado.', $deployment->error_message);
        $this->assertTrue($deployment->isActive(), 'Wait for GitHub to stop before retrying.');
        $this->assertSame('Tiempo agotado', $deployment->statusLabel());
    }

    /** @param array<string, mixed> $attributes */
    private function deployment(array $attributes = []): DeploymentRun
    {
        $deployment = DeploymentRun::query()->create([
            'request_id' => (string) Str::uuid(),
            'ref' => 'main',
            'status' => 'requested',
            ...$attributes,
        ]);
        $deployment->forceFill(['created_at' => now()->subHours(7)])->save();

        return $deployment;
    }

    /** @return array<string, mixed> */
    private function githubRun(DeploymentRun $deployment, string $status, ?string $conclusion = null): array
    {
        return [
            'id' => 12345,
            'display_title' => "Deploy production main [{$deployment->request_id}]",
            'status' => $status,
            'conclusion' => $conclusion,
            'html_url' => 'https://github.com/Sciosdev/caope/actions/runs/12345',
            'head_sha' => str_repeat('a', 40),
        ];
    }
}
