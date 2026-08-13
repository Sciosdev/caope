<?php

namespace Tests\Feature\Developer;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeploymentVersionTest extends TestCase
{
    private string $markerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markerPath = storage_path('app/deployment/version.json');
        File::delete($this->markerPath);
    }

    protected function tearDown(): void
    {
        File::delete($this->markerPath);

        parent::tearDown();
    }

    public function test_version_endpoint_is_unavailable_before_first_deployment(): void
    {
        $this->getJson(route('api.deployment.version'))->assertNotFound();
    }

    public function test_version_endpoint_returns_only_validated_deployment_metadata(): void
    {
        File::ensureDirectoryExists(dirname($this->markerPath));
        File::put($this->markerPath, json_encode([
            'sha' => str_repeat('A', 40),
            'deployed_at' => '2026-08-13T07:30:00Z',
            'request_id' => 'internal-value',
        ], JSON_THROW_ON_ERROR));

        $this->getJson(route('api.deployment.version'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson([
                'sha' => str_repeat('a', 40),
                'deployed_at' => '2026-08-13T07:30:00Z',
            ]);
    }

    public function test_version_endpoint_rejects_an_invalid_marker(): void
    {
        File::ensureDirectoryExists(dirname($this->markerPath));
        File::put($this->markerPath, '{"sha":"not-a-commit"}');

        $this->getJson(route('api.deployment.version'))->assertStatus(503);
    }
}
