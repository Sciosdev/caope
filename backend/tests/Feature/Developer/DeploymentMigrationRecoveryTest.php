<?php

namespace Tests\Feature\Developer;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeploymentMigrationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_09_15_000001_add_agent_progress_to_deployment_runs_table';

    private const INDEX = 'deployment_runs_agent_status_index';

    #[DataProvider('partialSchemas')]
    public function test_migration_resumes_partial_ddl_and_preserves_existing_history(bool $indexAlreadyExists): void
    {
        $migration = require database_path('migrations/'.self::MIGRATION.'.php');
        $migration->down();
        DB::table('migrations')->where('migration', self::MIGRATION)->delete();

        // Reproduce the persisted state left by earlier successful ALTERs,
        // before the migration runner could record the migration as complete.
        Schema::table('deployment_runs', function (Blueprint $table) use ($indexAlreadyExists): void {
            $table->string('agent_status', 40)->nullable();
            $table->string('agent_stage', 40)->nullable();
            $table->timestamp('agent_started_at')->nullable();
            if ($indexAlreadyExists) {
                $table->index('agent_status');
            }
        });
        $id = DB::table('deployment_runs')->insertGetId([
            'request_id' => '521362ef-0706-478d-b729-bc01c1517754',
            'ref' => 'main',
            'status' => 'completed',
            'conclusion' => 'failure',
            'workflow_run_id' => 12345,
            'error_message' => 'Conservar diagnóstico del intento previo.',
            'agent_status' => 'failed',
            'agent_stage' => 'migrations',
            'agent_started_at' => '2026-09-14 12:00:00',
            'created_at' => '2026-09-14 11:59:00',
            'updated_at' => '2026-09-14 12:01:00',
        ]);
        $original = (array) DB::table('deployment_runs')->find($id);

        $this->runMigration();

        $recovered = (array) DB::table('deployment_runs')->find($id);
        foreach ($original as $column => $value) {
            $this->assertSame($value, $recovered[$column], "Existing {$column} changed.");
        }
        foreach (['agent_finished_at', 'agent_heartbeat_at', 'agent_result_key', 'agent_result_at'] as $column) {
            $this->assertArrayHasKey($column, $recovered);
            $this->assertNull($recovered[$column]);
        }
        $this->assertSame(1, DB::table('deployment_runs')->count());
        $this->assertSame(1, collect(Schema::getIndexes('deployment_runs'))->where('name', self::INDEX)->count());
        $columns = Schema::getColumnListing('deployment_runs');
        $indexes = Schema::getIndexes('deployment_runs');

        // All DDL can succeed while the final migration-history insert fails.
        // Running migrate again must only restore that missing history entry.
        DB::table('migrations')->where('migration', self::MIGRATION)->delete();
        $this->runMigration();

        $this->assertSame($recovered, (array) DB::table('deployment_runs')->find($id));
        $this->assertSame($columns, Schema::getColumnListing('deployment_runs'));
        $this->assertSame($indexes, Schema::getIndexes('deployment_runs'));
        $this->assertSame(1, DB::table('deployment_runs')->count());
    }

    /** @return array<string, array{bool}> */
    public static function partialSchemas(): array
    {
        return [
            'columns exist but index was not created' => [false],
            'columns and index already exist' => [true],
        ];
    }

    private function runMigration(): void
    {
        $exitCode = Artisan::call('migrate', [
            '--path' => 'database/migrations/'.self::MIGRATION.'.php',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertSame(1, DB::table('migrations')->where('migration', self::MIGRATION)->count());
        $this->assertTrue(Schema::hasIndex('deployment_runs', self::INDEX));
    }
}
