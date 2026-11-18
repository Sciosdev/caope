<?php

namespace Tests\Feature\Consultorios;

use App\Models\CatalogoConsultorio;
use App\Models\CatalogoEstrategia;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use App\Services\ConsultorioSerieService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConsultorioSerieOperationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Expediente $case;

    private string $uuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-18 10:00:00', 'America/Mexico_City'));
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($this->admin);
        foreach ([11, 12, 13] as $number) {
            CatalogoConsultorio::query()->create(['numero' => $number, 'nombre' => 'Espacio '.$number, 'activo' => $number !== 13]);
        }
        CatalogoConsultorio::flushCache();
        CatalogoEstrategia::query()->create(['nombre' => 'Estrategia sintética', 'activo' => true]);
        $this->case = Expediente::factory()->create(['creado_por' => $this->admin->id, 'cubiculo_id' => CatalogoConsultorio::where('numero', 11)->value('id')]);
        $this->uuid = (string) Str::uuid();
    }

    private function occurrence(string $date, array $extra = [], bool $single = false): ConsultorioReserva
    {
        return ConsultorioReserva::createOccurrence($extra + [
            'fecha' => $date, 'hora_inicio' => '11:00', 'hora_fin' => '12:00', 'consultorio_numero' => 11,
            'cubiculo_numero' => 11, 'estrategia' => 'Estrategia sintética', 'expediente_id' => $this->case->id,
            'creado_por' => $this->admin->id,
        ], $single ? null : $this->uuid);
    }

    private function form(ConsultorioReserva $base, array $extra = []): array
    {
        return $extra + [
            'accion' => 'reprogramar', 'alcance' => 'siguientes', 'fecha' => '2026-11-25', 'hora_inicio' => '11:00',
            'hora_fin' => '12:00', 'consultorio_numero' => 11, 'motivo' => 'Ajuste de prueba', 'confirmar' => '1',
            'revision' => app(ConsultorioSerieService::class)->preview($base)['revision'],
        ];
    }

    public function test_moves_suffix_in_place_even_across_its_own_old_slots_but_never_previous_or_unrelated_records(): void
    {
        $past = $this->occurrence('2026-11-16');
        $base = $this->occurrence('2026-11-18');
        $second = $this->occurrence('2026-11-23');
        $third = $this->occurrence('2026-11-25');
        $unrelated = $this->occurrence('2026-11-24', single: true);
        $preview = $this->get(route('consultorios.serie.edit', $base))->assertOk();
        $this->assertSame([$base->id, $second->id, $third->id], $preview->viewData('ocurrencias')->modelKeys());
        $this->post(route('consultorios.serie.apply', $base), $this->form($base, ['revision' => $preview->viewData('revision')]))
            ->assertSessionHasNoErrors()->assertRedirect(route('consultorios.index'));

        foreach ([[$past, '2026-11-16'], [$base, '2026-11-25'], [$second, '2026-11-30'], [$third, '2026-12-02'], [$unrelated, '2026-11-24']] as [$row, $date]) {
            $this->assertSame($date, $row->fresh()->fecha->toDateString());
            $this->assertSame($this->case->id, $row->fresh()->expediente_id);
        }
        $op = DB::table('consultorio_serie_operaciones')->sole();
        $this->assertSame($this->admin->id, $op->actor_id);
        $this->assertSame($base->id, $op->reserva_origen_id);
        $this->assertSame($this->uuid, $op->serie_uuid);
        $this->assertSame('Ajuste de prueba', $op->motivo);
        $changes = json_decode($op->cambios, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([$base->id, $second->id, $third->id], array_column($changes, 'reserva_id'));
        $this->assertSame('2026-11-18', $changes[0]['antes']['fecha']);
        $this->assertSame('2026-11-25', $changes[0]['despues']['fecha']);
        $this->assertDatabaseCount('consultorio_reservas', 5);
    }

    public function test_cancels_only_selected_suffix_preserving_sessions_old_dates_other_series_and_prior_decisions(): void
    {
        $past = $this->occurrence('2026-11-10');
        $earlierFuture = $this->occurrence('2026-11-19', ['consultorio_numero' => 12, 'cubiculo_numero' => 12]);
        $base = $this->occurrence('2026-11-20');
        $last = $this->occurrence('2026-11-24');
        $this->uuid = (string) Str::uuid();
        $unrelated = $this->occurrence('2026-11-20', ['hora_inicio' => '12:00', 'hora_fin' => '13:00']);
        $clinical = Sesion::factory()->create(['expediente_id' => $this->case->id]);
        $decision = ConsultorioReservaSolicitud::create(['consultorio_reserva_id' => $last->id, 'requested_by' => $this->admin->id, 'status' => 'atendida', 'tipo' => 'edicion', 'payload' => ['fecha' => '2026-11-24']]);
        $this->post(route('consultorios.serie.apply', $base), $this->form($base, ['accion' => 'cancelar']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEqualsCanonicalizing([$past->id, $earlierFuture->id, $unrelated->id], ConsultorioReserva::pluck('id')->all());
        $this->assertEquals($clinical->getAttributes(), $clinical->fresh()->getAttributes());
        $this->assertNull($decision->fresh()->consultorio_reserva_id);
        $this->assertEquals(['fecha' => '2026-11-24'], $decision->fresh()->payload);
        $this->assertSame(CatalogoConsultorio::where('numero', 12)->value('id'), $this->case->fresh()->cubiculo_id);
        $changes = json_decode(DB::table('consultorio_serie_operaciones')->sole()->cambios, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([$base->id, $last->id], array_column($changes, 'reserva_id'));
        $this->assertSame([null, null], array_column($changes, 'despues'));
    }

    public function test_individual_scope_keeps_other_series_dates_and_standalone_legacy_never_auto_groups(): void
    {
        $base = $this->occurrence('2026-11-18');
        $other = $this->occurrence('2026-11-25');
        $single = $this->occurrence('2026-11-26', single: true);
        $this->post(route('consultorios.serie.apply', $base), $this->form($base, ['alcance' => 'esta', 'fecha' => '2026-11-19', 'consultorio_numero' => 12]))
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-11-19', $base->fresh()->fecha->toDateString());
        $this->assertSame(12, $base->fresh()->consultorio_numero);
        $this->assertSame(12, $base->fresh()->cubiculo_numero);
        $this->assertSame(CatalogoConsultorio::where('numero', 12)->value('id'), $this->case->fresh()->cubiculo_id);
        $this->assertSame('2026-11-25', $other->fresh()->fecha->toDateString());
        $this->postJson(route('consultorios.serie.apply', $single), $this->form($single, ['accion' => 'cancelar']))->assertUnprocessable()->assertJsonValidationErrors('alcance');
        $this->assertDatabaseCount('consultorio_reservas', 3);
        $this->post(route('consultorios.serie.apply', $single), $this->form($single, ['accion' => 'cancelar', 'alcance' => 'esta']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('consultorio_reservas', 2);
        $this->assertDatabaseCount('consultorio_serie_operaciones', 2);
    }

    public static function failures(): array
    {
        return [
            ['empalme', 'hora_inicio'], ['dentro de serie', 'hora_inicio'], ['pendiente', 'alcance'],
            ['vigencia', 'revision'], ['archivado', 'alcance'], ['domingo posterior', 'fecha'],
            ['destino pasado', 'fecha'], ['cubiculo inactivo', 'consultorio_numero'], ['horario', 'hora_inicio'],
            ['sin confirmar', 'confirmar'], ['sin motivo', 'motivo'], ['revinculada', 'alcance'],
        ];
    }

    #[DataProvider('failures')]
    public function test_rejected_changes_leave_every_date_and_the_audit_intact(string $condition, string $errorKey): void
    {
        $base = $this->occurrence('2026-11-18');
        $next = $this->occurrence('2026-11-21');
        $form = $this->form($base);
        match ($condition) {
            'empalme' => $this->occurrence('2026-11-28', single: true),
            'dentro de serie' => $this->occurrence('2026-11-21', ['hora_inicio' => '13:00', 'hora_fin' => '14:00']),
            'pendiente' => ConsultorioReservaSolicitud::create(['consultorio_reserva_id' => $next->id, 'requested_by' => $this->admin->id, 'tipo' => 'baja', 'status' => 'pendiente']),
            'vigencia' => $next->update(['hora_fin' => '12:30']),
            'archivado' => $this->case->delete(),
            'domingo posterior' => $form['fecha'] = '2026-11-19',
            'destino pasado' => $form['fecha'] = '2026-11-16',
            'cubiculo inactivo' => $form['consultorio_numero'] = 13,
            'horario' => $form['hora_inicio'] = '06:59',
            'sin confirmar' => $form['confirmar'] = '0',
            'sin motivo' => $form['motivo'] = ' ',
            'revinculada' => $next->update(['expediente_id' => null]),
        };
        if (in_array($condition, ['revinculada', 'dentro de serie'], true)) {
            $form['revision'] = $this->form($base)['revision'];
        }
        $this->postJson(route('consultorios.serie.apply', $base), $form)->assertUnprocessable()->assertJsonValidationErrors($errorKey);
        $this->assertSame('2026-11-18', $base->fresh()->fecha->toDateString());
        $this->assertSame('2026-11-21', $next->fresh()->fecha->toDateString());
        $this->assertDatabaseCount('consultorio_serie_operaciones', 0);
    }

    public function test_cannot_cancel_an_occurrence_that_started_at_the_current_city_time_even_with_old_open_form(): void
    {
        $base = $this->occurrence('2026-11-18');
        $form = $this->form($base, ['accion' => 'cancelar']);
        $this->travelTo(Carbon::parse('2026-11-18 17:00:00', 'UTC')); // 11:00 local: already started.
        $this->get(route('consultorios.serie.edit', $base))->assertUnprocessable();
        $this->postJson(route('consultorios.serie.apply', $base), $form)->assertUnprocessable()->assertJsonValidationErrors('alcance');
        $this->assertDatabaseHas('consultorio_reservas', ['id' => $base->id]);
        $this->assertDatabaseCount('consultorio_serie_operaciones', 0);
    }

    public function test_non_admin_cannot_preview_or_apply_even_with_a_valid_confirmation(): void
    {
        $base = $this->occurrence('2026-11-18');
        $paps = User::factory()->create(['approved_at' => now()])->assignRole('paps');
        $this->actingAs($paps)->get(route('consultorios.serie.edit', $base))->assertForbidden();
        $this->postJson(route('consultorios.serie.apply', $base), $this->form($base, ['accion' => 'cancelar']))->assertForbidden();
        $this->assertDatabaseHas('consultorio_reservas', ['id' => $base->id]);
        $this->assertDatabaseCount('consultorio_serie_operaciones', 0);
    }

    public function test_noop_and_replayed_confirmations_do_not_create_additional_operations(): void
    {
        $base = $this->occurrence('2026-11-18');
        $next = $this->occurrence('2026-11-20');
        $form = $this->form($base, ['fecha' => '2026-11-18']);
        $this->postJson(route('consultorios.serie.apply', $base), $form)->assertUnprocessable()->assertJsonValidationErrors('reserva');
        $this->assertDatabaseCount('consultorio_serie_operaciones', 0);
        $form['consultorio_numero'] = 12;
        $this->post(route('consultorios.serie.apply', $base), $form)->assertSessionHasNoErrors();
        $this->postJson(route('consultorios.serie.apply', $base), $form)->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->assertDatabaseCount('consultorio_serie_operaciones', 1);
        $this->assertSame(12, $next->fresh()->consultorio_numero);
    }

    public function test_unmigrated_audit_table_blocks_both_the_form_and_operation(): void
    {
        $base = $this->occurrence('2026-11-18');
        $form = $this->form($base, ['accion' => 'cancelar']);
        Schema::rename('consultorio_serie_operaciones', 'audit_unavailable');
        try {
            $this->get(route('consultorios.serie.edit', $base))->assertRedirect(route('consultorios.index'))->assertSessionHasErrors('reserva');
            $this->postJson(route('consultorios.serie.apply', $base), $form)->assertUnprocessable()->assertJsonValidationErrors('reserva');
            $this->assertDatabaseHas('consultorio_reservas', ['id' => $base->id]);
        } finally {
            Schema::rename('audit_unavailable', 'consultorio_serie_operaciones');
        }
    }

    public function test_archived_or_pending_base_redirects_back_instead_of_opening_a_form_which_cannot_be_saved(): void
    {
        $base = $this->occurrence('2026-11-18');
        $proposal = ConsultorioReservaSolicitud::create(['consultorio_reserva_id' => $base->id, 'requested_by' => $this->admin->id, 'tipo' => 'edicion', 'status' => 'pendiente']);
        $this->get(route('consultorios.serie.edit', $base))->assertRedirect(route('consultorios.index'))->assertSessionHasErrors('reserva');
        $proposal->update(['status' => 'atendida']);
        $this->case->delete();
        $this->get(route('consultorios.serie.edit', $base))->assertRedirect(route('consultorios.index'))->assertSessionHasErrors('reserva');
        $this->assertDatabaseCount('consultorio_serie_operaciones', 0);
    }
}
