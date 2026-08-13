<?php

namespace Tests\Feature\Developer;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class RunPendingDeploymentCommandTest extends TestCase
{
    private string $logPath;

    private string $markerPath;

    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markerPath = storage_path('app/deployment/expected.json');
        $this->logPath = storage_path('logs/developer-deploy.log');
        $this->scriptPath = storage_path('app/testing/deploy-cpanel-staging.sh');
        File::delete([$this->markerPath, $this->logPath, $this->scriptPath]);
        File::ensureDirectoryExists(dirname($this->scriptPath));
        File::put($this->scriptPath, "#!/bin/sh\n");
        config(['developer_console.deploy_script' => $this->scriptPath]);
    }

    protected function tearDown(): void
    {
        File::delete([$this->markerPath, $this->logPath, $this->scriptPath]);

        parent::tearDown();
    }

    public function test_command_does_nothing_without_an_authorized_revision(): void
    {
        Process::fake();

        $this->artisan('caope:deploy-pending')->assertSuccessful();

        Process::assertNothingRan();
    }

    public function test_command_removes_an_expired_authorization(): void
    {
        $this->writeMarker(now()->subMinute()->timestamp);
        Process::fake();

        $this->artisan('caope:deploy-pending')
            ->expectsOutputToContain('autorización de despliegue vencida')
            ->assertFailed();

        $this->assertFileDoesNotExist($this->markerPath);
        Process::assertNothingRan();
    }

    public function test_command_runs_the_audited_deployment_script(): void
    {
        $this->writeMarker(now()->addMinutes(10)->timestamp);
        Process::fake([
            '*' => Process::result(output: 'LISTO'),
        ]);

        $this->artisan('caope:deploy-pending')
            ->expectsOutputToContain('terminó correctamente')
            ->assertSuccessful();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['/bin/bash', $this->scriptPath]
            && $process->path === dirname(base_path())
            && $process->environment['GIT_TERMINAL_PROMPT'] === '0'
            && $process->timeout === 1800);

        $this->assertStringContainsString('LISTO', (string) File::get($this->logPath));
        $this->assertStringContainsString('Código de salida: 0', (string) File::get($this->logPath));
    }

    public function test_scheduler_registers_the_pending_deployment_command(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('caope:deploy-pending')
            ->assertSuccessful();
    }

    private function writeMarker(int $expiresAt): void
    {
        File::ensureDirectoryExists(dirname($this->markerPath));
        File::put($this->markerPath, json_encode([
            'sha' => str_repeat('a', 40),
            'request_id' => 'test-request',
            'expires_at' => $expiresAt,
        ], JSON_THROW_ON_ERROR));
    }
}
