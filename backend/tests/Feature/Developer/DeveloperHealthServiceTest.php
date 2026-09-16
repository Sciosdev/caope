<?php

namespace Tests\Feature\Developer;

use App\Services\DeveloperHealthService;
use App\Services\QueueWorkerHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class DeveloperHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'database',
            'queue.connections.database.queue' => 'default',
            'developer_console.github.api_url' => 'https://api.github.com',
            'developer_console.github.repository' => 'Sciosdev/caope',
            'developer_console.github.workflow' => 'deploy.yml',
            'developer_console.github.token' => 'test-token',
            'developer_console.settings_path' => storage_path('framework/testing/nonexistent-health-settings.enc'),
        ]);

        Http::preventStrayRequests();
        $this->freezeTime();
    }

    public function test_empty_configured_queue_does_not_prove_a_worker_is_running(): void
    {
        $result = $this->check('queue');

        $this->assertSame('warning', $result['status']);
        $this->assertStringContainsString('No se ha comprobado actividad reciente', $result['summary']);
    }

    public function test_idle_worker_event_proves_recent_activity_for_each_served_queue(): void
    {
        Event::dispatch(new Looping('database', 'high, default'));

        $this->assertSame('ok', $this->check('queue')['status']);
        $this->assertNotNull(app(QueueWorkerHeartbeat::class)->lastSeen('database', 'high'));
    }

    public function test_worker_for_another_connection_or_queue_is_not_used_as_evidence(): void
    {
        Event::dispatch(new Looping('redis', 'default'));
        Event::dispatch(new Looping('database', 'notifications'));

        $this->assertSame('warning', $this->check('queue')['status']);
    }

    public function test_stale_worker_signal_is_not_reported_as_healthy(): void
    {
        Event::dispatch(new Looping('database', 'default'));
        $this->travel(4)->minutes();

        $result = $this->check('queue');

        $this->assertSame('warning', $result['status']);
        $this->assertStringContainsString('Última señal:', $result['details']);
    }

    public function test_worker_throttles_heartbeat_writes_but_resumes_after_thirty_seconds(): void
    {
        Event::dispatch(new Looping('database', 'default'));
        $heartbeat = app(QueueWorkerHeartbeat::class);
        $first = $heartbeat->lastSeen('database', 'default');

        $this->travel(20)->seconds();
        Event::dispatch(new Looping('database', 'default'));
        $this->assertTrue($first->equalTo($heartbeat->lastSeen('database', 'default')));

        $this->travel(11)->seconds();
        Event::dispatch(new Looping('database', 'default'));
        $this->assertTrue($first->lt($heartbeat->lastSeen('database', 'default')));
    }

    public function test_heartbeat_failure_does_not_interrupt_the_worker(): void
    {
        Cache::shouldReceive('put')->once()->andThrow(new \RuntimeException('Cache unavailable'));

        Event::dispatch(new Looping('database', 'default'));
        Event::dispatch(new Looping('database', 'default'));

        $this->assertTrue(true, 'The queue loop continued even though telemetry storage failed.');
    }

    public function test_sync_fallback_does_not_impersonate_an_independent_worker(): void
    {
        Event::dispatch(new JobProcessed('database', new SyncJob(app(), '{}', 'database', 'default')));

        $this->assertNull(app(QueueWorkerHeartbeat::class)->lastSeen('database', 'default'));
        $this->assertSame('warning', $this->check('queue')['status']);
    }

    public function test_github_uses_actual_workflow_read_and_caches_the_result_for_one_minute(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['id' => 123, 'state' => 'active'])]);

        $first = $this->check('githubDeployment');
        $this->assertSame('ok', $first['status']);
        $this->assertStringContainsString('permiso para ejecutarlo se valida', $first['details']);
        $this->assertSame($first, $this->check('githubDeployment'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->url() === 'https://api.github.com/repos/Sciosdev/caope/actions/workflows/deploy.yml'
            && $request->hasHeader('Authorization', 'Bearer test-token'));

        $this->travel(61)->seconds();
        $this->check('githubDeployment');
        Http::assertSentCount(2);
    }

    public function test_revoked_credentials_are_not_reported_as_healthy_or_disclosed(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Private sensitive response'], 401)]);

        $result = $this->check('githubDeployment');

        $this->assertSame('warning', $result['status']);
        $this->assertStringContainsString('Actualiza el token', $result['summary']);
        $this->assertStringNotContainsString('test-token', json_encode($result));
        $this->assertStringNotContainsString('Private sensitive response', json_encode($result));
    }

    public function test_disabled_workflow_does_not_look_ready(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['id' => 123, 'state' => 'disabled_manually'])]);

        $this->assertSame('warning', $this->check('githubDeployment')['status']);
    }

    public function test_network_failures_are_bounded_cached_and_sanitized(): void
    {
        $requestOptions = [];
        Http::fake(function ($request, array $options) use (&$requestOptions) {
            $requestOptions = $options;
            throw new ConnectionException('Secret test-token diagnostic');
        });

        $result = $this->check('githubDeployment');
        $this->assertSame(3, $requestOptions['connect_timeout']);
        $this->assertSame(5, $requestOptions['timeout']);
        $this->assertSame('warning', $result['status']);
        $this->assertStringNotContainsString('test-token', json_encode($result));
        Http::fake(['api.github.com/*' => Http::response(['id' => 123, 'state' => 'active'])]);
        $this->assertSame($result, $this->check('githubDeployment'));
        Http::assertNothingSent();
    }

    public function test_rotating_credentials_rechecks_immediately_instead_of_reusing_old_failure(): void
    {
        Http::fakeSequence()
            ->push([], 401)
            ->push(['id' => 123, 'state' => 'active']);

        $this->assertSame('warning', $this->check('githubDeployment')['status']);
        config(['developer_console.github.token' => 'replacement-token']);
        $this->assertSame('ok', $this->check('githubDeployment')['status']);
        Http::assertSentCount(2);
    }

    /** @return array<string, mixed> */
    private function check(string $method): array
    {
        return (new ReflectionMethod(DeveloperHealthService::class, $method))
            ->invoke(app(DeveloperHealthService::class));
    }
}
