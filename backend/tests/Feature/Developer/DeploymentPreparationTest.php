<?php

namespace Tests\Feature\Developer;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeploymentPreparationTest extends TestCase
{
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
}
