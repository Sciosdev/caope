<?php

namespace Tests\Feature\Consultorios;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoConsultorio;
use App\Models\CatalogoEstrategia;
use App\Models\CatalogoTurno;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecurringCaseReservationTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoConsultorio $room;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-08 12:00:00')); // Sunday: an optional assignment must not prevent an ordinary case.
        $this->seed(RoleSeeder::class);
        $this->room = CatalogoConsultorio::query()->updateOrCreate(['numero' => 18], ['nombre' => 'Sala ficticia', 'activo' => true]);
        CatalogoEstrategia::query()->create(['nombre' => 'Estrategia de prueba', 'activo' => true]);
        CatalogoCarrera::query()->create(['nombre' => 'Carrera de prueba', 'activo' => true]);
        CatalogoTurno::query()->create(['nombre' => 'Turno de prueba', 'activo' => true]);
        foreach ([CatalogoConsultorio::class, CatalogoEstrategia::class, CatalogoCarrera::class, CatalogoTurno::class] as $catalogo) {
            $catalogo::flushCache();
        }
        $this->actor = User::factory()->create(['is_active' => true, 'approved_at' => now()]);
        $this->actor->assignRole('admin');
        $this->actingAs($this->actor);
    }

    private function schedule(array $changes = []): array
    {
        return array_replace([
            'modo_repeticion' => 'semanal',
            'fecha_inicio_repeticion' => '2026-11-09',
            'fecha_fin_repeticion' => '2026-11-18',
            'dias_semana' => ['1', '3'],
            'hora_inicio' => '09:00', 'hora_fin' => '10:00',
            'estrategia' => 'Estrategia de prueba',
        ], $changes);
    }

    private function newCase(): array
    {
        return [
            'no_control' => 'CA-2026-9561',
            'paciente' => 'Persona ficticia',
            'apertura' => '2026-11-08', 'carrera' => 'Carrera de prueba', 'turno' => 'Turno de prueba',
            'cubiculo_id' => $this->room->id,
        ];
    }

    public function test_forms_offer_submittable_multiple_days_and_show_old_values_after_error(): void
    {
        foreach (['consultorios.index' => 'dias_semana', 'expedientes.create' => 'consultorio_reserva[dias_semana]'] as $route => $key) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertSame(6, substr_count($html, 'data-weekday name="'.$key.'[]"'));
            $this->assertStringContainsString('Ver fechas seleccionadas', $html);
        }
        $this->post(route('expedientes.store'), $this->newCase() + ['consultorio_reserva' => $this->schedule(['hora_fin' => '08:30'])])
            ->assertSessionHasErrors('consultorio_reserva.hora_fin');
        $html = $this->get(route('expedientes.create'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="expediente-reservar-horario" checked/', $html);
        $this->assertMatchesRegularExpression('/name="consultorio_reserva\[dias_semana\]\[\]" value="3" checked/', $html);
        $this->assertStringContainsString('value="2026-11-18"', $html);
    }

    public static function roles(): array
    {
        return [['admin'], ['paps']];
    }

    #[DataProvider('roles')]
    public function test_monday_and_wednesday_create_four_independent_linked_rows_and_each_paps_occurrence_needs_approval(string $role): void
    {
        $this->actor->syncRoles([$role]);
        $suppliedUuid = (string) Str::uuid();
        $this->post(route('expedientes.store'), $this->newCase() + ['consultorio_reserva' => $this->schedule(['serie_uuid' => $suppliedUuid])])
            ->assertSessionHasNoErrors()->assertRedirect();

        $expediente = Expediente::query()->sole();
        $reservas = ConsultorioReserva::query()->where('expediente_id', $expediente->id)->orderBy('fecha')->get();
        $dates = ['2026-11-09', '2026-11-11', '2026-11-16', '2026-11-18'];
        $this->assertSame($dates, $reservas->map(fn ($r) => $r->fecha->toDateString())->all());
        $assignedUuid = $reservas->pluck('serie_uuid')->unique()->sole();
        $this->assertTrue(Str::isUuid($assignedUuid));
        $this->assertNotSame($suppliedUuid, $assignedUuid);
        $this->assertDatabaseCount('consultorio_reserva_solicitudes', $role === 'paps' ? 4 : 0);
        foreach ($reservas as $reserva) {
            $this->assertSame(18, $reserva->consultorio_numero);
            $this->assertSame(18, $reserva->cubiculo_numero);
            $this->assertTrue($reserva->origen_expediente);
            if ($role === 'paps') {
                $proposal = ConsultorioReservaSolicitud::query()->where('consultorio_reserva_id', $reserva->id)->sole();
                $this->assertSame('pendiente', $proposal->status);
                $this->assertSame($reserva->fecha->toDateString(), $proposal->payload['fecha']);
                $this->assertArrayNotHasKey('serie_uuid', $proposal->payload);
            }
        }
        if ($role === 'admin') {
            $calendar = $this->getJson(route('consultorios.availability', [
                'fecha_inicio' => $dates[0], 'fecha_fin' => end($dates), 'consultorio_numero' => 18,
            ]))->assertOk()->json('reservas');
            $this->assertSame($dates, array_column($calendar, 'fecha'));
            $this->get(route('expedientes.show', $expediente))->assertOk()->assertSee('Otros horarios')->assertSee('18/11/2026');
        }
    }

    public function test_repeated_standalone_form_accepts_more_than_one_weekday(): void
    {
        $this->post(route('consultorios.store'), $this->schedule() + ['consultorio_numero' => 18])->assertSessionHasNoErrors();
        $dates = ConsultorioReserva::query()->orderBy('fecha')->get()->map(fn ($r) => $r->fecha->toDateString())->all();
        $this->assertSame(['2026-11-09', '2026-11-11', '2026-11-16', '2026-11-18'], $dates);
        $this->assertDatabaseCount('expedientes', 0);
    }

    public function test_identical_weekly_submissions_get_distinct_server_generated_series_and_one_offs_cannot_join(): void
    {
        $suppliedUuid = (string) Str::uuid();
        $weekly = $this->schedule(['consultorio_numero' => 18, 'serie_uuid' => $suppliedUuid]);
        $this->post(route('consultorios.store'), $weekly)->assertSessionHasNoErrors();
        $firstUuid = ConsultorioReserva::query()->pluck('serie_uuid')->unique()->sole();
        $this->assertTrue(Str::isUuid($firstUuid));
        $this->assertNotSame($suppliedUuid, $firstUuid);
        // The earlier series vacated the slot. The room, dates and people in the next submission match exactly.
        ConsultorioReserva::query()->where('serie_uuid', $firstUuid)->update(['hora_inicio' => '11:00', 'hora_fin' => '12:00']);
        $this->post(route('consultorios.store'), $weekly)->assertSessionHasNoErrors();
        $other = ConsultorioReserva::query()->where('serie_uuid', '!=', $firstUuid)->get();
        $this->assertCount(4, $other);
        $this->assertTrue(Str::isUuid($other->pluck('serie_uuid')->unique()->sole()));

        $single = [
            'fecha' => '2026-11-19', 'hora_inicio' => '09:00', 'hora_fin' => '10:00',
            'estrategia' => 'Estrategia de prueba', 'serie_uuid' => $firstUuid,
        ];
        $this->post(route('consultorios.store'), $single + ['consultorio_numero' => 18])->assertSessionHasNoErrors();
        $this->post(route('expedientes.store'), $this->newCase() + ['consultorio_reserva' => array_replace($single, ['fecha' => '2026-11-20'])])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, ConsultorioReserva::query()->whereNull('serie_uuid')->count());
        $this->assertSame(4, ConsultorioReserva::query()->where('serie_uuid', $firstUuid)->count());
    }

    public function test_series_migration_never_groups_legacy_rows_and_repairs_partially_created_index(): void
    {
        $migration = require database_path('migrations/2026_11_18_000002_add_series_identity_to_consultorio_reservas.php');
        $migration->down();
        $single = [
            'hora_inicio' => '09:00', 'hora_fin' => '10:00', 'consultorio_numero' => 18,
            'estrategia' => 'Estrategia de prueba',
        ];
        foreach (['2026-11-10', '2026-11-17'] as $date) {
            $this->post(route('consultorios.store'), $single + ['fecha' => $date])->assertSessionHasNoErrors();
        }
        $this->postJson(route('consultorios.store'), $this->schedule(['consultorio_numero' => 18]))
            ->assertUnprocessable()->assertJsonValidationErrors('reserva');
        $this->assertDatabaseCount('consultorio_reservas', 2);
        $migration->up();
        $this->post(route('consultorios.store'), $this->schedule(['consultorio_numero' => 18]))->assertSessionHasNoErrors();
        $newIds = ConsultorioReserva::query()->whereNotNull('serie_uuid')->pluck('serie_uuid', 'id');
        $this->assertCount(4, $newIds);
        Schema::table('consultorio_reservas', static fn ($table) => $table->dropIndex('consultorio_reservas_serie_fecha_inicio_idx'));
        $migration->up();
        $migration->up();
        $this->assertTrue(Schema::hasIndex('consultorio_reservas', ['serie_uuid', 'fecha', 'hora_inicio', 'id']));
        $this->assertSame(2, ConsultorioReserva::query()->whereNull('serie_uuid')->count());
        $this->assertEquals($newIds, ConsultorioReserva::query()->whereNotNull('serie_uuid')->pluck('serie_uuid', 'id'));
    }

    public function test_start_boundary_uses_mexico_city_clock_and_refuses_in_progress_occurrences(): void
    {
        $row = new ConsultorioReserva(['fecha' => '2026-11-18', 'hora_inicio' => '09:00']);
        $this->assertTrue($row->hasNotStarted(Carbon::parse('2026-11-18 14:59:59', 'UTC')));
        $this->assertFalse($row->hasNotStarted(Carbon::parse('2026-11-18 15:00:00', 'UTC')));
        $this->assertFalse($row->hasNotStarted(Carbon::parse('2026-11-18 15:00:01', 'UTC')));
        $this->assertFalse((new ConsultorioReserva)->hasNotStarted());
    }

    public function test_a_late_conflict_rolls_back_the_new_case_all_other_occurrences_and_its_pending_approvals(): void
    {
        $this->actor->syncRoles(['paps']);
        $existing = ConsultorioReserva::query()->create([
            'fecha' => '2026-11-18', 'hora_inicio' => '09:30', 'hora_fin' => '10:30',
            'consultorio_numero' => 18, 'cubiculo_numero' => 18, 'creado_por' => $this->actor->id,
            'estrategia' => 'Estrategia de prueba',
        ]);
        $this->post(route('expedientes.store'), $this->newCase() + ['consultorio_reserva' => $this->schedule()])
            ->assertSessionHasErrors('consultorio_reserva.hora_inicio');
        $this->assertDatabaseCount('expedientes', 0);
        $this->assertDatabaseCount('timeline_eventos', 0);
        $this->assertDatabaseCount('consultorio_reserva_solicitudes', 0);
        $this->assertSame($existing->id, ConsultorioReserva::query()->sole()->id);
    }

    public function test_a_linked_room_with_missing_hours_and_strategy_never_creates_an_incomplete_reservation(): void
    {
        $schedule = $this->schedule();
        unset($schedule['hora_inicio'], $schedule['hora_fin'], $schedule['estrategia']);
        $this->postJson(route('expedientes.store'), $this->newCase() + ['consultorio_reserva' => $schedule])
            ->assertUnprocessable()->assertJsonValidationErrors(['consultorio_reserva.hora_inicio', 'consultorio_reserva.hora_fin', 'consultorio_reserva.estrategia']);

        CatalogoEstrategia::query()->where('nombre', 'Estrategia de prueba')->update(['activo' => false]);
        $this->postJson(route('expedientes.store'), $this->newCase() + ['consultorio_reserva' => $this->schedule()])
            ->assertUnprocessable()->assertJsonValidationErrors('consultorio_reserva.estrategia');

        $this->assertDatabaseCount('expedientes', 0);
        $this->assertDatabaseCount('consultorio_reservas', 0);
    }

    public static function invalidSchedules(): array
    {
        return [
            'over 180' => [['fecha_fin_repeticion' => '2040-11-18'], 'fecha_fin_repeticion'],
            'wrong range' => [['fecha_fin_repeticion' => '2026-11-08'], 'fecha_fin_repeticion'],
            'days omitted' => [['dias_semana' => []], 'dias_semana'],
            'invalid weekday' => [['dias_semana' => [7]], 'dias_semana.0'],
            'no matching dates' => [['fecha_fin_repeticion' => '2026-11-09', 'dias_semana' => [6]], 'dias_semana'],
            'earlier than hours' => [['hora_inicio' => '06:59'], 'hora_inicio'],
            'single Sunday' => [['modo_repeticion' => 'unica', 'fecha' => '2026-11-08'], 'fecha'],
        ];
    }

    #[DataProvider('invalidSchedules')]
    public function test_invalid_case_schedule_is_rejected_without_partial_creation(array $changes, string $key): void
    {
        $this->postJson(route('expedientes.store'), $this->newCase() + ['consultorio_reserva' => $this->schedule($changes)])
            ->assertUnprocessable()->assertJsonValidationErrors('consultorio_reserva.'.$key);
        $this->assertDatabaseCount('expedientes', 0);
        $this->assertDatabaseCount('consultorio_reservas', 0);
    }

    public static function institutionalRooms(): array
    {
        return [['with room'], ['without room']];
    }

    #[DataProvider('institutionalRooms')]
    public function test_case_without_reservation_can_be_registered_on_sunday_even_without_an_institutional_room(string $case): void
    {
        $html = $this->get(route('expedientes.create'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="expediente-datos-reserva"[^>]*disabled hidden/', $html);
        preg_match('/<select\s+name="cubiculo_id"[^>]*>/s', $html, $match);
        $this->assertNotEmpty($match);
        $this->assertStringNotContainsString('required', $match[0]);
        $payload = $this->newCase();
        if ($case === 'without room') {
            $payload['cubiculo_id'] = null;
        }
        $this->post(route('expedientes.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($payload['cubiculo_id'], Expediente::query()->sole()->cubiculo_id);
        $this->assertDatabaseCount('consultorio_reservas', 0);
    }
}
