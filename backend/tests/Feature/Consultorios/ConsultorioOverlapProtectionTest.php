<?php

namespace Tests\Feature\Consultorios;

use App\Http\Requests\StoreConsultorioReservaRequest;
use App\Models\CatalogoCarrera;
use App\Models\CatalogoConsultorio;
use App\Models\CatalogoCubiculo;
use App\Models\CatalogoEstrategia;
use App\Models\CatalogoTurno;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\User;
use Closure;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConsultorioOverlapProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-02 12:00:00'));
        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        foreach ([1, 2] as $numero) {
            CatalogoConsultorio::query()->updateOrCreate(['numero' => $numero], [
                'nombre' => 'Espacio físico de prueba '.$numero,
                'activo' => true,
            ]);
            CatalogoCubiculo::query()->updateOrCreate(['numero' => $numero], [
                'nombre' => 'Cubículo histórico '.$numero,
                'activo' => true,
            ]);
        }
        foreach (['Estrategia primera', 'Estrategia diferente'] as $nombre) {
            CatalogoEstrategia::query()->firstOrCreate(['nombre' => $nombre], ['activo' => true]);
        }
        CatalogoCarrera::query()->firstOrCreate(['nombre' => 'Carrera de prueba'], ['activo' => true]);
        CatalogoTurno::query()->firstOrCreate(['nombre' => 'Turno de prueba'], ['activo' => true]);
        foreach ([CatalogoConsultorio::class, CatalogoCubiculo::class, CatalogoEstrategia::class, CatalogoCarrera::class, CatalogoTurno::class] as $catalogo) {
            $catalogo::flushCache();
        }

        $this->admin = $this->user('admin');
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    #[DataProvider('overlappingTimes')]
    public function test_store_rejects_every_overlap_in_the_same_room_even_for_other_cubicles_and_people(string $start, string $end): void
    {
        $existing = $this->reservation([
            'usuario_atendido_id' => $this->user('alumno')->id,
            'estratega_id' => $this->user('docente')->id,
        ]);

        $this->postJson(route('consultorios.store'), $this->payload([
            'hora_inicio' => $start,
            'hora_fin' => $end,
            'usuario_atendido_id' => $this->user('alumno')->id,
            'estratega_id' => $this->user('docente')->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('hora_inicio');

        $this->assertSame([$existing->id], ConsultorioReserva::query()->pluck('id')->all());
        $this->assertSame(1, $existing->fresh()->cubiculo_numero);
        $this->assertSame('Estrategia primera', $existing->fresh()->estrategia);
        $this->assertDatabaseCount('consultorio_reserva_solicitudes', 0);
    }

    #[DataProvider('nonOverlappingAssignments')]
    public function test_store_allows_adjacent_intervals_another_room_or_another_day(array $changes): void
    {
        $this->reservation();
        $payload = $this->payload($changes);

        $this->postJson(route('consultorios.store'), $payload)->assertOk();

        $this->assertDatabaseCount('consultorio_reservas', 2);
        $created = ConsultorioReserva::query()->orderByDesc('id')->firstOrFail();
        $this->assertSame($payload['fecha'], $created->fecha->toDateString());
        $this->assertSame($payload['consultorio_numero'], $created->consultorio_numero);
        $this->assertSame($payload['hora_inicio'], substr($created->hora_inicio, 0, 5));
        $this->assertSame($payload['hora_fin'], substr($created->hora_fin, 0, 5));
    }

    public function test_admin_update_ignores_its_own_booking_but_cannot_overlap_another_historical_cubicle(): void
    {
        $reservation = $this->reservation();
        $payload = $this->payload(['cubiculo_numero' => 1]);

        $this->putJson(route('consultorios.update', $reservation), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('consultorios.index'));
        $this->assertDatabaseCount('consultorio_reservas', 1);

        $blocker = $this->reservation([
            'hora_inicio' => '11:00:00',
            'hora_fin' => '12:00:00',
            'cubiculo_numero' => 2,
        ]);
        $unchanged = $reservation->fresh()->getAttributes();

        $this->putJson(route('consultorios.update', $reservation), $this->payload([
            'hora_inicio' => '11:30',
            'hora_fin' => '12:30',
            'cubiculo_numero' => 1,
        ]))->assertUnprocessable()->assertJsonValidationErrors('hora_inicio');

        $this->assertSame($unchanged, $reservation->fresh()->getAttributes());
        $this->assertDatabaseCount('consultorio_reservas', 2);
        $this->assertTrue($blocker->fresh()->exists);
    }

    public function test_one_conflicting_weekly_occurrence_prevents_the_entire_series(): void
    {
        $blocker = $this->reservation(['fecha' => '2026-11-16']);

        $this->postJson(route('consultorios.store'), $this->weeklyPayload())
            ->assertUnprocessable()->assertJsonValidationErrors('hora_inicio');

        $this->assertSame([$blocker->id], ConsultorioReserva::query()->pluck('id')->all());
        $this->assertDatabaseMissing('consultorio_reservas', ['fecha' => '2026-11-09']);
        $this->assertDatabaseMissing('consultorio_reservas', ['fecha' => '2026-11-23']);
    }

    public function test_weekly_store_rechecks_conflicts_introduced_after_request_validation(): void
    {
        $request = new class extends StoreConsultorioReservaRequest
        {
            public Closure $afterValidation;

            protected function passedValidation(): void
            {
                ($this->afterValidation)();
            }
        };
        $request->afterValidation = function (): void {
            $this->reservation(['fecha' => '2026-11-16']);
        };
        $this->app->bind(StoreConsultorioReservaRequest::class, fn () => $request);

        $this->postJson(route('consultorios.store'), $this->weeklyPayload())
            ->assertUnprocessable()->assertJsonValidationErrors('hora_inicio');

        $this->assertDatabaseCount('consultorio_reservas', 1);
        $this->assertDatabaseHas('consultorio_reservas', ['fecha' => '2026-11-16', 'cubiculo_numero' => 1]);
        $this->assertDatabaseMissing('consultorio_reservas', ['fecha' => '2026-11-09']);
        $this->assertDatabaseMissing('consultorio_reservas', ['fecha' => '2026-11-23']);
    }

    public function test_approval_rechecks_a_conflict_added_after_the_paps_proposal_and_keeps_it_pending(): void
    {
        $paps = $this->user('paps');
        $reservation = $this->reservation([
            'hora_inicio' => '07:00:00',
            'hora_fin' => '08:00:00',
            'cubiculo_numero' => 2,
        ]);
        $unchanged = $reservation->fresh()->getAttributes();
        $proposed = $this->payload(['hora_inicio' => '10:00', 'hora_fin' => '11:00']);

        $this->actingAs($paps)
            ->putJson(route('consultorios.request-update', $reservation), $proposed)
            ->assertSessionHasNoErrors()->assertRedirect(route('consultorios.index'));

        $proposal = ConsultorioReservaSolicitud::query()->firstOrFail();
        $this->assertSame('pendiente', $proposal->status);
        $this->assertSame($unchanged, $reservation->fresh()->getAttributes());

        $this->reservation(['hora_inicio' => '10:30:00', 'hora_fin' => '11:30:00']);

        $this->actingAs($this->admin)
            ->postJson(route('admin.consultorios.solicitudes.approve', $proposal))
            ->assertUnprocessable()->assertJsonValidationErrors('hora_inicio');

        $this->assertSame('pendiente', $proposal->fresh()->status);
        $this->assertSame($unchanged, $reservation->fresh()->getAttributes());
        $this->assertDatabaseCount('consultorio_reservas', 2);
        $this->assertDatabaseCount('consultorio_reserva_solicitudes', 1);
    }

    public function test_conflicting_assignment_rolls_back_the_expediente_urgency_and_reservation(): void
    {
        $paps = $this->user('paps');
        $existing = $this->reservation();
        $strategy = CatalogoEstrategia::query()->where('nombre', 'Estrategia diferente')->firstOrFail();

        $this->actingAs($paps)->postJson(route('expedientes.store'), [
            'no_control' => 'CA-2026-9911',
            'paciente' => 'Paciente sintético sin registro',
            'apertura' => now()->toDateString(),
            'carrera' => 'Carrera de prueba',
            'turno' => 'Turno de prueba',
            'estrategia_id' => $strategy->id,
            'cubiculo_id' => CatalogoConsultorio::query()->where('numero', $this->payload()['consultorio_numero'])->value('id'),
            'resumen_clinico' => ['cubiculo' => 2],
            'registro_urgencia' => ['nivel_riesgo' => 'bajo', 'motivo' => 'Prueba sintética'],
            'consultorio_reserva' => $this->payload(),
        ])->assertUnprocessable()->assertJsonValidationErrors('consultorio_reserva.hora_inicio');

        $this->assertDatabaseCount('expedientes', 0);
        $this->assertDatabaseCount('registro_urgencias', 0);
        $this->assertDatabaseCount('consultorio_reserva_solicitudes', 0);
        $this->assertSame([$existing->id], ConsultorioReserva::query()->pluck('id')->all());
    }

    public static function overlappingTimes(): array
    {
        return [
            'exact same interval' => ['09:00', '10:00'],
            'overlaps the start' => ['08:30', '09:30'],
            'overlaps the end' => ['09:30', '10:30'],
            'contained interval' => ['09:15', '09:45'],
            'containing interval' => ['08:30', '10:30'],
        ];
    }

    public static function nonOverlappingAssignments(): array
    {
        return [
            'ends exactly at the next start' => [['hora_inicio' => '08:00', 'hora_fin' => '09:00']],
            'starts exactly at the previous end' => [['hora_inicio' => '10:00', 'hora_fin' => '11:00']],
            'another physical room' => [['consultorio_numero' => 2]],
            'another day' => [['fecha' => '2026-11-10']],
        ];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['approved_at' => now(), 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function reservation(array $overrides = []): ConsultorioReserva
    {
        return ConsultorioReserva::query()->create(array_replace([
            'fecha' => '2026-11-09',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '10:00:00',
            'consultorio_numero' => 1,
            'cubiculo_numero' => 1,
            'estrategia' => 'Estrategia primera',
            'creado_por' => $this->admin->id,
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'modo_repeticion' => 'unica',
            'fecha' => '2026-11-09',
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => 1,
            'cubiculo_numero' => 2,
            'estrategia' => 'Estrategia diferente',
        ], $overrides);
    }

    private function weeklyPayload(): array
    {
        return $this->payload([
            'modo_repeticion' => 'semanal',
            'fecha_inicio_repeticion' => '2026-11-09',
            'fecha_fin_repeticion' => '2026-11-23',
            'dias_semana' => [1],
        ]);
    }
}
