<?php

namespace Tests\Feature\Developer;

use App\Models\DeploymentRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class RunPendingDeploymentCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    private string $markerPath;

    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markerPath = storage_path('app/deployment/expected.json');
        $this->logPath = storage_path('logs/developer-deploy.log');
        $this->scriptPath = storage_path('app/testing/deploy-scheduled.sh');
        File::delete([$this->markerPath, $this->logPath, $this->scriptPath]);
        File::ensureDirectoryExists(dirname($this->scriptPath));
        File::put($this->scriptPath, "#!/bin/sh\n");
        config([
            'app.env' => 'staging',
            'developer_console.deploy_script' => $this->scriptPath,
        ]);
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
            ->expectsOutputToContain('autorización de despliegue inválida o vencida')
            ->assertFailed();

        $this->assertFileDoesNotExist($this->markerPath);
        Process::assertNothingRan();
    }

    public function test_command_removes_an_authorization_without_a_valid_request_id(): void
    {
        $this->writeMarker(now()->addMinutes(10)->timestamp, '');
        Process::fake();

        $this->artisan('caope:deploy-pending')
            ->expectsOutputToContain('autorización de despliegue inválida o vencida')
            ->assertFailed();

        $this->assertFileDoesNotExist($this->markerPath);
        Process::assertNothingRan();
    }

    public function test_command_removes_an_authorization_without_an_exact_sha(): void
    {
        $this->writeMarker(now()->addMinutes(10)->timestamp, 'test-request', 'main');
        Process::fake();

        $this->artisan('caope:deploy-pending')
            ->expectsOutputToContain('autorización de despliegue inválida o vencida')
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
            && $process->environment['CAOPE_PHP_BIN'] === PHP_BINARY
            && $process->environment['CAOPE_REQUIRE_CLEAN_CHECKOUT'] === '0'
            && $process->environment['CAOPE_SECURITY_PROFILE'] === 'staging'
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

    public function test_local_failure_is_visible_in_the_audited_deployment_history(): void
    {
        $requestId = 'd062783e-3538-46ba-a56b-1c35e215ee04';
        DeploymentRun::query()->create([
            'request_id' => $requestId,
            'ref' => 'main',
            'status' => 'in_progress',
        ]);
        $this->writeMarker(now()->addMinutes(10)->timestamp, $requestId);
        Process::fake([
            '*' => Process::result(
                errorOutput: "ERROR: Composer no pudo instalar las dependencias.\nRollback automático completado.",
                exitCode: 100,
            ),
        ]);

        $this->artisan('caope:deploy-pending')->assertFailed();

        $deployment = DeploymentRun::query()->where('request_id', $requestId)->sole();
        $this->assertStringContainsString('código 100', (string) $deployment->error_message);
        $this->assertStringContainsString('Composer no pudo instalar', (string) $deployment->error_message);
        $this->assertStringContainsString('versión anterior fue restaurada', (string) $deployment->error_message);
    }

    public function test_production_never_downgrades_when_encrypted_console_settings_are_absent(): void
    {
        config(['app.env' => 'production']);
        $this->writeMarker(now()->addMinutes(10)->timestamp);
        Process::fake(['*' => Process::result(output: 'LISTO')]);

        $this->artisan('caope:deploy-pending')->assertSuccessful();

        Process::assertRan(fn (PendingProcess $process): bool => $process->environment['CAOPE_REQUIRE_CLEAN_CHECKOUT'] === '1'
            && $process->environment['CAOPE_SECURITY_PROFILE'] === 'production');
    }

    private function writeMarker(
        int $expiresAt,
        string $requestId = 'test-request',
        string $sha = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
    ): void {
        File::ensureDirectoryExists(dirname($this->markerPath));
        File::put($this->markerPath, json_encode([
            'sha' => $sha,
            'request_id' => $requestId,
            'expires_at' => $expiresAt,
        ], JSON_THROW_ON_ERROR));
    }
}
