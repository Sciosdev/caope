<?php

namespace Tests\Feature\Consultorios;

use App\Models\CatalogoConsultorio;
use App\Models\CatalogoEstrategia;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConsultorioCalendarPendingTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-11-18';

    private const ROOM = 41;

    private User $admin;

    private User $paps;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);

        foreach (['admin', 'paps', 'alumno'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->admin = User::factory()->create()->assignRole('admin');
        $this->paps = User::factory()->create(['approved_at' => now()])->assignRole('paps');
        CatalogoConsultorio::query()->create(['numero' => self::ROOM, 'nombre' => 'Aula simulada', 'activo' => true]);
        CatalogoEstrategia::query()->create(['nombre' => 'Grupo de prueba', 'activo' => true]);
    }

    public function test_global_readers_see_the_persisted_slots_not_unapproved_edits_or_cancellations(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse(self::DATE.' 07:00', 'America/Mexico_City'));
        $edit = $this->reservation('08:00', '09:00');
        $cancel = $this->reservation('10:00', '11:00');
        $pastDecision = $this->reservation('12:00', '13:00');

        $proposal = $this->requestFor($edit, 'edicion', ['fecha' => '2026-11-19', 'hora_inicio' => '15:00', 'hora_fin' => '16:00', 'estrategia' => 'Propuesta no aplicada']);
        $this->requestFor($cancel, 'edicion');
        $this->requestFor($cancel, 'baja'); // Pending duplicates must not duplicate or free the slot.
        $this->requestFor($pastDecision, 'edicion', [], 'atendida');

        foreach ([$this->admin, $this->paps] as $actor) {
            $this->actingAs($actor)->getJson(route('consultorios.availability', [
                'fecha_inicio' => self::DATE,
                'fecha_fin' => '2026-11-19',
                'consultorio_numero' => self::ROOM,
            ]))->assertOk()->assertJsonCount(3, 'reservas')
                ->assertJsonPath('reservas.0.solicitud_pendiente', 'edicion')
                ->assertJsonPath('reservas.1.solicitud_pendiente', 'baja')
                ->assertJsonStructure(['reservas' => ['*' => ['solicitud_pendiente']]])
                ->assertJsonPath('reservas.2.solicitud_pendiente', null)
                ->assertJsonPath('reservas.0.fecha', self::DATE)
                ->assertJsonPath('reservas.0.hora_inicio', fn ($hora) => substr($hora, 0, 5) === '08:00')
                ->assertJsonPath('reservas.1.fecha', self::DATE)
                ->assertJsonPath('reservas.2.fecha', self::DATE)
                ->assertJsonMissing(['estrategia' => 'Propuesta no aplicada'])
                ->assertJsonMissingPath('reservas.0.payload');
        }

        $this->actingAs($this->admin)->get(route('consultorios.index', ['fecha' => self::DATE, 'consultorio_numero' => self::ROOM]))
            ->assertOk()->assertSee('Edición pendiente')->assertSee('Baja pendiente')->assertSee('Gestionar solicitudes')
            ->assertDontSee(route('consultorios.edit', $edit), false)
            ->assertDontSee(route('consultorios.edit', $cancel), false)
            ->assertSee(route('consultorios.edit', $pastDecision), false)
            ->assertViewHas('ocupacionPorCubiculo', fn ($rooms) => $rooms->get(self::ROOM)->modelKeys() === [$edit->id, $cancel->id, $pastDecision->id]);

        $this->assertSame('pendiente', $proposal->fresh()->status);
        $this->assertSame(self::DATE, $edit->fresh()->fecha->toDateString());
        $this->assertDatabaseCount('consultorio_reservas', 3);
    }

    public function test_pending_visibility_still_requires_an_actual_assignment_and_never_leaks_the_proposal(): void
    {
        $facilitador = User::factory()->create()->assignRole('alumno');
        $otro = User::factory()->create()->assignRole('alumno');
        $visible = $this->reservation('08:00', '09:00', $facilitador);
        $hidden = $this->reservation('10:00', '11:00', $otro);
        $this->requestFor($visible, 'edicion', ['usuario_atendido_id' => $otro->id, 'estrategia' => 'Texto privado de solicitud']);
        $this->requestFor($hidden, 'baja');

        $this->actingAs($facilitador)->getJson(route('consultorios.availability', ['fecha' => self::DATE]))
            ->assertOk()->assertJsonCount(1, 'reservas')
            ->assertJsonPath('reservas.0.usuario_atendido_id', $facilitador->id)
            ->assertJsonPath('reservas.0.solicitud_pendiente', 'edicion')
            ->assertJsonMissing(['usuario_atendido_id' => $otro->id])
            ->assertJsonMissing(['estrategia' => 'Texto privado de solicitud'])
            ->assertJsonMissingPath('reservas.0.payload')
            ->assertJsonMissingPath('reservas.0.requested_by');

        $this->get(route('consultorios.index', ['fecha' => self::DATE, 'consultorio_numero' => self::ROOM]))
            ->assertOk()
            ->assertViewHas('consultoriosActivos', fn ($rooms) => $rooms->pluck('numero')->all() === [self::ROOM])
            ->assertViewHas('reservas', fn ($rows) => $rows->pluck('id')->all() === [$visible->id])
            ->assertViewHas('ocupacionPorCubiculo', fn ($rooms) => $rooms->get(self::ROOM)->modelKeys() === [$visible->id]);

        $outsider = User::factory()->create()->assignRole('alumno');
        $this->actingAs($outsider)->getJson(route('consultorios.availability', ['fecha' => self::DATE, 'consultorio_numero' => self::ROOM]))
            ->assertOk()->assertJsonCount(0, 'reservas');
    }

    #[DataProvider('protectedReservations')]
    public function test_direct_and_bulk_delete_protect_the_entire_selection(string $protection, bool $bulk): void
    {
        $unblocked = $this->reservation('07:00', '08:00');
        $protected = $this->reservation('08:00', '09:00');

        if ($protection === 'archived') {
            $case = Expediente::factory()->create(['deleted_at' => now()]);
            $protected->update(['expediente_id' => $case->id]);
        } else {
            $proposal = $this->requestFor($protected, $protection);
        }

        $this->actingAs($this->admin);
        $response = $bulk
            ? $this->deleteJson(route('consultorios.bulk-destroy'), ['reservas' => [$unblocked->id, $protected->id]])
            : $this->deleteJson(route('consultorios.destroy', $protected));
        $response->assertUnprocessable()->assertJsonValidationErrors('reserva');
        $this->assertDatabaseCount('consultorio_reservas', 2);
        $this->assertDatabaseHas('consultorio_reservas', ['id' => $protected->id, 'consultorio_numero' => self::ROOM]);
        $this->assertDatabaseHas('consultorio_reservas', ['id' => $unblocked->id]);
        if ($protection !== 'archived') {
            $this->assertDatabaseHas('consultorio_reserva_solicitudes', ['id' => $proposal->id, 'status' => 'pendiente', 'consultorio_reserva_id' => $protected->id]);
        } else {
            $this->assertSoftDeleted($case);
        }
    }

    public static function protectedReservations(): array
    {
        return [
            'direct delete during pending edit' => ['edicion', false],
            'direct delete during pending removal' => ['baja', false],
            'direct delete of an archived case reservation' => ['archived', false],
            'mixed bulk delete during pending edit' => ['edicion', true],
            'mixed bulk delete during pending removal' => ['baja', true],
            'mixed bulk delete including an archived case' => ['archived', true],
        ];
    }

    private function reservation(string $start, string $end, ?User $assignee = null): ConsultorioReserva
    {
        return ConsultorioReserva::query()->create([
            'fecha' => self::DATE,
            'hora_inicio' => $start,
            'hora_fin' => $end,
            'consultorio_numero' => self::ROOM,
            'cubiculo_numero' => self::ROOM,
            'estrategia' => 'Grupo de prueba',
            'usuario_atendido_id' => $assignee?->id,
            'creado_por' => $this->admin->id,
        ]);
    }

    private function requestFor(ConsultorioReserva $reservation, string $type, array $payload = [], string $status = 'pendiente'): ConsultorioReservaSolicitud
    {
        return $reservation->solicitudes()->create([
            'tipo' => $type,
            'status' => $status,
            'payload' => $payload,
            'requested_by' => $this->paps->id,
        ]);
    }
}
