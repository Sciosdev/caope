<?php

namespace Tests\Feature\Consultorios;

use App\Exports\ConsultorioReservasExport;
use App\Models\CatalogoConsultorio;
use App\Models\CatalogoCubiculo;
use App\Models\CatalogoEstrategia;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConsultorioVisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'paps', 'coordinador', 'alumno', 'docente', 'estratega'] as $role) {
            Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        foreach ([5, 6] as $numero) {
            CatalogoConsultorio::query()->firstOrCreate(
                ['numero' => $numero],
                ['nombre' => "Consultorio {$numero}", 'activo' => true]
            );
        }

        foreach ([2, 3] as $numero) {
            CatalogoCubiculo::query()->firstOrCreate(
                ['numero' => $numero],
                ['nombre' => "Cubículo {$numero}", 'activo' => true]
            );
        }

        CatalogoEstrategia::query()->firstOrCreate([
            'nombre' => 'Reserva asignada',
            'activo' => true,
        ]);
    }

    public function test_coordinador_solo_recibe_catalogos_y_reservas_asignadas(): void
    {
        [$admin, $coordinador] = [$this->user('admin'), $this->user('coordinador')];
        $visible = $this->reservation(5, 2, $admin, ['supervisor_id' => $coordinador->id]);
        $this->reservation(6, 3, $admin, ['estrategia' => 'Reserva ajena']);

        $this->actingAs($coordinador)
            ->get(route('consultorios.index', ['consultorio_numero' => 6, 'cubiculo_numero' => 3]))
            ->assertOk()
            ->assertViewHas('consultoriosActivos', fn ($items) => $items->pluck('numero')->all() === [5])
            ->assertViewHas('cubiculosActivos', fn ($items) => $items->pluck('numero')->all() === [2])
            ->assertViewHas('consultorioSeleccionado', 5)
            ->assertViewHas('reservas', fn ($items) => $items->pluck('id')->all() === [$visible->id]);
    }

    public function test_usuario_restringido_sin_asignacion_no_recibe_catalogos_ni_fallback(): void
    {
        $coordinador = $this->user('coordinador');

        $this->actingAs($coordinador)
            ->get(route('consultorios.index', ['consultorio_numero' => 5]))
            ->assertOk()
            ->assertViewHas('consultoriosActivos', fn ($items) => $items->isEmpty())
            ->assertViewHas('cubiculosActivos', fn ($items) => $items->isEmpty())
            ->assertViewHas('consultorioSeleccionado', null)
            ->assertViewHas('reservas', fn ($items) => $items->isEmpty());
    }

    public function test_creator_without_an_operational_assignment_cannot_see_the_reservation(): void
    {
        $facilitador = $this->user('alumno');
        $reserva = $this->reservation(5, 2, $facilitador);

        $this->assertFalse(
            ConsultorioReserva::query()->visibleTo($facilitador)->whereKey($reserva)->exists()
        );
    }

    public function test_creator_loses_visibility_after_the_reservation_is_reassigned(): void
    {
        $facilitador = $this->user('alumno');
        $otroFacilitador = $this->user('alumno');
        $reserva = $this->reservation(5, 2, $facilitador, [
            'usuario_atendido_id' => $facilitador->id,
        ]);

        $this->assertTrue(
            ConsultorioReserva::query()->visibleTo($facilitador)->whereKey($reserva)->exists()
        );

        $reserva->update(['usuario_atendido_id' => $otroFacilitador->id]);

        $this->assertFalse(
            ConsultorioReserva::query()->visibleTo($facilitador)->whereKey($reserva)->exists()
        );
        $this->assertTrue(
            ConsultorioReserva::query()->visibleTo($otroFacilitador)->whereKey($reserva)->exists()
        );
    }

    public function test_docente_solo_ve_donde_es_estratega(): void
    {
        [$admin, $docente] = [$this->user('admin'), $this->user('docente')];
        $this->reservation(5, 2, $admin, ['estratega_id' => $docente->id]);
        $this->reservation(6, 3, $admin, ['estrategia' => 'Reserva ajena']);

        $this->actingAs($docente)
            ->getJson(route('consultorios.availability', ['fecha' => now()->toDateString()]))
            ->assertOk()
            ->assertJsonCount(1, 'reservas')
            ->assertJsonPath('reservas.0.estratega_id', $docente->id);
    }

    public function test_export_directo_respeta_el_alcance_del_coordinador(): void
    {
        [$admin, $coordinador] = [$this->user('admin'), $this->user('coordinador')];
        $visible = $this->reservation(5, 2, $admin, ['supervisor_id' => $coordinador->id]);
        $this->reservation(6, 3, $admin, ['estrategia' => 'Reserva ajena']);

        Excel::fake();
        Excel::matchByRegex();

        $this->actingAs($coordinador)->get(route('consultorios.export'))->assertOk();

        Excel::assertDownloaded('/^bitacora_reservas_\d{8}_\d{6}\.xlsx$/', function (ConsultorioReservasExport $export) use ($visible): bool {
            return $export->query()->pluck('id')->all() === [$visible->id]
                && $export->headings()[4] === 'Cubículo';
        });
    }

    public function test_tabla_usa_usuario_atendido_como_facilitador(): void
    {
        $admin = $this->user('admin');
        $creador = User::factory()->create(['name' => 'Creador distinto']);
        $facilitador = User::factory()->create(['name' => 'Facilitador correcto']);
        $this->reservation(5, 2, $creador, ['usuario_atendido_id' => $facilitador->id]);

        $this->actingAs($admin)
            ->get(route('consultorios.index'))
            ->assertOk()
            ->assertSee('<td>Facilitador correcto</td>', false)
            ->assertDontSee('<td>Creador distinto</td>', false);
    }

    public function test_admin_y_paps_aprobado_conservan_visibilidad_global(): void
    {
        $admin = $this->user('admin');
        $paps = $this->user('paps', ['approved_at' => now()]);
        $this->reservation(5, 2, $admin);
        $this->reservation(6, 3, $admin);

        $this->assertCount(2, ConsultorioReserva::query()->visibleTo($admin)->get());
        $this->assertCount(2, ConsultorioReserva::query()->visibleTo($paps)->get());
    }

    public function test_paps_no_aprobado_con_rol_operativo_no_puede_crear_reservas(): void
    {
        $actor = $this->user('alumno', ['approved_at' => null]);
        $actor->assignRole('paps');

        $this->actingAs($actor)
            ->post(route('consultorios.store'), [
                'fecha' => now()->addDay()->toDateString(),
                'hora_inicio' => '08:00',
                'hora_fin' => '09:00',
                'consultorio_numero' => 5,
                'cubiculo_numero' => 2,
                'estrategia' => 'Reserva asignada',
                'origen_expediente' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('consultorio_reservas', 0);
    }

    public function test_admin_approval_applies_the_requested_reservation_changes(): void
    {
        $admin = $this->user('admin');
        $paps = $this->user('paps', ['approved_at' => now()]);
        $reserva = $this->reservation(5, 2, $admin);

        $solicitud = ConsultorioReservaSolicitud::query()->create([
            'consultorio_reserva_id' => $reserva->id,
            'requested_by' => $paps->id,
            'tipo' => 'edicion',
            'payload' => [
                'fecha' => '2026-08-14',
                'hora_inicio' => '10:00',
                'hora_fin' => '11:00',
                'consultorio_numero' => 6,
                'cubiculo_numero' => 3,
                'estrategia' => 'Reserva asignada',
                'usuario_atendido_id' => $paps->id,
                'estratega_id' => null,
                'supervisor_id' => null,
            ],
            'status' => 'pendiente',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.consultorios.solicitudes.approve', $solicitud))
            ->assertRedirect(route('admin.consultorios.solicitudes.index'));

        $reserva->refresh();
        $this->assertSame('2026-08-14', $reserva->fecha->toDateString());
        $this->assertSame(6, $reserva->consultorio_numero);
        $this->assertSame(3, $reserva->cubiculo_numero);
        $this->assertSame('10:00', $reserva->hora_inicio);
        $this->assertSame('11:00', $reserva->hora_fin);
        $this->assertSame('atendida', $solicitud->fresh()->status);
    }

    public function test_admin_approval_preserves_the_deletion_request_audit_row(): void
    {
        $admin = $this->user('admin');
        $paps = $this->user('paps', ['approved_at' => now()]);
        $reserva = $this->reservation(5, 2, $admin);

        $solicitud = ConsultorioReservaSolicitud::query()->create([
            'consultorio_reserva_id' => $reserva->id,
            'requested_by' => $paps->id,
            'tipo' => 'baja',
            'status' => 'pendiente',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.consultorios.solicitudes.approve', $solicitud))
            ->assertRedirect(route('admin.consultorios.solicitudes.index'));

        $this->assertDatabaseMissing('consultorio_reservas', ['id' => $reserva->id]);
        $this->assertDatabaseHas('consultorio_reserva_solicitudes', [
            'id' => $solicitud->id,
            'consultorio_reserva_id' => null,
            'requested_by' => $paps->id,
            'tipo' => 'baja',
            'status' => 'atendida',
        ]);
        $this->assertNull($solicitud->fresh()->reserva);
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function reservation(int $consultorio, int $cubiculo, User $creator, array $attributes = []): ConsultorioReserva
    {
        return ConsultorioReserva::query()->create($attributes + [
            'fecha' => now()->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fin' => '09:00',
            'consultorio_numero' => $consultorio,
            'cubiculo_numero' => $cubiculo,
            'estrategia' => 'Reserva asignada',
            'creado_por' => $creator->id,
        ]);
    }
}
