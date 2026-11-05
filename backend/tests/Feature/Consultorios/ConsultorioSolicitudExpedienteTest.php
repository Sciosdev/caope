<?php

namespace Tests\Feature\Consultorios;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoConsultorio;
use App\Models\CatalogoCubiculo;
use App\Models\CatalogoEstrategia;
use App\Models\CatalogoTurno;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConsultorioSolicitudExpedienteTest extends TestCase
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
        CatalogoConsultorio::query()->updateOrCreate(['numero' => 1], ['nombre' => 'Espacio sintético', 'activo' => true]);
        CatalogoCubiculo::query()->updateOrCreate(['numero' => 1], ['nombre' => 'Cubículo histórico sintético', 'activo' => true]);
        CatalogoCarrera::query()->firstOrCreate(['nombre' => 'Carrera de prueba'], ['activo' => true]);
        CatalogoTurno::query()->firstOrCreate(['nombre' => 'Turno de prueba'], ['activo' => true]);
        $this->strategy = CatalogoEstrategia::query()->firstOrCreate(['nombre' => 'Estrategia sintética'], ['activo' => true]);
        foreach ([CatalogoConsultorio::class, CatalogoCubiculo::class, CatalogoCarrera::class, CatalogoTurno::class, CatalogoEstrategia::class] as $catalogo) {
            $catalogo::flushCache();
        }
        $this->admin = $this->user('admin');
        $this->actingAs($this->admin);
    }

    public function test_request_list_uses_the_linked_patient_and_number_not_the_facilitator_account(): void
    {
        $facilitator = $this->user('alumno');
        $wrong = $this->expediente('Paciente sintético no vinculado', $facilitator);
        $linked = $this->expediente('Paciente sintético correcto', $facilitator);
        $requester = $this->user('paps');
        $proposal = $this->proposal($this->reservation($linked, $facilitator), $requester);

        $response = $this->get(route('admin.consultorios.solicitudes.index'))->assertOk()
            ->assertSee($linked->paciente)->assertSee($linked->no_control)
            ->assertSee($requester->name)
            ->assertDontSee($wrong->paciente)->assertDontSee($wrong->no_control)
            ->assertDontSee($facilitator->name);

        $this->assertPatientAndRequesterInSeparateCells($response, $linked, $requester);
        $this->assertSame($linked->id, $proposal->fresh()->reserva->expediente->id);
    }

    public function test_legacy_unlinked_reservation_does_not_guess_between_a_facilitators_expedientes(): void
    {
        $facilitator = $this->user('alumno');
        $first = $this->expediente('Paciente sintético primero', $facilitator);
        $second = $this->expediente('Paciente sintético segundo', $facilitator);
        $reservation = $this->reservation(null, $facilitator);
        $proposal = $this->proposal($reservation, $this->user('paps'));

        $this->get(route('admin.consultorios.solicitudes.index'))->assertOk()
            ->assertDontSee($first->paciente)->assertDontSee($first->no_control)
            ->assertDontSee($second->paciente)->assertDontSee($second->no_control)
            ->assertDontSee($facilitator->name);

        $this->assertNull($reservation->fresh()->expediente_id);
        $this->assertNull($proposal->fresh()->reserva->expediente);
    }

    public function test_pending_request_without_a_reservation_renders_without_guessing_a_patient(): void
    {
        $linked = $this->expediente('Paciente sintético con reserva eliminada');
        $reservation = $this->reservation($linked);
        $proposal = $this->proposal($reservation, $this->user('paps'));
        $proposal->update(['payload' => $this->reservationPayload()]);
        $reservation->delete();

        $this->get(route('admin.consultorios.solicitudes.index'))->assertOk()
            ->assertDontSee($linked->paciente)->assertDontSee($linked->no_control);

        $this->assertNull($proposal->fresh()->consultorio_reserva_id);
        $this->assertNull($proposal->fresh()->reserva);
        $this->assertSame('pendiente', $proposal->fresh()->status);
    }

    public function test_approved_paps_only_receives_their_own_patient_requests_while_admin_sees_both(): void
    {
        $paps = $this->user('paps');
        $other = $this->user('paps');
        $ownPatient = $this->expediente('Paciente sintético propio');
        $otherPatient = $this->expediente('Paciente sintético de otro solicitante');
        $own = $this->proposal($this->reservation($ownPatient), $paps);
        $foreign = $this->proposal($this->reservation($otherPatient), $other);

        $this->actingAs($paps)->get(route('admin.consultorios.solicitudes.index'))->assertOk()
            ->assertSee($ownPatient->paciente)->assertSee($ownPatient->no_control)
            ->assertDontSee($otherPatient->paciente)->assertDontSee($otherPatient->no_control)
            ->assertViewHas('solicitudesPendientes', fn ($items) => $items->pluck('id')->all() === [$own->id]);

        $this->actingAs($this->admin)->get(route('admin.consultorios.solicitudes.index'))->assertOk()
            ->assertSee($ownPatient->paciente)->assertSee($otherPatient->paciente)
            ->assertViewHas('solicitudesPendientes', fn ($items) => $items->contains('id', $own->id) && $items->contains('id', $foreign->id));
    }

    #[DataProvider('restrictedProfiles')]
    public function test_other_profiles_cannot_read_patient_request_list(string $role, bool $approved): void
    {
        $patient = $this->expediente('Paciente sintético protegido');
        $this->proposal($this->reservation($patient), $this->user('paps'));

        $this->actingAs($this->user($role, $approved))
            ->get(route('admin.consultorios.solicitudes.index'))->assertForbidden();
    }

    public function test_patient_content_is_html_escaped_in_the_request_list(): void
    {
        $unsafe = '<script>alert("paciente-sintetico")</script>';
        $patient = $this->expediente($unsafe);
        $this->proposal($this->reservation($patient), $this->user('paps'));

        $this->get(route('admin.consultorios.solicitudes.index'))->assertOk()
            ->assertSee(e($unsafe), false)->assertDontSee($unsafe, false)
            ->assertSee($patient->no_control);
    }

    #[DataProvider('malformedProposalPayloads')]
    public function test_malformed_legacy_proposals_do_not_crash_or_coerce_an_identity_to_expediente_one(array|string $payload): void
    {
        $patient = $this->expediente('Paciente sintético que no debe inferirse');
        $this->assertSame(1, $patient->id);
        $reservation = $this->reservation();
        $proposal = $this->proposal($reservation, $this->user('paps'));
        $proposal->update(['payload' => $payload]);

        $this->get(route('admin.consultorios.solicitudes.index'))->assertOk()
            ->assertDontSee($patient->paciente)->assertDontSee($patient->no_control);

        $this->assertNull($reservation->fresh()->expediente_id);
        $this->assertSame('pendiente', $proposal->fresh()->status);
    }

    #[DataProvider('approvedCreators')]
    public function test_expediente_creation_links_the_actual_new_record_even_with_a_forged_nested_id(string $role): void
    {
        $creator = $this->user($role);
        $facilitator = $this->user('alumno');
        $forged = $this->expediente('Paciente sintético que no debe vincularse', $facilitator);

        $this->actingAs($creator)->post(route('expedientes.store'), [
            'no_control' => sprintf('CA-%s-9992', now()->year),
            'paciente' => 'Paciente sintético nuevo vinculado',
            'apertura' => now()->toDateString(),
            'carrera' => 'Carrera de prueba',
            'turno' => 'Turno de prueba',
            'facilitador_id' => $facilitator->id,
            'estrategia_id' => $this->strategy->id,
            'cubiculo_id' => CatalogoConsultorio::query()->where('numero', $this->reservationPayload()['consultorio_numero'])->value('id'),
            'resumen_clinico' => ['cubiculo' => 1],
            'expediente_id' => $forged->id,
            'consultorio_reserva' => $this->reservationPayload(['expediente_id' => $forged->id]),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $created = Expediente::query()->where('paciente', 'Paciente sintético nuevo vinculado')->firstOrFail();
        $reservation = ConsultorioReserva::query()->firstOrFail();
        $this->assertNotSame($forged->id, $created->id);
        $this->assertSame($created->id, (int) $reservation->expediente_id);
        $this->assertSame($created->id, $reservation->expediente->id);
        $this->assertSame($creator->id, $created->creado_por);
        $this->assertSame($facilitator->id, (int) $created->facilitador_id);
    }

    public function test_admin_can_explicitly_link_a_legacy_reservation_and_the_request_list_uses_that_record(): void
    {
        $facilitator = $this->user('alumno');
        $wrong = $this->expediente('Paciente sintético no elegido', $facilitator);
        $chosen = $this->expediente('Paciente sintético elegido manualmente', $facilitator);
        $reservation = $this->reservation(null, $facilitator);

        $this->get(route('consultorios.edit', $reservation))->assertOk()
            ->assertSee('name="expediente_id"', false)
            ->assertSee($chosen->paciente)->assertSee($chosen->no_control);
        $this->put(route('consultorios.update', $reservation), $this->reservationPayload([
            'expediente_id' => $chosen->id,
        ]))->assertSessionHasNoErrors()->assertRedirect(route('consultorios.index'));

        $this->assertSame($chosen->id, (int) $reservation->fresh()->expediente_id);
        $this->proposal($reservation->fresh(), $this->user('paps'));
        $this->get(route('admin.consultorios.solicitudes.index'))->assertOk()
            ->assertSee($chosen->paciente)->assertSee($chosen->no_control)
            ->assertDontSee($wrong->paciente)->assertDontSee($wrong->no_control);
    }

    public function test_paps_relink_proposal_preserves_the_current_patient_until_admin_approval(): void
    {
        $first = $this->expediente('Paciente sintético actual');
        $next = $this->expediente('Paciente sintético propuesto');
        $reservation = $this->reservation($first);
        $paps = $this->user('paps');

        $this->actingAs($paps)
            ->put(route('consultorios.request-update', $reservation), $this->reservationPayload([
                'expediente_id' => $next->id,
            ]))->assertSessionHasNoErrors()->assertRedirect(route('consultorios.index'));

        $proposal = ConsultorioReservaSolicitud::query()->firstOrFail();
        $this->assertSame('pendiente', $proposal->status);
        $this->assertSame($next->id, (int) $proposal->payload['expediente_id']);
        $this->assertSame($first->id, (int) $reservation->fresh()->expediente_id);
        $this->get(route('admin.consultorios.solicitudes.index'))->assertOk()
            ->assertSee($next->paciente)->assertSee($next->no_control)
            ->assertSee('Vínculo propuesto; pendiente de aprobación');
        $this->assertSame($first->id, (int) $reservation->fresh()->expediente_id);

        $this->actingAs($this->admin)
            ->post(route('admin.consultorios.solicitudes.approve', $proposal))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.consultorios.solicitudes.index'));

        $this->assertSame($next->id, (int) $reservation->fresh()->expediente_id);
        $this->assertSame($next->id, $reservation->fresh()->expediente->id);
        $this->assertSame('atendida', $proposal->fresh()->status);
    }

    public function test_invalid_manual_expediente_ids_are_rejected_without_changing_links_or_creating_requests(): void
    {
        $patient = $this->expediente('Paciente sintético que debe conservarse');
        $reservation = $this->reservation($patient);
        $paps = $this->user('paps');
        $payload = $this->reservationPayload(['expediente_id' => 999999]);

        $this->putJson(route('consultorios.update', $reservation), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('expediente_id');
        $this->actingAs($paps)->putJson(route('consultorios.request-update', $reservation), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('expediente_id');

        $this->assertSame($patient->id, (int) $reservation->fresh()->expediente_id);
        $this->assertDatabaseCount('consultorio_reserva_solicitudes', 0);
    }

    public function test_permanently_deleting_an_expediente_nulls_the_link_without_deleting_reservation_or_request(): void
    {
        $patient = $this->expediente('Paciente sintético eliminado en prueba');
        $reservation = $this->reservation($patient);
        $proposal = $this->proposal($reservation, $this->user('paps'));
        $patient->forceDelete();

        $this->assertNull($reservation->fresh()->expediente_id);
        $this->assertNull($reservation->fresh()->expediente);
        $this->assertSame($reservation->id, (int) $proposal->fresh()->consultorio_reserva_id);
        $this->assertDatabaseCount('consultorio_reservas', 1);
        $this->assertDatabaseCount('consultorio_reserva_solicitudes', 1);
        $this->get(route('admin.consultorios.solicitudes.index'))->assertOk()
            ->assertDontSee($patient->paciente)->assertDontSee($patient->no_control);
    }

    public function test_archiving_an_expediente_preserves_the_historical_reservation_link(): void
    {
        $patient = $this->expediente('Paciente sintético archivado en prueba');
        $reservation = $this->reservation($patient);
        $proposal = $this->proposal($reservation, $this->user('paps'));

        $this->delete(route('expedientes.destroy', $patient))->assertRedirect(route('expedientes.index'));

        $this->assertSoftDeleted($patient);
        $this->assertSame($patient->id, (int) $reservation->fresh()->expediente_id);
        $this->assertTrue($reservation->fresh()->expediente->trashed());
        $this->assertSame($reservation->id, (int) $proposal->fresh()->consultorio_reserva_id);
        $this->assertDatabaseCount('consultorio_reservas', 1);
        $this->assertDatabaseCount('consultorio_reserva_solicitudes', 1);
    }

    public static function restrictedProfiles(): array
    {
        return [
            'facilitador' => ['alumno', true],
            'docente' => ['docente', true],
            'estratega' => ['estratega', true],
            'coordinador' => ['coordinador', true],
            'PAPS sin aprobar' => ['paps', false],
        ];
    }

    public static function approvedCreators(): array
    {
        return [['admin'], ['paps']];
    }

    public static function malformedProposalPayloads(): array
    {
        return [
            'array must not become id one' => [['expediente_id' => [1]]],
            'boolean must not become id one' => [['expediente_id' => true]],
            'historical string payload' => ['contenido histórico no estructurado'],
        ];
    }

    private function user(string $role, bool $approved = true): User
    {
        $user = User::factory()->create([
            'name' => 'Cuenta sintética '.$role,
            'approved_at' => $approved ? now() : null,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function expediente(string $name, ?User $facilitator = null): Expediente
    {
        return Expediente::factory()->create([
            'paciente' => $name,
            'estado' => 'abierto',
            'carrera' => 'Carrera de prueba',
            'turno' => 'Turno de prueba',
            'creado_por' => $this->admin->id,
            'facilitador_id' => $facilitator?->id,
            'tutor_id' => null,
            'coordinador_id' => null,
            'estrategia_id' => $this->strategy->id,
        ]);
    }

    private function reservation(?Expediente $expediente = null, ?User $facilitator = null): ConsultorioReserva
    {
        return ConsultorioReserva::query()->create([
            'fecha' => '2026-11-09',
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => 1,
            'cubiculo_numero' => 1,
            'estrategia' => $this->strategy->nombre,
            'usuario_atendido_id' => $facilitator?->id,
            'creado_por' => $this->admin->id,
            'expediente_id' => $expediente?->id,
        ]);
    }

    private function proposal(ConsultorioReserva $reservation, User $requester): ConsultorioReservaSolicitud
    {
        return ConsultorioReservaSolicitud::query()->create([
            'consultorio_reserva_id' => $reservation->id,
            'requested_by' => $requester->id,
            'tipo' => 'edicion',
            'status' => 'pendiente',
            'payload' => $this->reservationPayload(['expediente_id' => $reservation->expediente_id]),
        ]);
    }

    private function reservationPayload(array $overrides = []): array
    {
        return array_replace([
            'fecha' => '2026-11-09',
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => 1,
            'cubiculo_numero' => 1,
            'estrategia' => $this->strategy->nombre,
        ], $overrides);
    }

    private function assertPatientAndRequesterInSeparateCells(TestResponse $response, Expediente $patient, User $requester): void
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $matchedRow = false;
        foreach ($xpath->query('//tbody/tr') as $row) {
            $text = $row->textContent;
            if (! str_contains($text, $patient->paciente) || ! str_contains($text, $patient->no_control) || ! str_contains($text, $requester->name)) {
                continue;
            }
            $matchedRow = true;
            $requesterCell = false;
            foreach ($xpath->query('./td', $row) as $cell) {
                $text = $cell->textContent;
                $requesterCell = $requesterCell || (str_contains($text, $requester->name)
                    && ! str_contains($text, $patient->paciente)
                    && ! str_contains($text, $patient->no_control));
            }
            $this->assertTrue($requesterCell, 'The requesting account must remain separate from the patient identity.');
        }
        $this->assertTrue($matchedRow, 'The request row must identify both the patient and the expediente number.');
    }
}
