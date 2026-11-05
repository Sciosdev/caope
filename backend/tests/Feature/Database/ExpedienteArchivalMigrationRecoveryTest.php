<?php

namespace Tests\Feature\Database;

use App\Models\Consentimiento;
use App\Models\ConsultorioReserva;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpedienteArchivalMigrationRecoveryTest extends TestCase
{
    use DatabaseMigrations;

    #[DataProvider('schemaStates')]
    public function test_migration_recovers_partial_columns_and_constraints_without_archiving_or_purging_history(
        array $columns,
        bool $customIndexes,
        bool $existingForeignKey,
        bool $completeReplay,
        bool $missingAuditAndOutboxIndexes = false,
    ): void {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $actor = User::factory()->create(['name' => 'Administrador sintético de migración']);
        $active = Expediente::factory()->create(['paciente' => 'Caso sintético vigente', 'creado_por' => $actor->id]);
        $historical = Expediente::factory()->create(['paciente' => 'Caso sintético con historial', 'creado_por' => $actor->id]);
        Consentimiento::factory()->create(['expediente_id' => $historical->id, 'archivo_path' => 'synthetic/migration-consent.pdf']);
        ConsultorioReserva::query()->create([
            'expediente_id' => $historical->id,
            'consultorio_numero' => 1,
            'cubiculo_numero' => 1,
            'fecha' => '2026-11-09',
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'estrategia' => 'Historia sintética sin inferencia',
            'creado_por' => $actor->id,
        ]);
        $migration = require database_path('migrations/2026_11_04_000004_add_expediente_archival_and_purge_audit.php');

        if (! $completeReplay) {
            // Solo desarma el esquema de esta base SQLite desechable antes de
            // introducir datos históricos; no es un rollback de datos retenidos.
            $migration->down();
            foreach ($columns as $column) {
                Schema::table('expedientes', function (Blueprint $table) use ($column): void {
                    if ($column === 'archived_by') {
                        $table->unsignedBigInteger($column)->nullable();
                    } else {
                        $table->timestamp($column)->nullable();
                    }
                });
            }
            if ($customIndexes) {
                foreach ($columns as $column) {
                    Schema::table('expedientes', function (Blueprint $table) use ($column): void {
                        $table->index($column, 'historical_'.$column.'_custom_index');
                    });
                }
            }
            if ($existingForeignKey) {
                Schema::table('expedientes', function (Blueprint $table): void {
                    $table->foreign('archived_by')->references('id')->on('users')->nullOnDelete();
                });
            }
        }

        $historicalValues = array_intersect_key([
            'archived_by' => $actor->id,
            'retention_until' => '2036-11-01 09:00:00',
            'deleted_at' => '2026-11-01 09:00:00',
        ], array_flip($columns));
        if ($historicalValues !== []) {
            DB::table('expedientes')->where('id', $historical->id)->update($historicalValues);
        }
        if ($completeReplay) {
            $auditId = DB::table('expediente_lifecycle_audits')->insertGetId([
                'expediente_id' => $historical->id,
                'actor_id' => $actor->id,
                'folio' => $historical->no_control,
                'action' => 'archive',
                'motivo' => 'Registro sintético anterior al replay',
                'created_at' => '2026-11-01 09:00:00',
            ]);
            DB::table('expediente_file_deletions')->insert([
                'audit_id' => $auditId,
                'expediente_id' => $historical->id,
                'disk' => 'private',
                'path' => 'synthetic/pending-migration-file.pdf',
                'deduplication_key' => hash('sha256', 'synthetic-migration-file'),
                'status' => 'pending',
                'attempts' => 2,
                'last_error' => 'Fallo sintético previo',
                'created_at' => '2026-11-01 09:01:00',
                'updated_at' => '2026-11-01 09:02:00',
            ]);
        }
        if ($missingAuditAndOutboxIndexes) {
            foreach (['expediente_lifecycle_audits', 'expediente_file_deletions'] as $tableName) {
                foreach (Schema::getIndexes($tableName) as $index) {
                    if ($index['primary']) {
                        continue;
                    }
                    Schema::table($tableName, function (Blueprint $table) use ($index): void {
                        if ($index['unique']) {
                            $table->dropUnique($index['name']);
                        } else {
                            $table->dropIndex($index['name']);
                        }
                    });
                }
            }
        }

        $expedienteColumnsBefore = Schema::getColumnListing('expedientes');
        $expedientesBefore = DB::table('expedientes')->select($expedienteColumnsBefore)->orderBy('id')->get()->toArray();
        $otherSnapshots = [];
        foreach (['users', 'consentimientos', 'consultorio_reservas'] as $table) {
            $otherSnapshots[$table] = DB::table($table)->orderBy('id')->get()->toArray();
        }
        $auditsBefore = $completeReplay ? DB::table('expediente_lifecycle_audits')->get()->toArray() : [];
        $outboxBefore = $completeReplay ? DB::table('expediente_file_deletions')->get()->toArray() : [];

        $migration->up();
        $migration->up();

        $this->assertEquals($expedientesBefore, DB::table('expedientes')->select($expedienteColumnsBefore)->orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('expedientes', 2);
        foreach ($otherSnapshots as $table => $rows) {
            $this->assertEquals($rows, DB::table($table)->orderBy('id')->get()->toArray(), 'Migration changed '.$table);
        }
        foreach (['archived_by', 'retention_until', 'deleted_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('expedientes', $column));
            $indexes = collect(Schema::getIndexes('expedientes'))
                ->filter(fn (array $index): bool => $index['columns'] === [$column] && ! $index['primary']);
            $this->assertCount(1, $indexes);
            if ($customIndexes) {
                $this->assertSame('historical_'.$column.'_custom_index', $indexes->first()['name']);
            }
            $this->assertNull(DB::table('expedientes')->where('id', $active->id)->value($column));
            $this->assertSame($historicalValues[$column] ?? null, DB::table('expedientes')->where('id', $historical->id)->value($column));
        }
        $foreignKeys = collect(Schema::getForeignKeys('expedientes'))
            ->filter(fn (array $key): bool => $key['columns'] === ['archived_by']);
        $this->assertCount(1, $foreignKeys);
        $this->assertSame('users', $foreignKeys->first()['foreign_table']);
        $this->assertSame(['id'], $foreignKeys->first()['foreign_columns']);
        $this->assertSame('set null', strtolower($foreignKeys->first()['on_delete']));
        $this->assertTrue(Schema::hasColumns('expediente_lifecycle_audits', ['id', 'expediente_id', 'actor_id', 'folio', 'action', 'motivo', 'created_at']));
        $this->assertTrue(Schema::hasColumns('expediente_file_deletions', ['id', 'audit_id', 'expediente_id', 'disk', 'path', 'deduplication_key', 'status', 'attempts', 'last_error', 'processed_at', 'created_at', 'updated_at']));
        foreach ([['expediente_id'], ['actor_id'], ['action', 'created_at']] as $index) {
            $this->assertTrue(Schema::hasIndex('expediente_lifecycle_audits', $index));
        }
        foreach ([['audit_id'], ['expediente_id'], ['status', 'created_at']] as $index) {
            $this->assertTrue(Schema::hasIndex('expediente_file_deletions', $index));
        }
        $this->assertTrue(Schema::hasIndex('expediente_file_deletions', ['deduplication_key'], 'unique'));
        $this->assertEquals($auditsBefore, DB::table('expediente_lifecycle_audits')->get()->toArray());
        $this->assertEquals($outboxBefore, DB::table('expediente_file_deletions')->get()->toArray());
    }

    public static function schemaStates(): array
    {
        $all = ['archived_by', 'retention_until', 'deleted_at'];

        return [
            'fresh columns and tables' => [[], false, false, false],
            'archived by column only' => [['archived_by'], false, false, false],
            'retention column only' => [['retention_until'], false, false, false],
            'deleted at column only' => [['deleted_at'], false, false, false],
            'all columns custom indexes without FK' => [$all, true, false, false],
            'all columns FK without indexes' => [$all, false, true, false],
            'replay preserves archived retention audit and pending outbox' => [$all, false, true, true],
            'existing audit and outbox tables without indexes' => [$all, false, true, true, true],
        ];
    }
}
