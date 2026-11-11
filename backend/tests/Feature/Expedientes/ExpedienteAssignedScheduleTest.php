<?php

namespace Tests\Feature\Expedientes;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoConsultorio;
use App\Models\CatalogoEstrategia;
use App\Models\CatalogoTurno;
use App\Models\ConsultorioReserva;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteAssignedScheduleTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoConsultorio $room;

    private CatalogoEstrategia $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
        CatalogoCarrera::query()->create(['nombre' => 'Carrera sintética', 'activo' => true]);
        CatalogoTurno::query()->create(['nombre' => 'Matutino', 'activo' => true]);
        $this->room = CatalogoConsultorio::query()->create([
            'nombre' => 'Cubículo sintético norte', 'numero' => 18, 'activo' => true,
        ]);
        $this->strategy = CatalogoEstrategia::query()->create(['nombre' => 'Estrategia sintética', 'activo' => true]);
        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();
        CatalogoConsultorio::flushCache();
        CatalogoEstrategia::flushCache();
    }

    #[DataProvider('authorizedProfiles')]
    public function test_schedule_is_in_case_header_but_only_admin_gets_the_reschedule_link(string $role): void
    {
        $actor = $this->user($role);
        $case = $this->expediente($actor);
        $reservation = $this->reserve($case, $actor, '2026-11-10', '09:10:00', '10:25:00');
        $url = route('consultorios.edit', ['reserva' => $reservation, 'origen' => 'expediente']);

        $response = $this->actingAs($actor)->get(route('expedientes.show', $case))
            ->assertOk()->assertSeeText('Día y horario asignado')
            ->assertSeeText('Martes 10/11/2026')
            ->assertSeeText('09:10–10:25')
            ->assertSeeText($this->room->nombre);

        if ($role === 'admin') {
            $response->assertSee($url, false)->assertSeeText('Cambiar horario o cubículo');
        } else {
            $response->assertDontSee($url, false)->assertDontSeeText('Cambiar horario o cubículo');
        }
    }

    public function test_unlinked_reservations_are_never_guessed_from_room_or_facilitator(): void
    {
        $actor = $this->user('admin');
        $case = $this->expediente($actor);
        $this->reserve(null, $actor, '2026-11-10', '17:13:00', '18:13:00');
        $this->reserve($this->expediente($actor), $actor, '2026-11-12', '16:33:00', '17:33:00');

        $this->actingAs($actor)->get(route('expedientes.show', $case))
            ->assertOk()->assertSeeText('Sin horario vinculado')
            ->assertDontSeeText('17:13–18:13')->assertDontSeeText('16:33–17:33')
            ->assertDontSeeText('Cambiar horario o cubículo');
    }

    public function test_next_local_date_is_shown_first_then_other_linked_slots(): void
    {
        // Es 11 de noviembre en México, aunque ya sea 12 en UTC.
        $this->travelTo(Carbon::parse('2026-11-12T01:30:00Z'));
        $actor = $this->user('admin');
        $case = $this->expediente($actor);
        $latestPast = $this->reserve($case, $actor, '2026-11-10', '12:00:00', '13:00:00');
        $olderPast = $this->reserve($case, $actor, '2026-11-10', '09:00:00', '10:00:00');
        $tomorrow = $this->reserve($case, $actor, '2026-11-12', '09:00:00', '10:00:00');
        $today = $this->reserve($case, $actor, '2026-11-11', '20:00:00', '21:00:00');

        $response = $this->actingAs($actor)->get(route('expedientes.show', $case))
            ->assertOk()->assertSeeText('Otros horarios (3)');
        $this->assertSame(
            [$today->id, $tomorrow->id, $latestPast->id, $olderPast->id],
            $response->viewData('reservasCubiculo')->modelKeys(),
        );
        $this->travelTo(Carbon::parse('2026-11-13T16:00:00Z'));
        $this->assertSame($tomorrow->id, $case->reservasCubiculo()->firstOrFail()->id);
    }

    public function test_pending_request_shows_the_saved_schedule_and_not_the_proposal_or_edit_link(): void
    {
        $actor = $this->user('admin');
        $case = $this->expediente($actor);
        $reservation = $this->reserve($case, $actor, '2026-11-10', '08:00:00', '09:00:00');
        $reservation->solicitudes()->create([
            'requested_by' => $actor->id, 'tipo' => 'edicion', 'status' => 'pendiente',
            'payload' => ['fecha' => '2026-11-20', 'hora_inicio' => '18:20', 'hora_fin' => '19:20'],
        ]);

        $this->actingAs($actor)->get(route('expedientes.show', $case))
            ->assertOk()->assertSeeText('08:00–09:00')->assertSeeText('Solicitud pendiente')
            ->assertDontSeeText('18:20–19:20')->assertDontSeeText('Cambiar horario o cubículo');
    }

    public function test_archived_schedule_is_read_only_and_unauthorized_case_does_not_reveal_it(): void
    {
        $admin = $this->user('admin');
        $case = $this->expediente($admin);
        $this->reserve($case, $admin, '2026-11-10', '09:00:00', '10:00:00');
        $case->delete();

        $this->actingAs($admin)->get(route('expedientes.show', $case))
            ->assertOk()->assertSeeText('09:00–10:00')->assertDontSeeText('Cambiar horario o cubículo');
        $this->actingAs($this->user('alumno'))->get(route('expedientes.show', $case))->assertForbidden();
    }

    public function test_existing_reservation_prevents_changing_only_the_room_in_the_case_form(): void
    {
        $actor = $this->user('admin');
        $case = $this->expediente($actor);
        $reservation = $this->reserve($case, $actor, '2026-11-10', '09:00:00', '10:00:00');
        $otherRoom = CatalogoConsultorio::query()->create([
            'nombre' => 'Cubículo sintético sur', 'numero' => 23, 'activo' => true,
        ]);
        $this->actingAs($actor)->get(route('expedientes.edit', $case))
            ->assertOk()->assertSeeText('Ir a los horarios asignados');

        foreach ([$otherRoom->id, null] as $roomId) {
            $this->putJson(route('expedientes.update', $case), [
                'no_control' => $case->no_control, 'paciente' => $case->paciente,
                'apertura' => $case->apertura->toDateString(), 'carrera' => $case->carrera,
                'turno' => $case->turno, 'cubiculo_id' => $roomId,
            ])->assertUnprocessable()->assertJsonValidationErrors('cubiculo_id');
        }

        $this->assertSame($this->room->id, $case->fresh()->cubiculo_id);
        $this->assertSame($this->room->numero, $reservation->fresh()->consultorio_numero);
        $this->assertDatabaseCount('consultorio_reservas', 1);
    }

    public static function authorizedProfiles(): array
    {
        return [['admin'], ['paps'], ['docente'], ['estratega'], ['coordinador'], ['alumno']];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true, 'approved_at' => now()]);
        $user->assignRole($role);
        if ($role === 'coordinador') {
            $user->estrategiasCoordinadas()->attach($this->strategy);
        } elseif (in_array($role, ['docente', 'estratega'], true)) {
            $user->estrategiasParticipadas()->attach($this->strategy);
        }

        return $user;
    }

    private function expediente(User $actor): Expediente
    {
        return Expediente::factory()->create([
            'paciente' => 'Persona sintética para horarios', 'estado' => 'abierto',
            'carrera' => 'Carrera sintética', 'turno' => 'Matutino',
            'creado_por' => $actor->id, 'tutor_id' => $actor->id, 'facilitador_id' => $actor->id,
            'estrategia_id' => $this->strategy->id, 'cubiculo_id' => $this->room->id,
        ]);
    }

    private function reserve(?Expediente $case, User $actor, string $date, string $start, string $end): ConsultorioReserva
    {
        return ConsultorioReserva::query()->create([
            'expediente_id' => $case?->id, 'fecha' => $date, 'hora_inicio' => $start, 'hora_fin' => $end,
            'consultorio_numero' => $this->room->numero, 'cubiculo_numero' => $this->room->numero,
            'estrategia' => $this->strategy->nombre, 'creado_por' => $actor->id,
            'usuario_atendido_id' => $actor->id, 'origen_expediente' => $case !== null,
        ]);
    }
}
