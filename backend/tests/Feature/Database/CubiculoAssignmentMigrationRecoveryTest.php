<?php

namespace Tests\Feature\Database;

use App\Models\CatalogoConsultorio;
use App\Models\ConsultorioReserva;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CubiculoAssignmentMigrationRecoveryTest extends TestCase
{
    use DatabaseMigrations;

    #[DataProvider('partialSchemaStates')]
    public function test_migration_resumes_partial_schema_idempotently_without_guessing_or_rewriting_history(string $state): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $creator = User::factory()->create(['name' => 'Cuenta sintética de migración']);

        foreach ([7 => 99, 200 => 18] as $id => $number) {
            $room = new CatalogoConsultorio;
            $room->forceFill(['id' => $id, 'numero' => $number, 'nombre' => 'Espacio canónico sintético '.$number, 'activo' => true])->save();
        }
        DB::table('catalogo_cubiculos')->insert([
            'id' => 88,
            'nombre' => 'Entrada histórica sin correspondencia',
            'numero' => 88,
            'activo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $legacy = $this->expediente($creator, 'Paciente sintético histórico');
        $explicit = $this->expediente($creator, 'Paciente sintético asignado');
        $reservation = ConsultorioReserva::query()->create([
            'expediente_id' => $legacy->id,
            'fecha' => '2026-11-09',
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => 18,
            'cubiculo_numero' => 7,
            'estrategia' => 'Referencia histórica sintética',
            'creado_por' => $creator->id,
        ]);
        $summaries = DB::table('expedientes')->orderBy('id')->pluck('resumen_clinico', 'id')->all();
        $canonicalRows = DB::table('catalogo_consultorios')->orderBy('id')->get()->toArray();
        $archiveRows = DB::table('catalogo_cubiculos')->orderBy('id')->get()->toArray();
        $reservationBefore = $reservation->fresh()->getAttributes();
        $migration = require database_path('migrations/2026_11_04_000002_add_cubiculo_id_to_expedientes_table.php');

        $migration->down();
        if ($state !== 'missing column') {
            Schema::table('expedientes', function (Blueprint $table) use ($state): void {
                $table->unsignedBigInteger('cubiculo_id')->nullable();
                if ($state === 'column and custom index') {
                    $table->index('cubiculo_id', 'recovered_cubiculo_custom_index');
                }
            });
            DB::table('expedientes')->where('id', $explicit->id)->update(['cubiculo_id' => 200]);
        }

        $migration->up();
        $migration->up();

        $this->assertNull($legacy->fresh()->cubiculo_id);
        $this->assertSame($state === 'missing column' ? null : 200, $explicit->fresh()->cubiculo_id);
        $this->assertSame($summaries, DB::table('expedientes')->orderBy('id')->pluck('resumen_clinico', 'id')->all());
        $this->assertEquals($canonicalRows, DB::table('catalogo_consultorios')->orderBy('id')->get()->toArray());
        $this->assertEquals($archiveRows, DB::table('catalogo_cubiculos')->orderBy('id')->get()->toArray());
        $this->assertSame($reservationBefore, $reservation->fresh()->getAttributes());
        $this->assertDatabaseCount('expedientes', 2);
        $this->assertDatabaseCount('consultorio_reservas', 1);

        $indexes = collect(Schema::getIndexes('expedientes'))
            ->filter(fn (array $index): bool => $index['columns'] === ['cubiculo_id'] && ! $index['primary']);
        $foreignKeys = collect(Schema::getForeignKeys('expedientes'))
            ->filter(fn (array $key): bool => $key['columns'] === ['cubiculo_id']);
        $this->assertCount(1, $indexes);
        $this->assertCount(1, $foreignKeys);
        $foreignKey = $foreignKeys->first();
        $this->assertSame('catalogo_consultorios', $foreignKey['foreign_table']);
        $this->assertSame(['id'], $foreignKey['foreign_columns']);
        $this->assertSame('restrict', strtolower($foreignKey['on_delete']));
        if ($state === 'column and custom index') {
            $this->assertSame('recovered_cubiculo_custom_index', $indexes->first()['name']);
        }

        $migration->down();
        $migration->down();

        $this->assertFalse(Schema::hasColumn('expedientes', 'cubiculo_id'));
        $this->assertSame($summaries, DB::table('expedientes')->orderBy('id')->pluck('resumen_clinico', 'id')->all());
        $this->assertEquals($canonicalRows, DB::table('catalogo_consultorios')->orderBy('id')->get()->toArray());
        $this->assertEquals($archiveRows, DB::table('catalogo_cubiculos')->orderBy('id')->get()->toArray());
        $this->assertSame($reservationBefore, $reservation->fresh()->getAttributes());
        $this->assertDatabaseCount('expedientes', 2);
        $this->assertDatabaseCount('consultorio_reservas', 1);
    }

    public static function partialSchemaStates(): array
    {
        return [
            'no column or constraints' => ['missing column'],
            'column only' => ['column only'],
            'custom index without foreign key' => ['column and custom index'],
        ];
    }

    private function expediente(User $creator, string $name): Expediente
    {
        return Expediente::factory()->create([
            'paciente' => $name,
            'creado_por' => $creator->id,
            'facilitador_id' => null,
            'cubiculo_id' => null,
            'resumen_clinico' => array_merge(Expediente::defaultClinicalSummary(), ['cubiculo' => 7]),
        ]);
    }
}
