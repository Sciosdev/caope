<?php

namespace Tests\Feature\Consultorios;

use App\Models\CatalogoEstrategia;
use App\Models\ConsultorioReserva;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConsultorioStrategyIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('coordinador', 'web');
    }

    public function test_catalog_rename_preserves_reservation_identity_and_coordinator_access(): void
    {
        $strategy = CatalogoEstrategia::create(['nombre' => 'Estrategia original', 'activo' => true]);
        $coordinator = $this->coordinator($strategy);
        $reservation = $this->reservation($coordinator, $strategy->nombre);

        $this->assertSame($strategy->id, (int) $reservation->estrategia_id);
        $this->assertSame($strategy->id, $reservation->estrategiaCatalogo->id);

        $strategy->update(['nombre' => 'Estrategia renombrada']);
        $reservation->update(['hora_inicio' => '10:00', 'hora_fin' => '11:00']);

        $this->assertSame($strategy->id, (int) $reservation->fresh()->estrategia_id);
        $this->assertVisibleIds($coordinator, [$reservation->id]);

        $replacement = CatalogoEstrategia::create(['nombre' => 'Estrategia original', 'activo' => true]);
        $otherCoordinator = $this->coordinator($replacement);

        $this->assertVisibleIds($otherCoordinator, []);
        $this->assertVisibleIds($coordinator, [$reservation->id]);

        $newReservation = $this->reservation($otherCoordinator, $replacement->nombre);
        $this->assertSame($replacement->id, (int) $newReservation->estrategia_id);
        $this->assertVisibleIds($otherCoordinator, [$newReservation->id]);
    }

    public function test_changing_the_reservation_strategy_reassigns_its_identity_and_access(): void
    {
        $original = CatalogoEstrategia::create(['nombre' => 'Estrategia Alfa', 'activo' => true]);
        $next = CatalogoEstrategia::create(['nombre' => 'Estrategia Beta', 'activo' => true]);
        $firstCoordinator = $this->coordinator($original);
        $nextCoordinator = $this->coordinator($next);
        $reservation = $this->reservation($firstCoordinator, $original->nombre);

        $reservation->update(['estrategia' => $next->nombre]);

        $this->assertSame($next->id, (int) $reservation->fresh()->estrategia_id);
        $this->assertVisibleIds($firstCoordinator, []);
        $this->assertVisibleIds($nextCoordinator, [$reservation->id]);

        $reservation->update(['estrategia_id' => $original->id]);
        $this->assertSame($next->id, (int) $reservation->fresh()->estrategia_id);
    }

    public function test_unknown_legacy_strategy_has_no_identity_or_coordinator_access(): void
    {
        $strategy = CatalogoEstrategia::create(['nombre' => 'Estrategia asignada', 'activo' => true]);
        $coordinator = $this->coordinator($strategy);
        $reservation = $this->reservation($coordinator, 'Nombre histórico sin catálogo');

        $this->assertNull($reservation->estrategia_id);
        $this->assertVisibleIds($coordinator, []);
    }

    public function test_migration_backfills_only_reservations_matching_a_catalog_name_and_rolls_back(): void
    {
        $strategy = CatalogoEstrategia::create(['nombre' => 'Estrategia de reserva existente', 'activo' => true]);
        $coordinator = $this->coordinator($strategy);
        $known = $this->reservation($coordinator, $strategy->nombre);
        $unknown = $this->reservation($coordinator, 'Sin coincidencia');
        $migration = require database_path('migrations/2026_10_19_000002_add_coordinator_strategy_assignments.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('consultorio_reservas', 'estrategia_id'));
        $this->assertFalse(Schema::hasColumn('expedientes', 'estrategia_id'));
        $this->assertFalse(Schema::hasTable('coordinador_estrategia'));

        $migration->up();

        $this->assertSame($strategy->id, (int) $known->fresh()->estrategia_id);
        $this->assertNull($unknown->fresh()->estrategia_id);
        $this->assertDatabaseCount('coordinador_estrategia', 0);
        $this->assertSame(0, DB::table('expedientes')->whereNotNull('estrategia_id')->count());

        try {
            $strategy->delete();
            $this->fail('A strategy referenced by a reservation must not be deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('catalogo_estrategias', ['id' => $strategy->id]);
        }
    }

    private function coordinator(CatalogoEstrategia $strategy): User
    {
        $user = User::factory()->create();
        $user->assignRole('coordinador');
        $user->estrategiasCoordinadas()->attach($strategy);

        return $user;
    }

    private function reservation(User $creator, string $strategy): ConsultorioReserva
    {
        return ConsultorioReserva::create([
            'fecha' => '2026-10-20',
            'hora_inicio' => '08:00',
            'hora_fin' => '09:00',
            'consultorio_numero' => 1,
            'cubiculo_numero' => 1,
            'estrategia' => $strategy,
            'creado_por' => $creator->id,
            'supervisor_id' => $creator->id,
        ]);
    }

    private function assertVisibleIds(User $user, array $expected): void
    {
        sort($expected);
        $this->assertSame($expected, ConsultorioReserva::visibleTo($user)->orderBy('id')->pluck('id')->all());
    }
}
