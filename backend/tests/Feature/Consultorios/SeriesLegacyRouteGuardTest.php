<?php

namespace Tests\Feature\Consultorios;

use App\Models\CatalogoConsultorio;
use App\Models\CatalogoEstrategia;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeriesLegacyRouteGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $paps;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-18 15:00:00', 'UTC')); // 09:00 in Mexico City.
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create()->assignRole('admin');
        $this->paps = User::factory()->create(['approved_at' => now()])->assignRole('paps');
        CatalogoConsultorio::query()->updateOrCreate(['numero' => 12], ['nombre' => 'Sala de prueba', 'activo' => true]);
        CatalogoEstrategia::query()->create(['nombre' => 'Estrategia de prueba', 'activo' => true]);
        $this->actingAs($this->admin);
    }

    private function slot(string $date): array
    {
        return ['fecha' => $date, 'hora_inicio' => '09:00', 'hora_fin' => '10:00', 'consultorio_numero' => 12,
            'cubiculo_numero' => 12, 'estrategia' => 'Estrategia de prueba'];
    }

    private function row(string $date, ?string $uuid = null): ConsultorioReserva
    {
        return ConsultorioReserva::createOccurrence($this->slot($date) + ['creado_por' => $this->paps->id], $uuid);
    }

    private function requestFor(ConsultorioReserva $row, string $type): ConsultorioReservaSolicitud
    {
        return ConsultorioReservaSolicitud::query()->create([
            'consultorio_reserva_id' => $row->id, 'requested_by' => $this->paps->id,
            'tipo' => $type, 'status' => 'pendiente',
            'payload' => $type === 'baja' ? null : array_replace($this->slot($row->fecha->toDateString()), ['hora_fin' => '11:00']),
        ]);
    }

    public function test_old_edit_or_cancellation_cannot_modify_an_occurrence_that_just_started(): void
    {
        $row = $this->row('2026-11-18');
        $before = $row->fresh()->getAttributes();
        $this->get(route('consultorios.edit', $row))->assertStatus(409);
        $this->putJson(route('consultorios.update', $row), $this->slot('2026-11-19'))
            ->assertUnprocessable()->assertJsonValidationErrors('reserva');
        $this->deleteJson(route('consultorios.destroy', $row))->assertUnprocessable()->assertJsonValidationErrors('reserva');
        $this->actingAs($this->paps)->deleteJson(route('consultorios.request-destroy', $row))
            ->assertUnprocessable()->assertJsonValidationErrors('reserva');
        $this->assertSame($before, $row->fresh()->getAttributes());
        $this->assertDatabaseCount('consultorio_reserva_solicitudes', 0);
    }

    public function test_old_update_cannot_move_a_future_slot_into_the_past_or_change_a_series_schedule(): void
    {
        $single = $this->row('2026-11-20');
        $series = $this->row('2026-11-21', (string) Str::uuid());
        $first = $single->fresh()->getAttributes();
        $second = $series->fresh()->getAttributes();
        $this->putJson(route('consultorios.update', $single), $this->slot('2026-11-18'))
            ->assertUnprocessable()->assertJsonValidationErrors('fecha');
        $this->putJson(route('consultorios.update', $series), $this->slot('2026-11-23'))
            ->assertUnprocessable()->assertJsonValidationErrors('reserva');
        $backdate = $this->requestFor($single, 'edicion');
        $backdate->update(['payload' => $this->slot('2026-11-17')]);
        $this->postJson(route('admin.consultorios.solicitudes.approve', $backdate))
            ->assertUnprocessable()->assertJsonValidationErrors('fecha');
        $this->assertSame('pendiente', $backdate->fresh()->status);
        $this->assertSame($first, $single->fresh()->getAttributes());
        $this->assertSame($second, $series->fresh()->getAttributes());
    }

    public function test_series_members_cannot_be_cancelled_using_direct_or_mixed_bulk_delete(): void
    {
        $single = $this->row('2026-11-19');
        $series = $this->row('2026-11-20', (string) Str::uuid());
        $this->deleteJson(route('consultorios.destroy', $series))->assertUnprocessable()->assertJsonValidationErrors('reserva');
        $this->deleteJson(route('consultorios.bulk-destroy'), ['reservas' => [$single->id, $series->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('reserva');
        $this->assertSame([$single->id, $series->id], ConsultorioReserva::query()->orderBy('id')->pluck('id')->all());
    }

    public function test_pending_past_legacy_edit_can_be_settled_but_past_cancellation_or_series_edit_cannot(): void
    {
        $legacy = $this->row('2026-11-16');
        $member = $this->row('2026-11-17', (string) Str::uuid());
        $oldEdit = $this->requestFor($legacy, 'edicion');
        $oldCancel = $this->requestFor($legacy, 'baja');
        $seriesEdit = $this->requestFor($member, 'edicion');
        $this->post(route('admin.consultorios.solicitudes.approve', $oldEdit))->assertSessionHasNoErrors();
        $this->assertSame('aprobada', $oldEdit->fresh()->resolucion['decision']);
        $this->assertSame('11:00', substr($legacy->fresh()->hora_fin, 0, 5));
        $this->postJson(route('admin.consultorios.solicitudes.approve', $oldCancel))->assertUnprocessable()->assertJsonValidationErrors('reserva');
        $this->postJson(route('admin.consultorios.solicitudes.approve', $seriesEdit))->assertUnprocessable()->assertJsonValidationErrors('reserva');
        foreach ([$oldCancel, $seriesEdit] as $pending) {
            $this->assertSame('pendiente', $pending->fresh()->status);
            $this->post(route('admin.consultorios.solicitudes.reject', $pending), ['motivo' => 'Se conserva el horario registrado.'])
                ->assertSessionHasNoErrors();
            $this->assertSame('rechazada', $pending->fresh()->resolucion['decision']);
        }
        $this->assertSame('10:00', substr($member->fresh()->hora_fin, 0, 5));
        $this->assertDatabaseCount('consultorio_reservas', 2);
    }

    public function test_changing_responsibility_in_the_old_form_detaches_only_that_occurrence(): void
    {
        $uuid = (string) Str::uuid();
        $row = $this->row('2026-11-23', $uuid);
        $sibling = $this->row('2026-11-24', $uuid);
        $before = $sibling->fresh()->getAttributes();
        $this->put(route('consultorios.update', $row), $this->slot('2026-11-23') + ['supervisor_id' => $this->paps->id])
            ->assertSessionHasNoErrors();
        $this->assertNull($row->fresh()->serie_uuid);
        $this->assertSame($this->paps->id, $row->fresh()->supervisor_id);
        $this->assertSame($before, $sibling->fresh()->getAttributes());
    }
}
