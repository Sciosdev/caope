<?php

namespace Tests\Feature\Developer;

use App\Models\DeploymentRun;
use App\Services\DeploymentAgentState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyTimeoutException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

class RunPendingDeploymentCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    private string $markerPath;

    private string $scriptPath;

    private string $recoveryPath;

    private string $resultPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markerPath = storage_path('app/deployment/expected.json');
        $this->logPath = storage_path('logs/developer-deploy.log');
        $this->scriptPath = storage_path('app/testing/deploy-scheduled.sh');
        $this->recoveryPath = storage_path('app/deployment/recovery.json');
        $this->resultPath = storage_path('app/deployment/result.json');
        File::delete([$this->markerPath, $this->logPath, $this->scriptPath, $this->recoveryPath, $this->resultPath]);
        File::ensureDirectoryExists(dirname($this->scriptPath));
        File::put($this->scriptPath, "#!/bin/sh\n");
        config([
            'app.env' => 'staging',
            'developer_console.deploy_script' => $this->scriptPath,
        ]);
    }

    protected function tearDown(): void
    {
        File::delete([$this->markerPath, $this->logPath, $this->scriptPath, $this->recoveryPath, $this->resultPath]);

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

    public function test_malformed_request_id_is_rejected_without_crashing_the_agent(): void
    {
        $this->writeMarker(now()->addMinutes(10)->timestamp);
        $marker = json_decode(File::get($this->markerPath), true);
        $marker['request_id'] = ['malformed'];
        File::put($this->markerPath, json_encode($marker));
        Process::fake();

        $this->artisan('caope:deploy-pending')->assertFailed();

        $this->assertFileDoesNotExist($this->markerPath);
        Process::assertNothingRan();
    }

    public function test_malformed_recovery_id_does_not_prevent_a_recovery_attempt(): void
    {
        $deployment = $this->deployment(['agent_status' => 'interrupted']);
        File::ensureDirectoryExists(dirname($this->recoveryPath));
        File::put($this->recoveryPath, json_encode(['request_id' => ['malformed']]));
        Process::fake(['*' => Process::result(output: 'CAOPE_RESULT=recovery_required', exitCode: 1)]);

        $this->artisan('caope:deploy-pending')->assertFailed();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['/bin/bash', $this->scriptPath, '--recover-only']);
        $this->assertSame('recovery_required', $deployment->refresh()->agent_status);
        $this->assertFileExists($this->recoveryPath);
    }

    public function test_command_runs_the_audited_deployment_script(): void
    {
        $this->writeMarker(now()->addMinutes(10)->timestamp);
        Process::fake([
            '*' => Process::result(output: "LISTO\nCAOPE_RESULT=succeeded"),
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
            && $process->environment['CAOPE_AGENT_REQUEST_ID'] === 'test-request'
            && $process->environment['CAOPE_AGENT_EXPECTED_SHA'] === str_repeat('a', 40)
            && $process->timeout === 2250);

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
        Process::fake(['*' => Process::result(output: "LISTO\nCAOPE_RESULT=succeeded")]);

        $this->artisan('caope:deploy-pending')->assertSuccessful();

        Process::assertRan(fn (PendingProcess $process): bool => $process->environment['CAOPE_REQUIRE_CLEAN_CHECKOUT'] === '1'
            && $process->environment['CAOPE_SECURITY_PROFILE'] === 'production');
    }

    public function test_progress_and_completion_are_recorded_without_waiting_for_github(): void
    {
        $deployment = $this->deployment();
        $this->writeMarker(now()->addMinutes(30)->timestamp, $deployment->request_id);
        Process::fake(['*' => Process::describe()->output([
            'CAOPE_STAGE=preflight',
            'CAOPE_STAGE=backup',
            'CAOPE_STAGE=unknown_untrusted_stage',
            'CAOPE_STAGE=completed',
            'CAOPE_RESULT=succeeded',
        ])]);

        $this->artisan('caope:deploy-pending')->assertSuccessful();

        $deployment->refresh();
        $this->assertSame('succeeded', $deployment->agent_status);
        $this->assertSame('completed', $deployment->agent_stage);
        $this->assertNotNull($deployment->agent_started_at);
        $this->assertNotNull($deployment->agent_finished_at);
        $this->assertSame('in_progress', $deployment->status);
        $this->assertFileDoesNotExist($this->markerPath);
    }

    public function test_timeout_is_recorded_and_next_tick_recovers_without_replaying_authorization(): void
    {
        $deployment = $this->deployment();
        $this->writeMarker(now()->addMinutes(30)->timestamp, $deployment->request_id);
        Process::fake(function () {
            throw new ProcessTimedOutException(
                new SymfonyTimeoutException(new SymfonyProcess(['fake-deployment'], timeout: 2250), SymfonyTimeoutException::TYPE_GENERAL),
                Process::result(errorOutput: 'ERROR: Tiempo agotado.', exitCode: 137),
            );
        });

        $this->artisan('caope:deploy-pending')->assertFailed();
        $this->assertSame('interrupted', $deployment->refresh()->agent_status);
        $this->assertStringContainsString('tiempo máximo', $deployment->error_message);
        $this->assertFileExists($this->logPath);
        $this->assertFileDoesNotExist($this->markerPath);

        Process::fake(['*' => Process::result(output: 'CAOPE_RECOVERY=not_needed')]);
        $this->artisan('caope:deploy-pending')->assertSuccessful();
        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['/bin/bash', $this->scriptPath, '--recover-only']);
        $this->assertSame('failed', $deployment->refresh()->agent_status);
    }

    public function test_busy_lock_preserves_running_attempt_and_authorization(): void
    {
        $deployment = $this->deployment(['agent_status' => 'running', 'agent_stage' => 'backup']);
        $this->writeMarker(now()->addMinutes(30)->timestamp, $deployment->request_id);
        Process::fake(['*' => Process::result(exitCode: 75)]);

        $this->artisan('caope:deploy-pending')->assertSuccessful();

        $this->assertSame('running', $deployment->refresh()->agent_status);
        $this->assertSame('backup', $deployment->agent_stage);
        $this->assertNull($deployment->error_message);
        $this->assertFileExists($this->markerPath);
    }

    public function test_recovery_probe_preserves_an_attempt_that_completed_while_acquiring_the_lock(): void
    {
        $deployment = $this->deployment(['agent_status' => 'running']);
        Process::fake(function () use ($deployment) {
            $this->writeResult($deployment->request_id, 'succeeded');
            // The web console may have imported the outcome first.
            app(DeploymentAgentState::class)->synchronizeResult();

            return Process::result(output: 'CAOPE_RECOVERY=not_needed');
        });

        $this->artisan('caope:deploy-pending')->assertSuccessful();

        $this->assertSame('succeeded', $deployment->refresh()->agent_status);
        $this->assertNull($deployment->error_message);
        $this->assertNotNull($deployment->agent_result_key);
    }

    public function test_changed_authorization_is_preserved_for_the_next_tick(): void
    {
        $deployment = $this->deployment();
        $this->writeMarker(now()->addMinutes(30)->timestamp, $deployment->request_id);
        Process::fake(function () {
            $this->writeMarker(now()->addMinutes(30)->timestamp, 'replacement-request');

            return Process::result(output: 'CAOPE_AUTHORIZATION=changed', exitCode: 75);
        });

        $this->artisan('caope:deploy-pending')->assertSuccessful();

        $this->assertNull($deployment->refresh()->agent_status);
        $this->assertNull($deployment->error_message);
        $this->assertSame('replacement-request', json_decode(File::get($this->markerPath), true)['request_id']);
        Process::assertRan(fn (PendingProcess $process): bool => $process->environment['CAOPE_AGENT_REQUEST_ID'] === $deployment->request_id);
    }

    public function test_recovery_journal_is_processed_even_after_authorization_expires(): void
    {
        $deployment = $this->deployment(['agent_status' => 'interrupted']);
        $this->writeMarker(now()->subMinute()->timestamp, $deployment->request_id);
        File::put($this->recoveryPath, json_encode(['request_id' => $deployment->request_id]));
        Process::fake(function () use ($deployment) {
            File::delete($this->recoveryPath);
            $this->writeResult($deployment->request_id, 'failed', ['restored' => true, 'stage' => 'rollback']);

            return Process::result(output: "CAOPE_RESULT=failed\nRollback automático completado.");
        });

        $this->artisan('caope:deploy-pending')->assertSuccessful();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['/bin/bash', $this->scriptPath, '--recover-only']);
        $this->assertSame('failed', $deployment->refresh()->agent_status);
        $this->assertStringContainsString('restaurada automáticamente', $deployment->error_message);
        $this->assertFileDoesNotExist($this->markerPath);
    }

    public function test_failed_recovery_stays_blocking_even_if_github_has_finished(): void
    {
        $deployment = $this->deployment(['status' => 'completed', 'conclusion' => 'failure', 'agent_status' => 'interrupted']);
        File::ensureDirectoryExists(dirname($this->recoveryPath));
        File::put($this->recoveryPath, json_encode(['request_id' => $deployment->request_id]));
        Process::fake(['*' => Process::result(output: 'CAOPE_RESULT=recovery_required', exitCode: 1)]);

        $this->artisan('caope:deploy-pending')->assertFailed();

        $this->assertSame('recovery_required', $deployment->refresh()->agent_status);
        $this->assertTrue($deployment->isActive());
        $this->assertSame(1, DeploymentRun::blocking()->count());
        $this->assertFileExists($this->recoveryPath);
    }

    public function test_result_written_before_laravel_boots_is_imported_without_another_deployment(): void
    {
        $deployment = $this->deployment(['agent_status' => 'interrupted']);
        $this->writeResult($deployment->request_id, 'failed', ['restored' => true, 'stage' => 'rollback']);
        Process::fake();

        $this->artisan('caope:deploy-pending')->assertSuccessful();

        Process::assertNothingRan();
        $this->assertSame('failed', $deployment->refresh()->agent_status);
        $this->assertStringContainsString('restaurada automáticamente', $deployment->error_message);
    }

    public function test_older_outcome_cannot_replace_a_newer_successful_recovery(): void
    {
        $deployment = $this->deployment(['agent_status' => 'failed', 'agent_finished_at' => now()]);
        $this->writeResult($deployment->request_id, 'recovery_required', ['finished_at' => now()->subMinute()->toIso8601String()]);

        $this->assertNull(app(DeploymentAgentState::class)->synchronizeResult());
        $this->assertSame('failed', $deployment->refresh()->agent_status);
    }

    public function test_terminal_attempt_cannot_be_restarted_by_a_replayed_marker(): void
    {
        $deployment = $this->deployment(['agent_status' => 'failed']);
        $this->writeMarker(now()->addMinutes(30)->timestamp, $deployment->request_id);
        Process::fake();

        $this->artisan('caope:deploy-pending')->assertSuccessful();

        Process::assertNothingRan();
        $this->assertFileDoesNotExist($this->markerPath);
    }

    public function test_exit_zero_without_positive_confirmation_is_not_success(): void
    {
        $deployment = $this->deployment();
        $this->writeMarker(now()->addMinutes(30)->timestamp, $deployment->request_id);
        Process::fake(['*' => Process::result(output: 'unexpected empty outcome')]);

        $this->artisan('caope:deploy-pending')->assertFailed();

        $this->assertSame('interrupted', $deployment->refresh()->agent_status);
        $this->assertStringContainsString('sin confirmar', $deployment->error_message);
        $this->assertFileDoesNotExist($this->markerPath);
    }

    public function test_unverifiable_legacy_lock_is_visible_without_discarding_authorization(): void
    {
        $deployment = $this->deployment();
        $this->writeMarker(now()->addMinutes(30)->timestamp, $deployment->request_id);
        Process::fake(['*' => Process::result(errorOutput: 'ERROR: Bloqueo heredado sin propietario verificable.', exitCode: 75)]);

        $this->artisan('caope:deploy-pending')->assertSuccessful();

        $this->assertStringContainsString('bloqueo previo', $deployment->refresh()->error_message);
        $this->assertNull($deployment->agent_status);
        $this->assertFileExists($this->markerPath);
    }

    private function deployment(array $attributes = []): DeploymentRun
    {
        return DeploymentRun::query()->create(array_merge([
            'request_id' => 'a565b30f-e95a-460a-9dc1-69315676b426',
            'ref' => 'main',
            'status' => 'in_progress',
        ], $attributes));
    }

    private function writeResult(string $requestId, string $status, array $attributes = []): void
    {
        File::ensureDirectoryExists(dirname($this->resultPath));
        File::put($this->resultPath, json_encode(array_merge([
            'schema' => 1,
            'request_id' => $requestId,
            'status' => $status,
            'stage' => 'completed',
            'finished_at' => now()->toIso8601String(),
            'restored' => false,
        ], $attributes), JSON_THROW_ON_ERROR));
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
