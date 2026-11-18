<?php

namespace Tests\Feature\Consultorios;

use App\Models\CatalogoConsultorio;
use App\Models\ConsultorioReserva;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsultorioSerieUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-19T01:00:00Z')); // Wed 18, 19:00 in Mexico City.
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($this->admin);
        CatalogoConsultorio::query()->create(['numero' => 47, 'nombre' => 'Sala de prueba', 'activo' => true]);
    }

    public function test_admin_previews_only_explicit_series_from_selected_slot_and_never_restores_confirmation(): void
    {
        $series = (string) Str::uuid();
        $base = $this->reserve('2026-11-19', $series);
        $next = $this->reserve('2026-11-26', $series);
        $prior = $this->reserve('2026-11-12', $series);
        $unrelated = $this->reserve('2026-12-03', null);
        $this->withSession(['_old_input' => ['revision' => 'stale-token', 'confirmar' => '1']])
            ->get(route('consultorios.serie.edit', $base))->assertOk()
            ->assertSee('action="'.route('consultorios.serie.apply', $base).'"', false)
            ->assertSee('name="revision"', false)->assertDontSee('stale-token')
            ->assertSee('name="confirmar" value="1" required', false)->assertDontSee('name="confirmar" value="1" checked', false)
            ->assertSee('name="alcance" value="esta" checked', false)
            ->assertSee('name="alcance" value="siguientes"', false)
            ->assertSee('data-slot="'.$base->id.'"', false)->assertSee('data-slot="'.$next->id.'"', false)
            ->assertDontSee('data-slot="'.$prior->id.'"', false)->assertDontSee('data-slot="'.$unrelated->id.'"', false)
            ->assertSeeText('No elimina el expediente, sus sesiones clínicas ni sus documentos.');
        $this->get(route('consultorios.serie.edit', $unrelated))->assertOk()
            ->assertDontSee('name="alcance" value="siguientes"', false)
            ->assertSeeText('Solo se puede gestionar de forma individual.');
    }

    public function test_bitacora_only_offers_management_for_admin_on_mutable_future_occurrences_and_no_legacy_bulk_shortcut(): void
    {
        $series = (string) Str::uuid();
        $started = $this->reserve('2026-11-18', $series, '19:00');
        $future = $this->reserve('2026-11-18', $series, '20:00');
        $pending = $this->reserve('2026-11-19', $series);
        $archived = Expediente::factory()->create(['creado_por' => $this->admin->id]);
        $frozen = $this->reserve('2026-11-20', null, caseId: $archived->id);
        $archived->delete();
        $pending->solicitudes()->create(['requested_by' => $this->admin->id, 'tipo' => 'edicion', 'status' => 'pendiente']);
        $this->get(route('consultorios.index'))->assertOk()
            ->assertSee(route('consultorios.serie.edit', $future), false)
            ->assertSee(route('consultorios.edit', $future), false)
            ->assertDontSee(route('consultorios.serie.edit', $started), false)
            ->assertDontSee(route('consultorios.serie.edit', $frozen), false)
            ->assertDontSee(route('consultorios.serie.edit', $pending), false)
            ->assertSeeText('Gestionar solicitudes')->assertDontSee('bitacora-bulk-delete-form', false);
        $this->get(route('consultorios.edit', $future))->assertOk()
            ->assertSee('type="hidden" name="consultorio_numero"', false)->assertSeeText('esta reserva quedará separada de la serie');
        $paps = User::factory()->create(['approved_at' => now()])->assignRole('paps');
        $this->actingAs($paps)->get(route('consultorios.index'))->assertOk()
            ->assertSeeText('Solicitar edición')->assertDontSee(route('consultorios.serie.edit', $future), false);
        $this->get(route('consultorios.serie.edit', $future))->assertForbidden();
    }

    private function reserve(string $date, ?string $series, string $time = '09:00', ?int $caseId = null): ConsultorioReserva
    {
        $row = new ConsultorioReserva([
            'fecha' => $date, 'hora_inicio' => $time, 'hora_fin' => substr($time, 0, 2).':55',
            'consultorio_numero' => 47, 'cubiculo_numero' => 47, 'estrategia' => 'Prueba',
            'creado_por' => $this->admin->id, 'expediente_id' => $caseId,
        ]);
        $row->serie_uuid = $series;
        $row->save();

        return $row;
    }
}
