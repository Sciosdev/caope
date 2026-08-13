<?php

namespace Tests\Feature\Developer;

use App\Models\DeploymentRun;
use App\Models\User;
use App\Services\DeveloperConsoleSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeploymentPreparationTest extends TestCase
{
    use RefreshDatabase;

    private string $markerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markerPath = storage_path('app/deployment/expected.json');
        File::delete($this->markerPath);
        config(['developer_console.deploy_webhook_token' => 'shared-test-token']);
    }

    protected function tearDown(): void
    {
        File::delete($this->markerPath);
        File::delete(storage_path('app/testing/production-settings.enc'));

        parent::tearDown();
    }

    public function test_preparation_endpoint_is_hidden_when_not_configured(): void
    {
        config(['developer_console.deploy_webhook_token' => '']);

        $this->postJson(route('api.deployment.prepare'), [
            'sha' => str_repeat('a', 40),
            'request_id' => 'manual',
        ])->assertNotFound();
    }

    public function test_preparation_endpoint_rejects_an_invalid_token(): void
    {
        $this->withToken('wrong-token')
            ->postJson(route('api.deployment.prepare'), [
                'sha' => str_repeat('a', 40),
                'request_id' => 'manual',
            ])
            ->assertForbidden();

        $this->assertFileDoesNotExist($this->markerPath);
    }

    public function test_preparation_endpoint_records_a_short_lived_exact_revision(): void
    {
        $before = now()->addMinutes(29)->timestamp;

        $this->withToken('shared-test-token')
            ->postJson(route('api.deployment.prepare'), [
                'sha' => str_repeat('A', 40),
                'request_id' => 'd062783e-3538-46ba-a56b-1c35e215ee04',
            ])
            ->assertAccepted()
            ->assertExactJson(['accepted' => true]);

        $marker = json_decode((string) File::get($this->markerPath), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(str_repeat('a', 40), $marker['sha']);
        $this->assertSame('d062783e-3538-46ba-a56b-1c35e215ee04', $marker['request_id']);
        $this->assertGreaterThanOrEqual($before, $marker['expires_at']);
    }

    public function test_preparation_endpoint_validates_the_revision(): void
    {
        $this->withToken('shared-test-token')
            ->postJson(route('api.deployment.prepare'), [
                'sha' => 'main',
                'request_id' => 'manual',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sha');
    }

    public function test_production_preparation_attests_the_exact_github_run_without_a_shared_secret(): void
    {
        config([
            'developer_console.deploy_webhook_token' => 'legacy-token-must-not-take-precedence',
            'developer_console.settings_path' => storage_path('app/testing/production-settings.enc'),
        ]);
        File::delete(config('developer_console.settings_path'));
        app(DeveloperConsoleSettings::class)->storeProduction('encrypted-github-token', 199102136, 1);

        $deployment = DeploymentRun::query()->create([
            'request_id' => 'd062783e-3538-46ba-a56b-1c35e215ee04',
            'requested_by' => User::factory()->create()->id,
            'ref' => 'main',
            'status' => 'requested',
        ]);
        $sha = str_repeat('a', 40);

        Http::fake([
            'api.github.com/repos/Sciosdev/caope/actions/runs/12345' => Http::response([
                'id' => 12345,
                'workflow_id' => 199102136,
                'event' => 'workflow_dispatch',
                'head_branch' => 'main',
                'head_sha' => $sha,
                'run_attempt' => 1,
                'display_title' => "Deploy production main [{$deployment->request_id}]",
                'path' => '.github/workflows/deploy.yml',
                'status' => 'in_progress',
                'conclusion' => null,
                'html_url' => 'https://github.com/Sciosdev/caope/actions/runs/12345',
            ]),
            'api.github.com/repos/Sciosdev/caope/actions/runs/12345/attempts/1/jobs*' => Http::response([
                'jobs' => [
                    ['name' => 'Validate release', 'status' => 'completed', 'conclusion' => 'success'],
                    ['name' => 'Deploy to production', 'status' => 'in_progress', 'conclusion' => null],
                ],
            ]),
        ]);

        $this->postJson(route('api.deployment.prepare'), [
            'sha' => $sha,
            'request_id' => $deployment->request_id,
            'run_id' => 12345,
            'run_attempt' => 1,
        ])->assertAccepted()->assertExactJson(['accepted' => true]);

        $deployment->refresh();
        $this->assertSame('in_progress', $deployment->status);
        $this->assertSame(12345, $deployment->workflow_run_id);
        $this->assertSame($sha, $deployment->commit_sha);
        $this->assertSame('Bearer encrypted-github-token', Http::recorded()[0][0]->header('Authorization')[0] ?? null);

        File::delete($this->markerPath);
        File::put(storage_path('app/deployment/version.json'), json_encode([
            'sha' => $sha,
            'request_id' => $deployment->request_id,
            'deployed_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        $this->postJson(route('api.deployment.prepare'), [
            'sha' => $sha,
            'request_id' => $deployment->request_id,
            'run_id' => 12345,
            'run_attempt' => 1,
        ])->assertAccepted();

        $this->assertFileDoesNotExist($this->markerPath);
        Http::assertSentCount(4);

        File::delete(storage_path('app/deployment/version.json'));
        File::delete(config('developer_console.settings_path'));
    }

    public function test_production_preparation_rejects_an_unrelated_run_before_writing_marker(): void
    {
        config([
            'developer_console.deploy_webhook_token' => '',
            'developer_console.settings_path' => storage_path('app/testing/production-settings.enc'),
        ]);
        File::delete(config('developer_console.settings_path'));
        app(DeveloperConsoleSettings::class)->storeProduction('encrypted-github-token', 199102136, 1);

        DeploymentRun::query()->create([
            'request_id' => 'd062783e-3538-46ba-a56b-1c35e215ee04',
            'requested_by' => User::factory()->create()->id,
            'ref' => 'main',
            'status' => 'requested',
        ]);

        Http::fake([
            '*' => Http::response([
                'id' => 12345,
                'workflow_id' => 999,
                'event' => 'workflow_dispatch',
            ]),
        ]);

        $this->postJson(route('api.deployment.prepare'), [
            'sha' => str_repeat('a', 40),
            'request_id' => 'd062783e-3538-46ba-a56b-1c35e215ee04',
            'run_id' => 12345,
            'run_attempt' => 1,
        ])->assertForbidden();

        $this->assertFileDoesNotExist($this->markerPath);
        File::delete(config('developer_console.settings_path'));
    }
}
