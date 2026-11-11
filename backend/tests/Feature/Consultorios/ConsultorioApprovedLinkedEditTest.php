<?php

namespace Tests\Feature\Consultorios;

use App\Models\CatalogoConsultorio;
use App\Models\CatalogoEstrategia;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\Expediente;
use App\Models\TimelineEvento;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConsultorioApprovedLinkedEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $paps;

    private User $facilitator;

    private User $teacher;

    private CatalogoConsultorio $roomOne;

    private CatalogoConsultorio $roomTwo;

    private CatalogoEstrategia $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 12:00:00', 'America/Mexico_City'));
        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        $this->roomOne = CatalogoConsultorio::query()->updateOrCreate(['numero' => 1], [
            'nombre' => 'Cubículo Norte',
            'activo' => true,
        ]);
        $this->roomTwo = CatalogoConsultorio::query()->updateOrCreate(['numero' => 2], [
            'nombre' => 'Cubículo Sur',
            'activo' => true,
        ]);
        CatalogoConsultorio::flushCache();
        $this->strategy = CatalogoEstrategia::query()->create([
            'nombre' => 'Estrategia sintética vinculada',
            'activo' => true,
        ]);
        CatalogoEstrategia::flushCache();

        $this->admin = $this->user('admin');
        $this->paps = $this->user('paps');
        $this->facilitator = $this->user('alumno');
        $this->teacher = $this->user('docente');
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_admin_reprograms_the_same_approved_linked_reservation_from_expediente_without_overwriting_identity(): void
    {
        $case = $this->expediente($this->roomOne);
        $reservation = $this->reservation($case, $this->roomOne, [
            'fecha' => '2026-11-12',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '10:00:00',
        ]);
        $originalProposal = $this->fullPayload($reservation);
        $proposal = ConsultorioReservaSolicitud::query()->create([
            'consultorio_reserva_id' => $reservation->id,
            'requested_by' => $this->paps->id,
            'tipo' => 'edicion',
            'payload' => $originalProposal,
            'status' => 'pendiente',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.consultorios.solicitudes.approve', $proposal))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.consultorios.solicitudes.index'));

        $this->assertSame('atendida', $proposal->fresh()->status);
        $this->assertSame($originalProposal, $proposal->fresh()->payload);

        $editUrl = route('consultorios.edit', ['reserva' => $reservation, 'origen' => 'expediente']);
        $this->get(route('expedientes.show', $case))
            ->assertOk()
            ->assertSee($editUrl, false)
            ->assertSeeText('Jueves 12/11/2026')
            ->assertSeeText('09:00–10:00');
        $this->get($editUrl)
            ->assertOk()
            ->assertSeeText('Cambiar horario o cubículo del expediente')
            ->assertSee('name="edit_context" value="expediente"', false)
            ->assertSee('name="fecha"', false)
            ->assertSee('name="hora_inicio"', false)
            ->assertSee('name="hora_fin"', false)
            ->assertSee('name="consultorio_numero"', false)
            ->assertDontSee('name="estrategia"', false)
            ->assertDontSee('name="expediente_id"', false)
            ->assertDontSee('name="usuario_atendido_id"', false)
            ->assertDontSee('name="estratega_id"', false);

        $this->put(route('consultorios.update', ['reserva' => $reservation, 'origen' => 'expediente']), [
            'edit_context' => 'expediente',
            'fecha' => '2026-11-13',
            'hora_inicio' => '11:00',
            'hora_fin' => '12:00',
            'consultorio_numero' => $this->roomTwo->numero,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('expedientes.show', $case));

        $updated = $reservation->fresh();
        $this->assertSame($reservation->id, $updated->id);
        $this->assertDatabaseCount('consultorio_reservas', 1);
        $this->assertSame('2026-11-13', $updated->fecha->toDateString());
        $this->assertSame('11:00', substr($updated->hora_inicio, 0, 5));
        $this->assertSame('12:00', substr($updated->hora_fin, 0, 5));
        $this->assertSame($this->roomTwo->numero, $updated->consultorio_numero);
        $this->assertSame($this->roomTwo->numero, $updated->cubiculo_numero);
        $this->assertSame($case->id, (int) $updated->expediente_id);
        $this->assertSame($this->strategy->nombre, $updated->estrategia);
        $this->assertSame($this->facilitator->id, (int) $updated->usuario_atendido_id);
        $this->assertSame($this->teacher->id, (int) $updated->estratega_id);
        $this->assertTrue($updated->origen_expediente);
        $this->assertSame($this->roomTwo->id, (int) $case->fresh()->cubiculo_id);
        $this->assertSame($originalProposal, $proposal->fresh()->payload);

        $event = TimelineEvento::query()
            ->where('expediente_id', $case->id)
            ->where('evento', 'cubiculo.reprogramado')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame($reservation->id, $event->payload['reserva_id']);
        $this->assertSame([
            'fecha' => '2026-11-12',
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => $this->roomOne->numero,
        ], $event->payload['antes']);
        $this->assertSame([
            'fecha' => '2026-11-13',
            'hora_inicio' => '11:00',
            'hora_fin' => '12:00',
            'consultorio_numero' => $this->roomTwo->numero,
        ], $event->payload['despues']);

        $this->get(route('expedientes.show', $case))
            ->assertOk()
            ->assertSeeText('Viernes 13/11/2026')
            ->assertSeeText('11:00–12:00')
            ->assertSeeText($this->roomTwo->nombre);
    }

    public function test_pending_proposal_is_visible_as_a_diff_and_cannot_be_bypassed_by_direct_edit(): void
    {
        $case = $this->expediente($this->roomOne);
        $reservation = $this->reservation($case, $this->roomOne);
        $proposalPayload = $this->fullPayload($reservation, [
            'fecha' => '2026-11-14',
            'hora_inicio' => '13:00',
            'hora_fin' => '14:00',
            'consultorio_numero' => $this->roomTwo->numero,
            'cubiculo_numero' => $this->roomTwo->numero,
        ]);
        $proposal = ConsultorioReservaSolicitud::query()->create([
            'consultorio_reserva_id' => $reservation->id,
            'requested_by' => $this->paps->id,
            'tipo' => 'edicion',
            'payload' => $proposalPayload,
            'status' => 'pendiente',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.consultorios.solicitudes.index'))
            ->assertOk()
            ->assertSeeText('Reserva actual')
            ->assertSeeText('Cambio solicitado')
            ->assertSeeText('13:00-14:00')
            ->assertSeeText($this->roomTwo->nombre)
            ->assertSeeText('Aprobar tal cual')
            ->assertDontSeeText('Editar reserva');

        $this->get(route('consultorios.edit', ['reserva' => $reservation, 'origen' => 'expediente']))
            ->assertStatus(409);
        $this->put(route('consultorios.update', $reservation), $this->fullPayload($reservation, [
            'hora_inicio' => '15:00',
            'hora_fin' => '16:00',
        ]))->assertSessionHasErrors('reserva');

        $this->assertSame('09:00', substr($reservation->fresh()->hora_inicio, 0, 5));
        $this->assertSame($this->roomOne->id, (int) $case->fresh()->cubiculo_id);
        $this->assertDatabaseCount('timeline_eventos', 0);
        $this->assertSame($proposalPayload, $proposal->fresh()->payload);
    }

    public function test_overlap_archived_case_and_non_admin_cannot_reprogram_a_linked_reservation(): void
    {
        $case = $this->expediente($this->roomOne);
        $reservation = $this->reservation($case, $this->roomOne);
        $blocker = $this->reservation(null, $this->roomTwo, [
            'fecha' => $reservation->fecha->toDateString(),
            'hora_inicio' => '11:00:00',
            'hora_fin' => '12:00:00',
            'origen_expediente' => false,
        ]);

        $this->actingAs($this->admin)
            ->put(route('consultorios.update', ['reserva' => $reservation, 'origen' => 'expediente']), [
                'edit_context' => 'expediente',
                'fecha' => $reservation->fecha->toDateString(),
                'hora_inicio' => '11:30',
                'hora_fin' => '12:30',
                'consultorio_numero' => $this->roomTwo->numero,
            ])->assertSessionHasErrors('hora_inicio');

        $this->assertSame('09:00', substr($reservation->fresh()->hora_inicio, 0, 5));
        $this->assertSame($this->roomOne->id, (int) $case->fresh()->cubiculo_id);
        $this->assertTrue($blocker->fresh()->exists);
        $this->assertDatabaseCount('timeline_eventos', 0);

        $this->actingAs($this->paps)
            ->get(route('consultorios.edit', ['reserva' => $reservation, 'origen' => 'expediente']))
            ->assertForbidden();
        $this->put(route('consultorios.update', $reservation), $this->fullPayload($reservation))
            ->assertForbidden();

        $case->delete();
        $this->actingAs($this->admin)
            ->get(route('consultorios.edit', ['reserva' => $reservation, 'origen' => 'expediente']))
            ->assertForbidden();
    }

    public function test_multiple_reservations_only_move_the_header_when_the_edited_reservation_becomes_effective(): void
    {
        $case = $this->expediente($this->roomOne);
        $primary = $this->reservation($case, $this->roomOne, [
            'fecha' => '2026-11-12',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '10:00:00',
        ]);
        $secondary = $this->reservation($case, $this->roomTwo, [
            'fecha' => '2026-11-15',
            'hora_inicio' => '11:00:00',
            'hora_fin' => '12:00:00',
        ]);

        $this->actingAs($this->admin)->put(
            route('consultorios.update', ['reserva' => $secondary, 'origen' => 'expediente']),
            $this->schedulePayload('2026-11-16', '11:00', '12:00', $this->roomTwo),
        )->assertSessionHasNoErrors();
        $this->assertSame($this->roomOne->id, (int) $case->fresh()->cubiculo_id);

        $this->put(
            route('consultorios.update', ['reserva' => $secondary, 'origen' => 'expediente']),
            $this->schedulePayload('2026-11-11', '11:00', '12:00', $this->roomTwo),
        )->assertSessionHasNoErrors();

        $this->assertDatabaseCount('consultorio_reservas', 2);
        $this->assertSame('2026-11-12', $primary->fresh()->fecha->toDateString());
        $this->assertSame('2026-11-11', $secondary->fresh()->fecha->toDateString());
        $this->assertSame($this->roomTwo->id, (int) $case->fresh()->cubiculo_id);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create([
            'approved_at' => now(),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function expediente(CatalogoConsultorio $room): Expediente
    {
        return Expediente::factory()->create([
            'creado_por' => $this->admin->id,
            'facilitador_id' => $this->facilitator->id,
            'tutor_id' => $this->teacher->id,
            'estrategia_id' => $this->strategy->id,
            'cubiculo_id' => $room->id,
            'estado' => 'abierto',
        ]);
    }

    private function reservation(?Expediente $case, CatalogoConsultorio $room, array $overrides = []): ConsultorioReserva
    {
        return ConsultorioReserva::query()->create(array_replace([
            'fecha' => '2026-11-12',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '10:00:00',
            'consultorio_numero' => $room->numero,
            'cubiculo_numero' => $room->numero,
            'estrategia' => $this->strategy->nombre,
            'expediente_id' => $case?->id,
            'usuario_atendido_id' => $this->facilitator->id,
            'estratega_id' => $this->teacher->id,
            'creado_por' => $this->admin->id,
            'origen_expediente' => $case !== null,
        ], $overrides));
    }

    private function fullPayload(ConsultorioReserva $reservation, array $overrides = []): array
    {
        return array_replace([
            'fecha' => $reservation->fecha->toDateString(),
            'hora_inicio' => substr($reservation->hora_inicio, 0, 5),
            'hora_fin' => substr($reservation->hora_fin, 0, 5),
            'consultorio_numero' => $reservation->consultorio_numero,
            'cubiculo_numero' => $reservation->consultorio_numero,
            'estrategia' => $reservation->estrategia,
            'expediente_id' => $reservation->expediente_id,
            'usuario_atendido_id' => $reservation->usuario_atendido_id,
            'estratega_id' => $reservation->estratega_id,
            'supervisor_id' => $reservation->supervisor_id,
        ], $overrides);
    }

    private function schedulePayload(
        string $date,
        string $start,
        string $end,
        CatalogoConsultorio $room,
    ): array {
        return [
            'edit_context' => 'expediente',
            'fecha' => $date,
            'hora_inicio' => $start,
            'hora_fin' => $end,
            'consultorio_numero' => $room->numero,
        ];
    }
}
