<?php

namespace Tests\Feature\Consultorios;

use App\Models\CatalogoConsultorio;
use App\Models\CatalogoEstrategia;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConsultorioSolicitudResolutionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $paps;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create()->assignRole('admin');
        $this->paps = User::factory()->create(['approved_at' => now()])->assignRole('paps');
        CatalogoConsultorio::query()->updateOrCreate(['numero' => 1], ['nombre' => 'Norte', 'activo' => true]);
        CatalogoConsultorio::query()->updateOrCreate(['numero' => 2], ['nombre' => 'Sur', 'activo' => true]);
        CatalogoEstrategia::query()->firstOrCreate(['nombre' => 'Estrategia sintética'], ['activo' => true]);
    }

    public function test_conflicting_editions_remain_pending_until_a_free_correction_is_approved(): void
    {
        $case = Expediente::factory()->create();
        $one = $this->reservation(['expediente_id' => $case->id, 'estratega_id' => $this->admin->id]);
        $two = $this->reservation();
        $proposed = $this->schedule(['hora_inicio' => '07:00:00', 'hora_fin' => '08:00:00']);
        $a = $this->proposal($one, $proposed);
        $b = $this->proposal($two, $proposed);
        $list = route('admin.consultorios.solicitudes.index');
        $this->actingAs($this->admin)->get($list)->assertOk()
            ->assertSeeText('Aprobar tal cual')->assertSeeText('Corregir y aprobar')->assertSeeText('Rechazar solicitud');
        $failed = $this->from($list)->post(route('admin.consultorios.solicitudes.approve', $a), ['_fila' => (string) $a->id]);
        $failed->assertSessionHasErrors('hora_inicio')->assertRedirect($list);
        $this->get($list)->assertOk()->assertSeeText('No se completó la acción sobre la solicitud #'.$a->id);
        $this->assertSame('pendiente', $a->fresh()->status);
        $this->assertNull($a->fresh()->resolucion);
        $this->assertSame('07:00', substr($one->fresh()->hora_inicio, 0, 5));

        $correct = route('admin.consultorios.solicitudes.correct', $a);
        $data = $this->schedule(['hora_inicio' => '08:00', 'hora_fin' => '09:00', 'consultorio_numero' => 2]) + ['motivo' => 'Mover al bloque disponible'];
        $this->postJson($correct, $data + ['expediente_id' => null])->assertUnprocessable()->assertJsonValidationErrors('expediente_id');
        $this->post($correct, $data)->assertSessionHasNoErrors()->assertRedirect($list);
        $a->refresh();
        $this->assertSame('atendida', $a->status);
        $this->assertSame($proposed, $a->payload);
        $this->assertSame('corregida', $a->resolucion['decision']);
        $this->assertSame('Mover al bloque disponible', $a->resolucion['motivo']);
        $this->assertSame($this->admin->id, $a->resolucion['actor_id']);
        $this->assertSame(1, $a->resolucion['antes']['consultorio_numero']);
        $this->assertSame(2, $a->resolucion['despues']['consultorio_numero']);
        $this->assertSame($case->id, $one->fresh()->expediente_id);
        $this->assertSame($this->admin->id, $one->fresh()->estratega_id);
        $this->assertSame($one->id, $a->consultorio_reserva_id);
        $this->assertSame(2, $one->fresh()->consultorio_numero);
        $this->assertSame(1, $two->fresh()->consultorio_numero);
        $this->assertSame('pendiente', $b->fresh()->status);
        $this->get($list)->assertOk()->assertSeeText('Decisiones recientes')->assertSeeText('Mover al bloque disponible');
        $first = $a->resolucion;
        $this->postJson(route('admin.consultorios.solicitudes.reject', $a), ['motivo' => 'Segundo intento'])->assertUnprocessable();
        $this->assertSame($first, $a->fresh()->resolucion);
        $this->post(route('admin.consultorios.solicitudes.approve', $b))->assertSessionHasNoErrors()->assertRedirect($list);
        $this->assertSame('aprobada', $b->fresh()->resolucion['decision']);
    }

    public function test_rejecting_deletion_or_edit_requires_reason_preserves_reservation_and_does_not_close_siblings(): void
    {
        $reserva = $this->reservation();
        $edit = $this->proposal($reserva);
        $cancel = $this->proposal($reserva, null, 'baja');
        $original = $reserva->fresh()->getAttributes();
        $route = route('admin.consultorios.solicitudes.reject', $cancel);
        $this->actingAs($this->paps)->postJson($route, ['motivo' => 'Denegar'])->assertForbidden();
        $this->postJson(route('admin.consultorios.solicitudes.correct', $edit), $this->schedule() + ['motivo' => 'Denegar'])->assertForbidden();
        $this->get(route('admin.consultorios.solicitudes.index'))->assertOk()->assertDontSeeText('Rechazar solicitud');
        $this->actingAs($this->admin)->postJson($route, ['motivo' => '  '])->assertUnprocessable()->assertJsonValidationErrors('motivo');
        $this->post($route, ['motivo' => 'Se mantiene la cita'])->assertSessionHasNoErrors();
        $this->assertSame($original, $reserva->fresh()->getAttributes());
        $this->assertSame('pendiente', $edit->fresh()->status);
        $audit = $cancel->fresh()->resolucion;
        $this->assertSame('rechazada', $audit['decision']);
        $this->assertSame($audit['antes'], $audit['despues']);
        $this->assertSame($reserva->id, $audit['reserva_id']);
        $otherPaps = User::factory()->create(['approved_at' => now()])->assignRole('paps');
        $this->actingAs($otherPaps)->get(route('admin.consultorios.solicitudes.index'))->assertOk()->assertDontSeeText('Se mantiene la cita');
        $this->actingAs($this->paps)->get(route('admin.consultorios.solicitudes.index'))->assertOk()->assertSeeText('Se mantiene la cita');
    }

    public function test_legacy_pending_review_cannot_make_a_started_reservation_deletable_by_moving_it_to_the_future(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 10:00:00', 'America/Mexico_City'));
        $row = $this->reservation();
        $original = $row->fresh()->getAttributes();
        $future = $this->schedule(['fecha' => '2026-11-18', 'hora_inicio' => '10:01', 'hora_fin' => '11:00']);
        $edit = $this->proposal($row, $future);
        $this->actingAs($this->admin)->postJson(route('admin.consultorios.solicitudes.approve', $edit))
            ->assertUnprocessable()->assertJsonValidationErrors('fecha')
            ->assertJsonPath('errors.fecha.0', 'Una reserva que ya comenzó no puede convertirse en una reserva futura. Conserva el registro histórico y crea una reserva nueva para el horario futuro.');
        $this->postJson(route('admin.consultorios.solicitudes.correct', $edit), $future + ['motivo' => 'Solicitud de cambio'])
            ->assertUnprocessable()->assertJsonValidationErrors('fecha');
        $this->assertSame($original, $row->fresh()->getAttributes());
        $this->assertSame('pendiente', $edit->fresh()->status);
        $this->assertNull($edit->fresh()->resolucion);

        // The pending historical request can still be reconciled at its recorded slot.
        $this->post(route('admin.consultorios.solicitudes.correct', $edit), $this->schedule() + ['motivo' => 'Conservar el horario registrado'])
            ->assertSessionHasNoErrors();
        $this->assertSame('corregida', $edit->fresh()->resolucion['decision']);
        $this->deleteJson(route('consultorios.destroy', $row))->assertUnprocessable()->assertJsonValidationErrors('reserva');
        $this->assertDatabaseHas('consultorio_reservas', ['id' => $row->id]);
    }

    public function test_legacy_empty_proposal_can_be_explicitly_scheduled_without_guessing_identities(): void
    {
        $case = Expediente::factory()->create();
        $reserva = $this->reservation(['expediente_id' => $case->id, 'usuario_atendido_id' => $this->paps->id]);
        $empty = $this->proposal($reserva);
        $this->actingAs($this->admin)->postJson(route('admin.consultorios.solicitudes.approve', $empty))
            ->assertUnprocessable()->assertJsonValidationErrors('solicitud');
        $route = route('admin.consultorios.solicitudes.correct', $empty);
        $data = $this->schedule() + ['motivo' => 'Acordado con el solicitante'];
        $this->postJson($route, array_replace($data, ['fecha' => '2026-11-22']))->assertUnprocessable()->assertJsonValidationErrors('fecha');
        $this->postJson($route, array_replace($data, ['hora_inicio' => '06:00']))->assertUnprocessable()->assertJsonValidationErrors('hora_inicio');
        $this->postJson($route, array_replace($data, ['consultorio_numero' => 9999]))->assertUnprocessable()->assertJsonValidationErrors('consultorio_numero');
        $this->assertNull($empty->fresh()->resolucion);
        $this->post($route, $data)->assertSessionHasNoErrors();
        $this->assertNull($empty->fresh()->payload);
        $this->assertSame($case->id, $reserva->fresh()->expediente_id);
        $this->assertSame($this->paps->id, $reserva->fresh()->usuario_atendido_id);
    }

    public function test_cancellation_preserves_its_audit_and_orphans_can_be_closed_without_affecting_other_rooms(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2)->startOfDay());
        $reserva = $this->reservation();
        $other = $this->reservation(['consultorio_numero' => 2]);
        $pending = $this->proposal($reserva, $this->schedule());
        $cancel = $this->proposal($reserva, null, 'baja');
        $this->actingAs($this->admin)->post(route('admin.consultorios.solicitudes.approve', $cancel))->assertSessionHasNoErrors();
        $this->assertNull($reserva->fresh());
        $this->assertNull($pending->fresh()->consultorio_reserva_id);
        $this->assertSame('aprobada', $cancel->fresh()->resolucion['decision']);
        $this->assertSame($reserva->id, $cancel->fresh()->resolucion['reserva_id']);
        $this->assertNull($cancel->fresh()->resolucion['despues']);
        $this->postJson(route('admin.consultorios.solicitudes.approve', $pending))->assertUnprocessable()->assertJsonValidationErrors('solicitud');
        $this->post(route('admin.consultorios.solicitudes.reject', $pending), ['motivo' => 'La reserva ya no existe'])->assertSessionHasNoErrors();
        $this->assertSame('rechazada', $pending->fresh()->resolucion['decision']);
        $this->assertNull($pending->fresh()->resolucion['antes']);
        $this->assertNotNull($other->fresh());
    }

    public function test_archiving_a_case_while_requests_are_pending_allows_rejection_but_not_edit_or_cancellation(): void
    {
        $case = Expediente::factory()->create();
        $reserva = $this->reservation(['expediente_id' => $case->id]);
        $edit = $this->proposal($reserva, $this->schedule());
        $cancel = $this->proposal($reserva, null, 'baja');
        $case->delete();
        $before = $reserva->fresh()->getAttributes();
        $this->actingAs($this->admin)->postJson(route('admin.consultorios.solicitudes.correct', $edit), $this->schedule() + ['motivo' => 'Cambiar horario'])
            ->assertUnprocessable()->assertJsonValidationErrors('reserva');
        $this->postJson(route('admin.consultorios.solicitudes.approve', $cancel))->assertUnprocessable()->assertJsonValidationErrors('solicitud');
        $this->assertNull($edit->fresh()->resolucion);
        $this->assertNull($cancel->fresh()->resolucion);
        $this->post(route('admin.consultorios.solicitudes.reject', $edit), ['motivo' => 'El expediente está archivado'])->assertSessionHasNoErrors();
        $this->assertSame($before, $reserva->fresh()->getAttributes());
        $this->assertSame('pendiente', $cancel->fresh()->status);
    }

    public function test_correcting_the_schedule_applies_only_identity_changes_in_the_original_proposal(): void
    {
        $old = Expediente::factory()->create();
        $next = Expediente::factory()->create();
        $reserva = $this->reservation(['expediente_id' => $old->id, 'supervisor_id' => $this->admin->id]);
        $payload = $this->schedule() + ['expediente_id' => $next->id];
        $relink = $this->proposal($reserva, $payload);
        $this->actingAs($this->admin)->post(route('admin.consultorios.solicitudes.correct', $relink), $this->schedule(['hora_fin' => '09:00']) + ['motivo' => 'Dos horas autorizadas'])
            ->assertSessionHasNoErrors();
        $this->assertSame($payload, $relink->fresh()->payload);
        $this->assertSame($next->id, $reserva->fresh()->expediente_id);
        $this->assertSame($this->admin->id, $reserva->fresh()->supervisor_id);
        $this->assertSame($old->id, $relink->fresh()->resolucion['antes']['expediente_id']);
        $this->assertSame($next->id, $relink->fresh()->resolucion['despues']['expediente_id']);
    }

    public function test_migration_is_replayable_and_unaudited_decisions_are_prevented_in_the_upgrade_window(): void
    {
        $migration = require database_path('migrations/2026_11_18_000001_add_resolution_to_consultorio_solicitudes.php');
        $migration->down();
        $reserva = $this->reservation();
        $empty = $this->proposal($reserva);
        $payload = $this->schedule(['hora_inicio' => '08:00', 'hora_fin' => '09:00']);
        $legacy = $this->proposal($reserva, $payload);
        $before = $reserva->fresh()->getAttributes();
        $this->actingAs($this->admin)->get(route('admin.consultorios.solicitudes.index'))
            ->assertOk()->assertSeeText('Las decisiones están temporalmente en pausa')->assertDontSeeText('Aprobar tal cual');
        foreach (['approve', 'correct', 'reject'] as $action) {
            $this->postJson(route('admin.consultorios.solicitudes.'.$action, $legacy), $payload + ['motivo' => 'Pendiente'])
                ->assertUnprocessable()->assertJsonValidationErrors('solicitud');
        }
        $migration->up();
        $migration->up();
        $this->assertSame($before, $reserva->fresh()->getAttributes());
        $this->assertSame('pendiente', $empty->fresh()->status);
        $this->assertNull($empty->fresh()->payload);
        $this->assertNull($empty->fresh()->resolucion);
        $this->assertSame('pendiente', $legacy->fresh()->status);
        $this->assertSame($payload, $legacy->fresh()->payload);
        $this->assertNull($legacy->fresh()->resolucion);
        $this->post(route('admin.consultorios.solicitudes.approve', $legacy))->assertSessionHasNoErrors();
        $audit = $legacy->fresh()->resolucion;
        $migration->up();
        $this->assertSame($audit, $legacy->fresh()->resolucion);
        $this->assertSame('atendida', $legacy->fresh()->status);
        $this->assertNull($empty->fresh()->resolucion);
    }

    private function schedule(array $overrides = []): array
    {
        return array_replace(['fecha' => '2026-11-03', 'hora_inicio' => '07:00', 'hora_fin' => '08:00', 'consultorio_numero' => 1], $overrides);
    }

    private function reservation(array $overrides = []): ConsultorioReserva
    {
        return ConsultorioReserva::query()->create(array_replace($this->schedule(), [
            'cubiculo_numero' => 1, 'estrategia' => 'Estrategia sintética', 'creado_por' => $this->paps->id,
        ], $overrides));
    }

    private function proposal(ConsultorioReserva $reserva, ?array $payload = null, string $tipo = 'edicion'): ConsultorioReservaSolicitud
    {
        return ConsultorioReservaSolicitud::query()->create([
            'consultorio_reserva_id' => $reserva->id, 'requested_by' => $this->paps->id,
            'tipo' => $tipo, 'payload' => $payload, 'status' => 'pendiente',
        ]);
    }
}
