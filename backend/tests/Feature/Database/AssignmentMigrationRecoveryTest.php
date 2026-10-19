<?php

namespace Tests\Feature\Database;

use App\Models\CatalogoEstrategia;
use App\Models\ConsultorioReserva;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AssignmentMigrationRecoveryTest extends TestCase
{
    use DatabaseMigrations;

    public function test_facilitator_migration_resumes_after_column_creation_and_preserves_assignments(): void
    {
        $creator = User::factory()->create();
        $assigned = User::factory()->create();
        $legacy = Expediente::factory()->create(['creado_por' => $creator->id]);
        $explicit = Expediente::factory()->create(['creado_por' => $creator->id]);
        $migration = require database_path('migrations/2026_10_19_000001_add_facilitador_id_to_expedientes_table.php');

        $migration->down();
        Schema::table('expedientes', function (Blueprint $table): void {
            $table->unsignedBigInteger('facilitador_id')->nullable();
        });
        DB::table('expedientes')->where('id', $explicit->id)->update(['facilitador_id' => $assigned->id]);

        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('expedientes', [
            'id' => $legacy->id,
            'creado_por' => $creator->id,
            'facilitador_id' => $creator->id,
        ]);
        $this->assertDatabaseHas('expedientes', [
            'id' => $explicit->id,
            'creado_por' => $creator->id,
            'facilitador_id' => $assigned->id,
        ]);
        $this->assertTrue(Schema::hasIndex('expedientes', ['facilitador_id']));
        $this->assertSame(1, collect(Schema::getForeignKeys('expedientes'))
            ->filter(fn (array $key): bool => $key['columns'] === ['facilitador_id'])
            ->count());
    }

    public function test_strategy_migration_resumes_partial_schema_without_losing_rows_or_memberships(): void
    {
        $creator = User::factory()->create();
        $first = CatalogoEstrategia::create(['nombre' => 'Estrategia existente', 'activo' => true]);
        $second = CatalogoEstrategia::create(['nombre' => 'Estrategia reasignada', 'activo' => true]);
        $expediente = Expediente::factory()->create(['creado_por' => $creator->id]);
        $legacy = $this->reservation($creator, $first->nombre, '08:00');
        $explicit = $this->reservation($creator, $first->nombre, '09:00');
        $unknown = $this->reservation($creator, 'Nombre histórico sin catálogo', '10:00');
        $migration = require database_path('migrations/2026_10_19_000002_add_coordinator_strategy_assignments.php');

        $migration->down();
        Schema::table('expedientes', function (Blueprint $table): void {
            $table->unsignedBigInteger('estrategia_id')->nullable();
        });
        Schema::create('coordinador_estrategia', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('estrategia_id');
            $table->primary(['user_id', 'estrategia_id']);
            $table->timestamps();
        });
        Schema::table('consultorio_reservas', function (Blueprint $table): void {
            $table->unsignedBigInteger('estrategia_id')->nullable();
        });
        DB::table('expedientes')->where('id', $expediente->id)->update(['estrategia_id' => $second->id]);
        DB::table('consultorio_reservas')->where('id', $explicit->id)->update(['estrategia_id' => $second->id]);
        DB::table('coordinador_estrategia')->insert([
            'user_id' => $creator->id,
            'estrategia_id' => $first->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('expedientes', ['id' => $expediente->id, 'estrategia_id' => $second->id]);
        $this->assertDatabaseHas('consultorio_reservas', ['id' => $legacy->id, 'estrategia_id' => $first->id]);
        $this->assertDatabaseHas('consultorio_reservas', ['id' => $explicit->id, 'estrategia_id' => $second->id]);
        $this->assertDatabaseHas('consultorio_reservas', ['id' => $unknown->id, 'estrategia_id' => null]);
        $this->assertDatabaseHas('coordinador_estrategia', ['user_id' => $creator->id, 'estrategia_id' => $first->id]);
        $this->assertDatabaseCount('coordinador_estrategia', 1);
        $this->assertCount(2, Schema::getForeignKeys('coordinador_estrategia'));

        foreach (['expedientes', 'consultorio_reservas'] as $table) {
            $this->assertTrue(Schema::hasIndex($table, ['estrategia_id']));
            $this->assertSame(1, collect(Schema::getForeignKeys($table))
                ->filter(fn (array $key): bool => $key['columns'] === ['estrategia_id'])
                ->count());
        }
    }

    private function reservation(User $creator, string $strategy, string $time): ConsultorioReserva
    {
        return ConsultorioReserva::create([
            'fecha' => '2026-10-20',
            'hora_inicio' => $time,
            'hora_fin' => '11:00',
            'consultorio_numero' => 1,
            'cubiculo_numero' => 1,
            'estrategia' => $strategy,
            'creado_por' => $creator->id,
        ]);
    }
}
