<?php

namespace Tests\Feature\Developer;

use App\Models\DeploymentRun;
use App\Services\DeploymentAgentState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeploymentAgentStateTest extends TestCase
{
    use RefreshDatabase;

    private string $isolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->isolatedStorage = storage_path('framework/testing/deployment-state-'.Str::uuid());
        $this->app->useStoragePath($this->isolatedStorage);
        File::ensureDirectoryExists(storage_path('app/deployment'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->isolatedStorage);
        parent::tearDown();
    }

    public function test_consumed_result_does_not_overwrite_new_recovery_progress_or_diagnostic(): void
    {
        $deployment = $this->deployment();
        $this->writeResult($deployment, ['status' => 'recovery_required', 'stage' => 'rollback']);
        $state = app(DeploymentAgentState::class);

        $this->assertNotNull($state->synchronizeResult());
        $key = $deployment->fresh()->agent_result_key;
        $this->assertSame(64, strlen($key));
        $this->travel(1)->minutes();
        $this->write('recovery', ['schema' => 1, 'request_id' => $deployment->request_id, 'phase' => 'rollback']);
        $state->progress($deployment->request_id, 'rollback');
        $deployment->refresh()->update(['error_message' => 'Diagnóstico más reciente del agente.']);

        $this->assertNull($state->synchronizeResult());
        $deployment->refresh();
        $this->assertSame('running', $deployment->agent_status);
        $this->assertNull($deployment->agent_finished_at);
        $this->assertSame($key, $deployment->agent_result_key);
        $this->assertSame('Diagnóstico más reciente del agente.', $deployment->error_message);
    }

    public function test_later_recovery_result_is_imported_and_old_result_cannot_return(): void
    {
        $deployment = $this->deployment(['error_message' => 'Composer no pudo instalar las dependencias.']);
        $earlier = $this->writeResult($deployment, ['status' => 'recovery_required', 'stage' => 'rollback']);
        $state = app(DeploymentAgentState::class);
        $state->synchronizeResult();
        $this->travel(1)->minutes();
        $this->write('recovery', ['schema' => 1, 'request_id' => $deployment->request_id, 'phase' => 'rollback']);
        $state->progress($deployment->request_id, 'rollback');
        $this->writeResult($deployment, ['status' => 'failed', 'stage' => 'rollback', 'restored' => true]);

        $this->assertNotNull($state->synchronizeResult());
        $deployment->refresh();
        $this->assertSame('failed', $deployment->agent_status);
        $this->assertStringContainsString('Composer no pudo instalar', $deployment->error_message);
        $this->assertStringContainsString('restaurada automáticamente', $deployment->error_message);
        $this->assertStringNotContainsString('recuperación no terminó', $deployment->error_message);

        $this->write('result', $earlier);
        $this->assertNull($state->synchronizeResult());
        $this->assertSame('failed', $deployment->fresh()->agent_status);
    }

    public function test_late_stdout_cannot_revive_an_already_imported_terminal_result(): void
    {
        foreach (['succeeded', 'failed', 'recovery_required'] as $status) {
            $deployment = $this->deployment();
            $this->writeResult($deployment, ['status' => $status]);
            $state = app(DeploymentAgentState::class);
            $state->synchronizeResult();
            $terminal = $deployment->fresh()->getAttributes();

            $this->travel(1)->seconds();
            $state->progress($deployment->request_id, 'completed');

            $this->assertSame($terminal, $deployment->fresh()->getAttributes());
            $this->assertNull($state->synchronizeResult());
        }
    }

    public function test_another_requests_journal_does_not_allow_late_progress(): void
    {
        $deployment = $this->deployment();
        $this->writeResult($deployment);
        $state = app(DeploymentAgentState::class);
        $state->synchronizeResult();
        $this->write('recovery', ['schema' => 1, 'request_id' => 'different-request', 'phase' => 'rollback']);

        $state->progress($deployment->request_id, 'rollback');

        $this->assertSame('failed', $deployment->fresh()->agent_status);
        $this->assertNotNull($deployment->fresh()->agent_finished_at);
    }

    public function test_canonical_result_key_ignores_json_order_and_equivalent_timezone(): void
    {
        $deployment = $this->deployment();
        $result = $this->writeResult($deployment);
        $state = app(DeploymentAgentState::class);
        $state->synchronizeResult();
        $deployment->refresh()->update(['error_message' => 'Mensaje posterior.']);
        $result['finished_at'] = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $result['unused'] = 'Metadata unrelated to the outcome';
        $this->write('result', array_reverse($result, true));

        $this->assertNull($state->synchronizeResult());
        $this->assertSame('Mensaje posterior.', $deployment->fresh()->error_message);
    }

    public function test_malformed_results_do_not_modify_history_or_throw(): void
    {
        $deployment = $this->deployment();
        foreach ([
            ['stage' => ['rollback']],
            ['stage' => 'unknown'],
            ['restored' => 'true'],
            ['interrupted' => 'false'],
            ['finished_at' => 'tomorrow'],
            ['request_id' => "invalid\nidentifier"],
            ['sha' => ['invalid']],
            ['status' => 'succeeded', 'stage' => 'rollback'],
            ['status' => 'succeeded', 'restored' => true],
        ] as $attributes) {
            $this->writeResult($deployment, $attributes);
            $this->assertNull(app(DeploymentAgentState::class)->synchronizeResult());
            $this->assertNull($deployment->fresh()->agent_status);
        }
    }

    public function test_future_and_pre_request_outcomes_are_rejected(): void
    {
        $deployment = $this->deployment();
        foreach ([now()->addMinutes(2), $deployment->created_at->copy()->subMinutes(2)] as $finishedAt) {
            $this->writeResult($deployment, ['finished_at' => $finishedAt->toIso8601String()]);
            $this->assertNull(app(DeploymentAgentState::class)->synchronizeResult());
        }
        $this->assertNull($deployment->fresh()->agent_status);
    }

    public function test_durable_outcome_does_not_need_an_authorization_marker(): void
    {
        $deployment = $this->deployment(['error_message' => 'An older diagnostic.']);
        $this->writeResult($deployment, ['status' => 'succeeded']);

        $this->assertNotNull(app(DeploymentAgentState::class)->synchronizeResult());
        $this->assertSame('succeeded', $deployment->fresh()->agent_status);
        $this->assertNull($deployment->fresh()->error_message);
        $this->assertFileDoesNotExist(storage_path('app/deployment/expected.json'));
    }

    public function test_read_handles_missing_malformed_and_oversized_markers(): void
    {
        $state = app(DeploymentAgentState::class);
        $this->assertNull($state->read('result'));
        File::put(storage_path('app/deployment/result.json'), '{');
        $this->assertNull($state->read('result'));
        File::put(storage_path('app/deployment/result.json'), str_repeat(' ', 65537));
        $this->assertNull($state->read('result'));
        $this->assertNull($state->read('../developer-console/settings'));
    }

    public function test_removal_respects_the_preparation_mutex_and_other_requests(): void
    {
        $this->write('expected', $this->marker());
        $state = app(DeploymentAgentState::class);
        $lock = Cache::lock('developer-console:deployment-marker', 30);
        $this->assertTrue($lock->get());

        $this->assertFalse($state->removeAuthorization('current-request'));
        $this->assertFileExists(storage_path('app/deployment/expected.json'));
        $lock->release();
        $this->assertFalse($state->removeAuthorization('different-request'));
        $this->assertTrue($state->removeAuthorization('current-request'));
        $this->assertFileDoesNotExist(storage_path('app/deployment/expected.json'));
    }

    public function test_expired_snapshot_does_not_delete_a_renewed_authorization(): void
    {
        $snapshot = $this->marker(['expires_at' => now()->subMinute()->timestamp]);
        $this->write('expected', $snapshot);
        $this->write('expected', $this->marker());

        $this->assertFalse(app(DeploymentAgentState::class)->discardInvalidAuthorization($snapshot));
        $this->assertFileExists(storage_path('app/deployment/expected.json'));
    }

    public function test_invalid_authorization_is_deleted_only_when_unchanged_and_lock_is_available(): void
    {
        $snapshot = $this->marker(['expires_at' => now()->subMinute()->timestamp]);
        $this->write('expected', $snapshot);
        $state = app(DeploymentAgentState::class);
        $lock = Cache::lock('developer-console:deployment-marker', 30);
        $lock->get();
        $this->assertFalse($state->discardInvalidAuthorization($snapshot));
        $lock->release();

        $this->assertTrue($state->discardInvalidAuthorization($snapshot));
        $this->assertFileDoesNotExist(storage_path('app/deployment/expected.json'));
        $this->assertFalse($state->discardInvalidAuthorization($snapshot));
    }

    public function test_valid_snapshot_cannot_be_discarded_as_invalid(): void
    {
        $snapshot = $this->marker();
        $this->write('expected', $snapshot);

        $this->assertFalse(app(DeploymentAgentState::class)->discardInvalidAuthorization($snapshot));
        $this->assertFileExists(storage_path('app/deployment/expected.json'));
    }

    private function deployment(array $attributes = []): DeploymentRun
    {
        return DeploymentRun::query()->create(array_merge([
            'request_id' => (string) Str::uuid(), 'ref' => 'main', 'status' => 'in_progress',
            'created_at' => now()->subHour(),
        ], $attributes));
    }

    private function writeResult(DeploymentRun $deployment, array $attributes = []): array
    {
        $result = array_merge([
            'schema' => 1, 'request_id' => $deployment->request_id, 'status' => 'failed',
            'stage' => 'completed', 'restored' => false, 'finished_at' => now()->toIso8601String(),
        ], $attributes);
        $this->write('result', $result);

        return $result;
    }

    private function marker(array $attributes = []): array
    {
        return array_merge([
            'sha' => str_repeat('a', 40), 'request_id' => 'current-request', 'expires_at' => now()->addMinutes(20)->timestamp,
        ], $attributes);
    }

    private function write(string $name, array $value): void
    {
        File::put(storage_path('app/deployment/'.$name.'.json'), json_encode($value, JSON_THROW_ON_ERROR));
    }
}
