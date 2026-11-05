<?php

namespace Tests\Feature\Database;

use App\Models\CatalogoEstrategia;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParticipantStrategyMigrationRecoveryTest extends TestCase
{
    use DatabaseMigrations;

    #[DataProvider('partialStates')]
    public function test_additive_migration_recovers_partial_schema_without_inferred_memberships_or_history_changes(string $state): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        foreach (['coordinador', 'docente', 'alumno'] as $name) {
            Role::findOrCreate($name, 'web');
        }
        $participant = User::factory()->create();
        $participant->syncRoles(['docente', 'alumno', 'coordinador']);
        $inferred = CatalogoEstrategia::create(['nombre' => 'No inferir desde coordinación o caso', 'activo' => true]);
        $explicit = CatalogoEstrategia::create(['nombre' => 'Membresía explícita previa', 'activo' => false]);
        $participant->estrategiasCoordinadas()->attach($inferred);
        Expediente::factory()->create([
            'creado_por' => $participant->id,
            'tutor_id' => $participant->id,
            'facilitador_id' => $participant->id,
            'coordinador_id' => $participant->id,
            'estrategia_id' => $inferred->id,
        ]);

        $snapshots = [];
        foreach (['users', 'roles', 'model_has_roles', 'coordinador_estrategia', 'catalogo_estrategias', 'expedientes'] as $table) {
            $snapshots[$table] = DB::table($table)->get()->toArray();
        }
        $migration = require database_path('migrations/2026_11_04_000003_create_participante_estrategia_table.php');
        $migration->down();

        if ($state !== 'missing table') {
            Schema::create('participante_estrategia', function (Blueprint $table) use ($state): void {
                $table->unsignedBigInteger('user_id');
                if ($state !== 'one column') {
                    $table->unsignedBigInteger('estrategia_id');
                }
                if ($state === 'custom unique and one FK') {
                    $table->unique(['user_id', 'estrategia_id'], 'existing_participant_unique');
                    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                }
            });
        }
        $hasExplicitRow = in_array($state, ['columns only', 'custom unique and one FK'], true);
        if ($hasExplicitRow) {
            DB::table('participante_estrategia')->insert(['user_id' => $participant->id, 'estrategia_id' => $explicit->id]);
        }

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumns('participante_estrategia', ['user_id', 'estrategia_id', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasIndex('participante_estrategia', ['user_id', 'estrategia_id'], 'unique'));
        $uniqueIndexes = collect(Schema::getIndexes('participante_estrategia'))
            ->filter(fn (array $index): bool => $index['columns'] === ['user_id', 'estrategia_id'] && $index['unique']);
        $this->assertCount(1, $uniqueIndexes);
        if ($state === 'custom unique and one FK') {
            $this->assertSame('existing_participant_unique', $uniqueIndexes->first()['name']);
        }
        $foreignKeys = collect(Schema::getForeignKeys('participante_estrategia'));
        $this->assertCount(2, $foreignKeys);
        foreach (['user_id' => 'users', 'estrategia_id' => 'catalogo_estrategias'] as $column => $target) {
            $this->assertTrue(Schema::hasIndex('participante_estrategia', [$column]));
            $keys = $foreignKeys->filter(fn (array $key): bool => $key['columns'] === [$column]);
            $this->assertCount(1, $keys);
            $this->assertSame($target, $keys->first()['foreign_table']);
            $this->assertSame(['id'], $keys->first()['foreign_columns']);
            $this->assertSame('cascade', strtolower($keys->first()['on_delete']));
        }
        $this->assertDatabaseCount('participante_estrategia', $hasExplicitRow ? 1 : 0);
        $this->assertDatabaseMissing('participante_estrategia', ['user_id' => $participant->id, 'estrategia_id' => $inferred->id]);
        if ($hasExplicitRow) {
            $this->assertDatabaseHas('participante_estrategia', ['user_id' => $participant->id, 'estrategia_id' => $explicit->id]);
        }
        foreach ($snapshots as $table => $rows) {
            $this->assertEquals($rows, DB::table($table)->get()->toArray(), 'Migration changed '.$table);
        }

        $migration->down();
        $migration->down();
        $this->assertFalse(Schema::hasTable('participante_estrategia'));
        foreach ($snapshots as $table => $rows) {
            $this->assertEquals($rows, DB::table($table)->get()->toArray(), 'Rollback changed '.$table);
        }
    }

    public static function partialStates(): array
    {
        return [
            'fresh installation' => ['missing table'],
            'interrupted columns' => ['one column'],
            'columns without constraints or timestamps' => ['columns only'],
            'preserve custom unique and one foreign key' => ['custom unique and one FK'],
        ];
    }
}
