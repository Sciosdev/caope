<?php

namespace Tests\Feature\Consultorios;

use App\Exports\ConsultorioReservasExport;
use App\Models\CatalogoCarrera;
use App\Models\CatalogoConsultorio;
use App\Models\CatalogoCubiculo;
use App\Models\CatalogoEstrategia;
use App\Models\CatalogoTurno;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\CatalogoConsultorioSeeder;
use Database\Seeders\RoleSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CubiculoUnificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CatalogoEstrategia $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
        $this->admin = $this->user('admin');
        $this->actingAs($this->admin);
        $this->strategy = CatalogoEstrategia::query()->create(['nombre' => 'Estrategia sintética', 'activo' => true]);
        CatalogoCarrera::query()->firstOrCreate(['nombre' => 'Carrera sintética'], ['activo' => true]);
        CatalogoTurno::query()->firstOrCreate(['nombre' => 'Matutino'], ['activo' => true]);

        for ($number = 1; $number <= 18; $number++) {
            $room = new CatalogoConsultorio;
            $room->forceFill([
                'id' => 100 + $number,
                'numero' => $number,
                'nombre' => $number === 18 ? 'Espacio Azul de prueba' : 'Espacio físico sintético '.$number,
                'activo' => true,
            ])->save();
        }
        foreach ([CatalogoConsultorio::class, CatalogoCubiculo::class, CatalogoCarrera::class, CatalogoTurno::class, CatalogoEstrategia::class] as $catalogo) {
            $catalogo::flushCache();
        }
    }

    public function test_both_creation_forms_use_all_eighteen_canonical_names_without_a_second_cubicle_catalog(): void
    {
        $caseForm = $this->get(route('expedientes.create'))->assertOk();
        $caseOptions = $this->selectOptions($caseForm, '//select[@name="cubiculo_id"]');
        $bookingForm = $this->get(route('consultorios.index'))->assertOk();
        $bookingOptions = $this->selectOptions($bookingForm, '//select[@name="consultorio_numero"]');

        foreach (CatalogoConsultorio::query()->orderBy('numero')->get() as $room) {
            $this->assertSame($room->nombre, $caseOptions[(string) $room->id] ?? null);
            $this->assertSame($room->nombre, $bookingOptions[(string) $room->numero] ?? null);
        }
        $this->assertCount(18, array_filter($caseOptions, fn ($label, $value) => $value !== '', ARRAY_FILTER_USE_BOTH));
        $this->assertCount(18, array_filter($bookingOptions, fn ($label, $value) => $value !== '', ARRAY_FILTER_USE_BOTH));
        $this->assertSame(0, $this->xpath($caseForm)->query('//select[@name="resumen_clinico[cubiculo]"]')->length);
        $this->assertSame(0, $this->xpath($bookingForm)->query('//select[@name="cubiculo_numero"]')->length);
        $this->get(route('admin.catalogos.consultorios.index'))->assertOk()->assertSee('Cubículos');
    }

    public function test_legacy_catalog_gets_redirect_without_reinterpreting_a_colliding_record_id(): void
    {
        $room = $this->room(1);
        $this->legacyCollision($room);

        $this->get(route('admin.catalogos.cubiculos.index'))->assertRedirect(route('admin.catalogos.consultorios.index'));
        $this->get(route('admin.catalogos.cubiculos.create'))->assertRedirect(route('admin.catalogos.consultorios.create'));
        $this->get(route('admin.catalogos.cubiculos.edit', $room->id))->assertRedirect(route('admin.catalogos.consultorios.index'));
        $this->assertDatabaseHas('catalogo_consultorios', ['id' => $room->id, 'numero' => 1, 'nombre' => $room->nombre]);
        $this->assertDatabaseHas('catalogo_cubiculos', ['id' => $room->id, 'numero' => 50, 'nombre' => 'Registro histórico independiente']);
    }

    #[DataProvider('legacyMutations')]
    public function test_stale_legacy_catalog_writes_return_409_without_changing_either_catalog(string $method, string $routeName, bool $needsId): void
    {
        $room = $this->room(1);
        $this->legacyCollision($room);
        $canonicalBefore = DB::table('catalogo_consultorios')->orderBy('id')->get()->toArray();
        $legacyBefore = DB::table('catalogo_cubiculos')->orderBy('id')->get()->toArray();

        $this->json($method, route($routeName, $needsId ? $room->id : []), [
            'nombre' => 'No debe cambiarse',
            'numero' => 99,
            'activo' => false,
        ])->assertStatus(409);

        $this->assertEquals($canonicalBefore, DB::table('catalogo_consultorios')->orderBy('id')->get()->toArray());
        $this->assertEquals($legacyBefore, DB::table('catalogo_cubiculos')->orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('consultorio_reservas', 0);
    }

    #[DataProvider('deprecatedCubicleValues')]
    public function test_new_reservation_uses_the_canonical_number_and_ignores_deprecated_cubicle_input(mixed $deprecated): void
    {
        $payload = $this->reservationPayload(18);
        if ($deprecated !== null) {
            $payload['cubiculo_numero'] = $deprecated;
        }

        $this->postJson(route('consultorios.store'), $payload)->assertOk();

        $booking = ConsultorioReserva::query()->sole();
        $this->assertSame(18, $booking->consultorio_numero);
        $this->assertSame(18, $booking->cubiculo_numero);
        $this->assertDatabaseMissing('catalogo_cubiculos', ['numero' => 18]);
    }

    public function test_explicit_reservation_edit_normalizes_the_selected_canonical_number_only_when_written(): void
    {
        $booking = $this->reservation(1, 7);

        $this->putJson(route('consultorios.update', $booking), $this->reservationPayload(18) + ['cubiculo_numero' => 999])
            ->assertSessionHasNoErrors()->assertRedirect(route('consultorios.index'));

        $this->assertSame(18, $booking->fresh()->consultorio_numero);
        $this->assertSame(18, $booking->fresh()->cubiculo_numero);
    }

    public function test_reading_unified_catalogs_does_not_rewrite_legacy_reservations_or_archive_rows(): void
    {
        $booking = $this->reservation(1, 7);
        $before = $booking->fresh()->getAttributes();
        $legacyBefore = DB::table('catalogo_cubiculos')->orderBy('id')->get()->toArray();
        $legacyCase = Expediente::factory()->create([
            'creado_por' => $this->admin->id,
            'cubiculo_id' => null,
            'resumen_clinico' => array_merge(Expediente::defaultClinicalSummary(), ['cubiculo' => 7]),
        ]);
        $legacySummary = $legacyCase->resumen_clinico;

        $this->get(route('consultorios.index'))->assertOk();
        $this->get(route('expedientes.create'))->assertOk();
        $this->get(route('admin.catalogos.consultorios.index'))->assertOk();
        $this->get(route('expedientes.edit', $legacyCase))->assertOk();
        $this->get(route('expedientes.show', $legacyCase))->assertOk();

        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertEquals($legacyBefore, DB::table('catalogo_cubiculos')->orderBy('id')->get()->toArray());
        $this->assertNull($legacyCase->fresh()->cubiculo_id);
        $this->assertSame($legacySummary, $legacyCase->fresh()->resumen_clinico);
    }

    public function test_availability_names_the_canonical_space_without_reinterpreting_a_historical_cubicle_number(): void
    {
        $room = $this->room(18);
        $this->reservation(18, 7);

        $this->getJson(route('consultorios.availability', ['fecha' => '2026-11-09', 'consultorio_numero' => 18]))
            ->assertOk()->assertJsonCount(1, 'reservas')
            ->assertJsonPath('reservas.0.consultorio_numero', 18)
            ->assertJsonPath('reservas.0.cubiculo_nombre', $room->nombre)
            ->assertJsonPath('reservas.0.cubiculo_numero', 7);
    }

    public function test_export_has_one_canonical_cubicle_column_with_its_name_and_stable_number(): void
    {
        $room = $this->room(18);
        $booking = $this->reservation(18, 7);
        $export = new ConsultorioReservasExport($this->admin->id);
        $headers = $export->headings();
        $mapped = $export->map($export->query()->sole());

        $this->assertSame(1, count(array_filter($headers, fn ($header) => $header === 'Cubículo')));
        $this->assertNotContains('Consultorio', $headers);
        $this->assertSame(count($headers), count($mapped));
        $this->assertSame($room->nombre.' · Núm. 18', $mapped[array_search('Cubículo', $headers, true)]);
        $this->assertSame(7, $booking->fresh()->cubiculo_numero);
    }

    public function test_editing_an_inactive_current_space_does_not_silently_select_the_first_active_option(): void
    {
        $room = $this->room(18);
        $booking = $this->reservation(18, 7);
        $room->update(['activo' => false]);
        CatalogoConsultorio::flushCache();
        $before = $booking->fresh()->getAttributes();

        $response = $this->get(route('consultorios.edit', $booking))->assertOk();
        $selected = $this->xpath($response)->query('//select[@name="consultorio_numero"]/option[@selected]');

        $this->assertSame(1, $selected->length);
        $this->assertSame('', $selected->item(0)->getAttribute('value'));
        $this->assertTrue($selected->item(0)->hasAttribute('disabled'));
        $this->assertStringContainsString($room->nombre, $selected->item(0)->textContent);
        $this->assertStringContainsString('Núm. 18', $selected->item(0)->textContent);
        $this->assertSame($before, $booking->fresh()->getAttributes());
    }

    public function test_historical_different_cubicle_number_cannot_bypass_the_physical_room_overlap(): void
    {
        $existing = $this->reservation(1, 7);

        $this->postJson(route('consultorios.store'), $this->reservationPayload(1, ['hora_inicio' => '09:30', 'hora_fin' => '10:30']))
            ->assertUnprocessable()->assertJsonValidationErrors('hora_inicio');

        $this->assertSame([$existing->id], ConsultorioReserva::query()->pluck('id')->all());
        $this->assertSame(7, $existing->fresh()->cubiculo_numero);
    }

    #[DataProvider('usageSources')]
    public function test_used_canonical_spaces_cannot_be_deleted_or_renumbered(string $source): void
    {
        $room = $this->room(18);
        $this->createUsage($room, $source);
        $before = $room->fresh()->getAttributes();

        $this->deleteJson(route('admin.catalogos.consultorios.force-destroy', $room))
            ->assertUnprocessable()->assertJsonValidationErrors('catalogo');
        $this->putJson(route('admin.catalogos.consultorios.update', $room), [
            'nombre' => 'Cambio que no debe guardarse',
            'numero' => 99,
            'activo' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('catalogo');

        $this->assertSame($before, $room->fresh()->getAttributes());
        $this->assertDatabaseCount('catalogo_consultorios', 18);
    }

    public function test_used_spaces_can_be_deactivated_without_deleting_or_renumbering_their_bookings(): void
    {
        $room = $this->room(18);
        $booking = $this->reservation(18, 7);

        $this->patch(route('admin.catalogos.consultorios.toggle-active', $room))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.catalogos.consultorios.index'));

        $this->assertFalse($room->fresh()->activo);
        $this->assertSame(18, $booking->fresh()->consultorio_numero);
        $this->assertSame(7, $booking->fresh()->cubiculo_numero);
        $this->postJson(route('consultorios.store'), $this->reservationPayload(18, ['hora_inicio' => '10:00', 'hora_fin' => '11:00']))
            ->assertUnprocessable()->assertJsonValidationErrors('consultorio_numero');
        $this->assertDatabaseCount('consultorio_reservas', 1);
    }

    public function test_an_unused_space_can_still_be_renumbered_and_deleted_without_touching_legacy_records(): void
    {
        $room = $this->room(18);
        $legacyBefore = DB::table('catalogo_cubiculos')->orderBy('id')->get()->toArray();

        $this->put(route('admin.catalogos.consultorios.update', $room), [
            'nombre' => 'Espacio sintético sin uso',
            'numero' => 40,
            'activo' => true,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.catalogos.consultorios.index'));
        $this->assertSame(40, $room->fresh()->numero);
        $this->delete(route('admin.catalogos.consultorios.force-destroy', $room))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.catalogos.consultorios.index'));

        $this->assertDatabaseMissing('catalogo_consultorios', ['id' => $room->id]);
        $this->assertEquals($legacyBefore, DB::table('catalogo_cubiculos')->orderBy('id')->get()->toArray());
    }

    public function test_rerunning_the_default_seeder_preserves_all_custom_names_numbers_ids_and_active_flags(): void
    {
        $this->room(2)->update(['activo' => false]);
        $before = CatalogoConsultorio::query()->orderBy('id')->get()->toArray();

        $this->seed(CatalogoConsultorioSeeder::class);
        $this->seed(CatalogoConsultorioSeeder::class);

        $this->assertSame($before, CatalogoConsultorio::query()->orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('catalogo_consultorios', 18);
    }

    public function test_new_expediente_keeps_the_canonical_id_distinct_from_the_booking_number(): void
    {
        $room = $this->room(18);
        $facilitator = $this->user('alumno');

        $this->post(route('expedientes.store'), [
            'no_control' => sprintf('CA-%s-8108', now()->year),
            'paciente' => 'Paciente sintético en espacio canónico',
            'apertura' => now()->toDateString(),
            'carrera' => 'Carrera sintética',
            'turno' => 'Matutino',
            'facilitador_id' => $facilitator->id,
            'estrategia_id' => $this->strategy->id,
            'cubiculo_id' => $room->id,
            'consultorio_reserva' => $this->reservationPayload(18) + ['cubiculo_numero' => 999],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $case = Expediente::query()->sole();
        $booking = ConsultorioReserva::query()->sole();
        $this->assertSame(118, (int) $case->cubiculo_id);
        $this->assertSame($case->id, (int) $booking->expediente_id);
        $this->assertSame(18, $booking->consultorio_numero);
        $this->assertSame(18, $booking->cubiculo_numero);
    }

    public static function legacyMutations(): array
    {
        return [
            'create' => ['POST', 'admin.catalogos.cubiculos.store', false],
            'update' => ['PUT', 'admin.catalogos.cubiculos.update', true],
            'deactivate' => ['DELETE', 'admin.catalogos.cubiculos.destroy', true],
            'toggle active' => ['PATCH', 'admin.catalogos.cubiculos.toggle-active', true],
            'permanent delete' => ['DELETE', 'admin.catalogos.cubiculos.force-destroy', true],
        ];
    }

    public static function deprecatedCubicleValues(): array
    {
        return [
            'absent' => [null],
            'different legacy number' => [1],
            'nonexistent legacy number' => [999],
            'invalid legacy array' => [['ignored']],
        ];
    }

    public static function usageSources(): array
    {
        return [['reservation'], ['pending proposal'], ['expediente']];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['name' => 'Cuenta sintética '.$role, 'approved_at' => now(), 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function room(int $number): CatalogoConsultorio
    {
        return CatalogoConsultorio::query()->where('numero', $number)->firstOrFail();
    }

    private function reservation(int $number, int $legacyCubicle): ConsultorioReserva
    {
        return ConsultorioReserva::query()->create([
            'fecha' => '2026-11-09',
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => $number,
            'cubiculo_numero' => $legacyCubicle,
            'estrategia' => $this->strategy->nombre,
            'creado_por' => $this->admin->id,
        ]);
    }

    private function reservationPayload(int $number, array $overrides = []): array
    {
        return array_replace([
            'modo_repeticion' => 'unica',
            'fecha' => '2026-11-09',
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => $number,
            'estrategia' => $this->strategy->nombre,
        ], $overrides);
    }

    private function legacyCollision(CatalogoConsultorio $canonical): void
    {
        DB::table('catalogo_cubiculos')->insert([
            'id' => $canonical->id,
            'nombre' => 'Registro histórico independiente',
            'numero' => 50,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUsage(CatalogoConsultorio $room, string $source): void
    {
        if ($source === 'reservation') {
            $this->reservation($room->numero, 7);

            return;
        }
        if ($source === 'pending proposal') {
            $booking = $this->reservation(1, 7);
            ConsultorioReservaSolicitud::query()->create([
                'consultorio_reserva_id' => $booking->id,
                'requested_by' => $this->user('paps')->id,
                'tipo' => 'edicion',
                'payload' => $this->reservationPayload($room->numero),
                'status' => 'pendiente',
            ]);

            return;
        }
        Expediente::factory()->create([
            'cubiculo_id' => $room->id,
            'creado_por' => $this->admin->id,
            'facilitador_id' => null,
            'resumen_clinico' => array_merge(Expediente::defaultClinicalSummary(), ['cubiculo' => 7]),
        ]);
    }

    private function selectOptions(TestResponse $response, string $query): array
    {
        $xpath = $this->xpath($response);
        $select = $xpath->query($query)->item(0);
        $this->assertNotNull($select, 'The canonical space selector must be present.');
        $options = [];
        foreach ($xpath->query('./option', $select) as $option) {
            $options[$option->getAttribute('value')] = trim($option->textContent);
        }

        return $options;
    }

    private function xpath(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }
}
