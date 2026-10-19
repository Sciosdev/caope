<?php

namespace Tests\Feature\Expedientes;

use App\Exports\ExpedientesExport;
use App\Exports\TimelineEventosExport;
use App\Models\Anexo;
use App\Models\CatalogoCarrera;
use App\Models\CatalogoConsultorio;
use App\Models\CatalogoCubiculo;
use App\Models\CatalogoEstrategia;
use App\Models\CatalogoTurno;
use App\Models\Comentario;
use App\Models\Consentimiento;
use App\Models\ConsultorioReserva;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\TimelineEvento;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CoordinatorStrategyScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $neutral;

    private CatalogoEstrategia $strategyA;

    private CatalogoEstrategia $strategyB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        CatalogoCarrera::query()->create(['nombre' => 'Psicología de prueba', 'activo' => true]);
        CatalogoTurno::query()->create(['nombre' => 'Matutino de prueba', 'activo' => true]);
        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();

        $this->neutral = User::factory()->create(['name' => 'Cuenta neutral de prueba']);
        $this->strategyA = CatalogoEstrategia::query()->create(['nombre' => 'Estrategia Alfa de prueba', 'activo' => true]);
        $this->strategyB = CatalogoEstrategia::query()->create(['nombre' => 'Estrategia Beta de prueba', 'activo' => true]);
    }

    public function test_coordinator_access_follows_strategy_membership_instead_of_the_individual_coordinator_field(): void
    {
        $coordinator = $this->coordinator([$this->strategyA->id]);
        $first = $this->expediente(['estrategia_id' => $this->strategyA->id]);
        $second = $this->expediente(['estrategia_id' => $this->strategyA->id, 'coordinador_id' => null]);
        $foreign = $this->expediente(['estrategia_id' => $this->strategyB->id, 'coordinador_id' => $coordinator->id]);
        $unclassified = $this->expediente(['estrategia_id' => null, 'coordinador_id' => $coordinator->id]);

        $this->assertVisibleIds($coordinator, [$first->id, $second->id]);

        foreach ([$first, $second] as $visible) {
            $this->assertTrue($coordinator->isCoordinatorOf($visible));
            $this->assertTrue(Gate::forUser($coordinator)->allows('view', $visible));
            $this->assertTrue(Gate::forUser($coordinator)->allows('update', $visible));
        }

        foreach ([$foreign, $unclassified] as $hidden) {
            $this->assertFalse($coordinator->isCoordinatorOf($hidden));
            $this->assertFalse(Gate::forUser($coordinator)->allows('view', $hidden));
            $this->assertFalse(Gate::forUser($coordinator)->allows('update', $hidden));
        }

        $this->actingAs($coordinator)->get(route('expedientes.index'))
            ->assertOk()
            ->assertSee($first->no_control)
            ->assertSee($second->no_control)
            ->assertDontSee($foreign->no_control)
            ->assertDontSee($unclassified->no_control);

        $this->get(route('expedientes.show', $first))->assertOk();
        $this->get(route('expedientes.edit', $first))->assertOk();
        $this->put(route('expedientes.update', $first), ['observaciones_relevantes' => 'Nota clínica autorizada.'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Nota clínica autorizada.', $first->fresh()->observaciones_relevantes);

        foreach ([$foreign, $unclassified] as $hidden) {
            $this->get(route('expedientes.show', $hidden))->assertForbidden();
            $this->get(route('expedientes.edit', $hidden))->assertForbidden();
            $this->putJson(route('expedientes.update', $hidden), ['observaciones_relevantes' => 'Intento ajeno.'])
                ->assertForbidden();
            $this->assertNotSame('Intento ajeno.', $hidden->fresh()->observaciones_relevantes);
        }
    }

    public function test_coordinator_can_have_multiple_strategies_and_no_membership_means_no_access(): void
    {
        $coordinator = $this->coordinator([]);
        $first = $this->expediente(['estrategia_id' => $this->strategyA->id, 'coordinador_id' => $coordinator->id]);
        $second = $this->expediente(['estrategia_id' => $this->strategyB->id]);
        $this->expediente(['estrategia_id' => null, 'coordinador_id' => $coordinator->id]);

        $this->assertVisibleIds($coordinator, []);
        $this->assertFalse(Gate::forUser($coordinator)->allows('view', $first));
        $this->actingAs($coordinator)->get(route('expedientes.show', $first))->assertForbidden();

        $coordinator->estrategiasCoordinadas()->sync([$this->strategyA->id, $this->strategyB->id]);

        $this->assertVisibleIds($coordinator, [$first->id, $second->id]);
        $this->assertTrue(Gate::forUser($coordinator)->allows('view', $second));
    }

    #[DataProvider('additionalRestrictedRoles')]
    public function test_other_operational_roles_cannot_expand_a_coordinators_strategy_scope(array $additionalRoles): void
    {
        $coordinator = $this->coordinator([$this->strategyA->id]);
        $coordinator->assignRole($additionalRoles);
        $visible = $this->expediente(['estrategia_id' => $this->strategyA->id]);
        $foreign = $this->expediente([
            'estrategia_id' => $this->strategyB->id,
            'creado_por' => $coordinator->id,
            'facilitador_id' => $coordinator->id,
            'tutor_id' => $coordinator->id,
            'coordinador_id' => $coordinator->id,
        ]);
        $foreignSession = $this->makeSession($foreign, ['realizada_por' => $coordinator->id]);
        $foreignAnexo = Anexo::factory()->create(['expediente_id' => $foreign->id, 'subido_por' => $coordinator->id]);

        $this->assertVisibleIds($coordinator, [$visible->id]);
        $this->assertSame([], Sesion::query()->visibleTo($coordinator)->pluck('id')->all());
        $this->assertFalse(Gate::forUser($coordinator)->allows('view', $foreign));
        $this->assertFalse(Gate::forUser($coordinator)->allows('changeState', $foreign));
        $this->assertFalse(Gate::forUser($coordinator)->allows('view', $foreignSession));
        $this->assertFalse(Gate::forUser($coordinator)->allows('update', $foreignSession));
        $this->assertFalse(Gate::forUser($coordinator)->allows('validate', $foreignSession));
        $this->assertFalse(Gate::forUser($coordinator)->allows('view', $foreignAnexo));
        $this->assertFalse(Gate::forUser($coordinator)->allows('delete', $foreignAnexo));
        $this->actingAs($coordinator)->get(route('expedientes.show', $foreign))->assertForbidden();
    }

    public function test_nested_records_and_direct_routes_follow_the_parent_strategy(): void
    {
        $coordinator = $this->coordinator([$this->strategyA->id]);
        $visible = $this->expediente(['estrategia_id' => $this->strategyA->id]);
        $foreign = $this->expediente(['estrategia_id' => $this->strategyB->id, 'coordinador_id' => $coordinator->id]);
        $visibleSession = $this->makeSession($visible);
        $foreignSession = $this->makeSession($foreign);
        $visibleAnexo = Anexo::factory()->create(['expediente_id' => $visible->id, 'subido_por' => $this->neutral->id]);
        $foreignAnexo = Anexo::factory()->create(['expediente_id' => $foreign->id, 'subido_por' => $coordinator->id]);
        $foreignConsent = Consentimiento::factory()->create(['expediente_id' => $foreign->id, 'subido_por' => $coordinator->id]);
        $foreignComment = Comentario::factory()->create([
            'user_id' => $coordinator->id,
            'comentable_type' => Expediente::class,
            'comentable_id' => $foreign->id,
        ]);

        $this->assertSame([$visibleSession->id], Sesion::query()->visibleTo($coordinator)->pluck('id')->all());
        $this->assertTrue(Gate::forUser($coordinator)->allows('view', $visibleAnexo));
        $this->assertTrue(Gate::forUser($coordinator)->allows('delete', $visibleAnexo));
        $this->assertTrue(Gate::forUser($coordinator)->allows('validate', $visibleSession));
        $this->assertFalse(Gate::forUser($coordinator)->allows('view', $foreignAnexo));
        $this->assertFalse(Gate::forUser($coordinator)->allows('view', $foreignConsent));
        $this->assertFalse(Gate::forUser($coordinator)->allows('update', $foreignComment));
        $this->assertFalse(Gate::forUser($coordinator)->allows('delete', $foreignComment));

        $this->actingAs($coordinator);
        $this->get(route('expedientes.sesiones.edit', [$visible, $visibleSession]))->assertOk();
        $this->get(route('expedientes.sesiones.edit', [$foreign, $foreignSession]))->assertForbidden();
        $this->post(route('expedientes.sesiones.validate', [$foreign, $foreignSession]))->assertForbidden();
        $this->get(route('expedientes.anexos.show', [$foreign, $foreignAnexo]))->assertForbidden();
        $this->delete(route('expedientes.anexos.destroy', [$foreign, $foreignAnexo]))->assertForbidden();
        $this->deleteJson(route('api.comentarios.destroy', $foreignComment))->assertForbidden();
        $this->postJson(route('expedientes.timeline.export', $foreign), ['format' => 'xlsx'])->assertForbidden();
    }

    public function test_reports_exports_timeline_and_dashboard_use_the_same_strategy_scope(): void
    {
        $coordinator = $this->coordinator([$this->strategyA->id]);
        $visible = $this->expediente([
            'estrategia_id' => $this->strategyA->id,
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(30),
        ]);
        $foreign = $this->expediente([
            'estrategia_id' => $this->strategyB->id,
            'coordinador_id' => $coordinator->id,
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(30),
        ]);
        $visibleSession = $this->makeSession($visible, ['status_revision' => 'validada']);
        $foreignSession = $this->makeSession($foreign, ['status_revision' => 'validada']);
        $visibleEvent = $this->event($visible, $visibleSession);
        $this->event($foreign, $foreignSession);

        $this->actingAs($coordinator)->get(route('reportes.index'))
            ->assertOk()->assertSee($visible->no_control)->assertDontSee($foreign->no_control);
        $this->assertSame([$visible->id], (new ExpedientesExport([], $coordinator->id))->query()->pluck('id')->all());
        $this->assertSame([$visibleEvent->id], (new TimelineEventosExport($coordinator->id, $visible->id))->query()->pluck('id')->all());
        $this->assertSame([], (new TimelineEventosExport($coordinator->id, $foreign->id))->query()->pluck('id')->all());
        $this->assertSame([$visibleEvent->id], TimelineEvento::query()->visibleTo($coordinator)->pluck('id')->all());

        $this->getJson(route('dashboard.metrics'))->assertOk()
            ->assertJsonPath('expedientes.total', 1)
            ->assertJsonPath('sesiones.tiempo_promedio_validacion.count', 1);
        $this->getJson(route('dashboard.alerts'))->assertOk()
            ->assertJsonCount(1, 'alerts')->assertJsonPath('alerts.0.id', $visible->id);
    }

    public function test_revoking_membership_immediately_revokes_preloaded_policies_scopes_metrics_and_export_tokens(): void
    {
        Storage::fake('local');
        config(['dashboard.cache_ttl' => 300]);
        $coordinator = $this->coordinator([$this->strategyA->id]);
        $expediente = $this->expediente(['estrategia_id' => $this->strategyA->id]);
        $session = $this->makeSession($expediente);
        $anexo = Anexo::factory()->create(['expediente_id' => $expediente->id, 'subido_por' => $this->neutral->id]);
        $coordinator->load('estrategiasCoordinadas');
        $session->load('expediente');
        $anexo->load('expediente');

        $this->assertTrue(Gate::forUser($coordinator)->allows('view', $expediente));
        $this->assertVisibleIds($coordinator, [$expediente->id]);
        $this->actingAs($coordinator)->getJson(route('dashboard.metrics'))->assertJsonPath('expedientes.total', 1);

        $statusToken = (string) Str::uuid();
        $downloadToken = (string) Str::uuid();
        foreach ([$statusToken, $downloadToken] as $token) {
            $path = "exports/expedientes_{$token}.xlsx";
            Storage::disk('local')->put($path, 'Reporte ficticio restringido');
            Cache::put($token, [
                'status' => 'ready', 'path' => $path, 'filename' => 'reporte.xlsx',
                'user_id' => $coordinator->id, 'expediente_ids' => [$expediente->id],
            ], now()->addMinutes(10));
        }

        // A separate model simulates an administrator changing membership in another request.
        User::query()->findOrFail($coordinator->id)->estrategiasCoordinadas()->detach();

        $this->assertTrue($coordinator->relationLoaded('estrategiasCoordinadas'));
        $this->assertFalse($coordinator->isCoordinatorOf($expediente));
        $this->assertFalse(Gate::forUser($coordinator)->allows('view', $expediente));
        $this->assertFalse(Gate::forUser($coordinator)->allows('view', $session));
        $this->assertFalse(Gate::forUser($coordinator)->allows('view', $anexo));
        $this->assertVisibleIds($coordinator, []);
        $this->assertSame([], Sesion::query()->visibleTo($coordinator)->pluck('id')->all());
        $this->get(route('expedientes.show', $expediente))->assertForbidden();
        $this->getJson(route('dashboard.metrics'))->assertJsonPath('expedientes.total', 0);
        $this->getJson(route('reportes.expedientes.export.status', $statusToken))->assertNotFound();
        $this->get(route('reportes.expedientes.download', $downloadToken))->assertNotFound();

        foreach ([$statusToken, $downloadToken] as $token) {
            $this->assertNull(Cache::get($token));
            Storage::disk('local')->assertMissing("exports/expedientes_{$token}.xlsx");
        }
    }

    public function test_coordinator_cannot_change_or_clear_strategy_even_when_coordinating_both_strategies(): void
    {
        $coordinator = $this->coordinator([$this->strategyA->id, $this->strategyB->id]);
        $expediente = $this->expediente(['estrategia_id' => $this->strategyA->id]);

        foreach ([$this->strategyB->id, null] as $targetStrategy) {
            $this->actingAs($coordinator)->put(route('expedientes.update', $expediente), [
                'estrategia_id' => $targetStrategy,
                'observaciones_relevantes' => 'Edición clínica permitida sin cambiar estrategia.',
            ])->assertSessionHasNoErrors()->assertRedirect();

            $this->assertSame($this->strategyA->id, (int) $expediente->fresh()->estrategia_id);
            $this->assertSame('Edición clínica permitida sin cambiar estrategia.', $expediente->fresh()->observaciones_relevantes);
        }
    }

    #[DataProvider('restrictedAssignmentRoles')]
    public function test_facilitators_and_teachers_cannot_change_strategy_during_clinical_updates(string $role): void
    {
        $user = $this->userWithRole($role);
        $expediente = $this->expediente([
            'estrategia_id' => $this->strategyA->id,
            'facilitador_id' => $user->id,
            'tutor_id' => $user->id,
        ]);

        $this->actingAs($user)->put(route('expedientes.update', $expediente), [
            'estrategia_id' => $this->strategyB->id,
            'observaciones_relevantes' => 'Nota permitida.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($this->strategyA->id, (int) $expediente->fresh()->estrategia_id);
        $this->put(route('expedientes.update', $expediente), ['observaciones_relevantes' => 'Otra nota permitida.'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->strategyA->id, (int) $expediente->fresh()->estrategia_id);
    }

    #[DataProvider('globalManagerRoles')]
    public function test_global_managers_retain_global_access_and_can_assign_change_or_clear_strategy(string $role): void
    {
        $manager = $this->userWithRole($role);
        $manager->assignRole('coordinador');
        $coordinatorA = $this->coordinator([$this->strategyA->id]);
        $coordinatorB = $this->coordinator([$this->strategyB->id]);
        $unclassified = $this->expediente(['estrategia_id' => null]);
        $first = $this->expediente(['estrategia_id' => $this->strategyA->id]);
        $second = $this->expediente(['estrategia_id' => $this->strategyB->id]);

        $this->assertVisibleIds($manager, [$unclassified->id, $first->id, $second->id]);
        $this->actingAs($manager)->get(route('expedientes.show', $unclassified))->assertOk();

        $this->post(route('expedientes.store'), [
            'no_control' => 'CA-'.now()->format('Y').'-8801',
            'paciente' => 'Paciente ficticio asignado por estrategia',
            'apertura' => now()->toDateString(),
            'carrera' => 'Psicología de prueba',
            'turno' => 'Matutino de prueba',
            'estrategia_id' => $this->strategyA->id,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $created = Expediente::query()->latest('id')->firstOrFail();
        $this->assertSame($this->strategyA->id, (int) $created->estrategia_id);
        $this->assertTrue(Gate::forUser($coordinatorA)->allows('view', $created));
        $this->assertFalse(Gate::forUser($coordinatorB)->allows('view', $created));

        $this->put(route('expedientes.update', $created), ['estrategia_id' => $this->strategyB->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $created->refresh();
        $this->assertFalse(Gate::forUser($coordinatorA)->allows('view', $created));
        $this->assertTrue(Gate::forUser($coordinatorB)->allows('view', $created));

        $this->put(route('expedientes.update', $created), ['estrategia_id' => null])
            ->assertSessionHasNoErrors()->assertRedirect();
        $created->refresh();
        $this->assertNull($created->estrategia_id);
        $this->assertFalse(Gate::forUser($coordinatorB)->allows('view', $created));
        $this->assertTrue(Gate::forUser($manager)->allows('view', $created));
    }

    public function test_reservation_scope_uses_the_assigned_strategy_and_cannot_be_expanded_by_supervisor_or_other_roles(): void
    {
        $coordinator = $this->coordinator([$this->strategyA->id]);
        $coordinator->assignRole(['alumno', 'docente']);
        $visible = $this->reservation($this->strategyA->nombre, []);
        $foreign = $this->reservation($this->strategyB->nombre, [
            'supervisor_id' => $coordinator->id,
            'estratega_id' => $coordinator->id,
            'usuario_atendido_id' => $coordinator->id,
        ]);
        $this->reservation('Estrategia inexistente en catálogo', ['supervisor_id' => $coordinator->id]);

        $this->assertSame([$visible->id], ConsultorioReserva::query()->visibleTo($coordinator)->pluck('id')->all());
        $this->assertFalse(ConsultorioReserva::query()->visibleTo($coordinator)->whereKey($foreign)->exists());

        $coordinator->load('estrategiasCoordinadas');
        User::query()->findOrFail($coordinator->id)->estrategiasCoordinadas()->detach();
        $this->assertSame([], ConsultorioReserva::query()->visibleTo($coordinator)->pluck('id')->all());
    }

    public function test_coordinator_with_facilitator_role_can_only_create_within_their_assigned_strategies(): void
    {
        $coordinator = $this->coordinator([$this->strategyA->id]);
        $coordinator->assignRole('alumno');

        $this->actingAs($coordinator)->postJson(route('expedientes.store'), $this->createPayload([
            'estrategia_id' => $this->strategyB->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('estrategia_id');
        $this->postJson(route('expedientes.store'), $this->createPayload())
            ->assertUnprocessable()->assertJsonValidationErrors('estrategia_id');
        $this->assertDatabaseCount('expedientes', 0);

        $this->post(route('expedientes.store'), $this->createPayload([
            'estrategia_id' => $this->strategyA->id,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $created = Expediente::query()->firstOrFail();
        $this->assertSame($this->strategyA->id, (int) $created->estrategia_id);
        $this->assertVisibleIds($coordinator, [$created->id]);
    }

    public function test_legacy_create_payload_classifies_the_expediente_using_the_exact_reservation_strategy_name(): void
    {
        $admin = $this->userWithRole('admin');
        $coordinator = $this->coordinator([$this->strategyA->id]);

        $this->actingAs($admin)->post(route('expedientes.store'), $this->createPayload([
            'consultorio_reserva' => $this->reservationPayload($this->strategyA->nombre),
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $created = Expediente::query()->firstOrFail();
        $this->assertSame($this->strategyA->id, (int) $created->estrategia_id);
        $this->assertVisibleIds($coordinator, [$created->id]);
        $this->assertDatabaseHas('consultorio_reservas', ['estrategia' => $this->strategyA->nombre]);
    }

    public function test_create_rejects_a_reservation_whose_strategy_differs_from_the_expediente_strategy(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson(route('expedientes.store'), $this->createPayload([
            'estrategia_id' => $this->strategyA->id,
            'consultorio_reserva' => $this->reservationPayload($this->strategyB->nombre),
        ]))->assertUnprocessable();

        $this->assertDatabaseCount('expedientes', 0);
        $this->assertDatabaseCount('consultorio_reservas', 0);
    }

    public function test_create_validation_restores_the_visible_reservation_fields_and_can_be_corrected_without_losing_assignment(): void
    {
        $admin = $this->userWithRole('admin');
        $facilitator = $this->userWithRole('alumno');
        $teacher = $this->userWithRole('docente');
        $reservation = $this->reservationPayload($this->strategyA->nombre);
        $reservation['fecha'] = now()->addDay()->toDateString();
        $reservation['hora_inicio'] = '09:15';
        $reservation['hora_fin'] = '08:45';
        $reservation['usuario_atendido_id'] = $facilitator->id;
        $reservation['estratega_id'] = $teacher->id;
        $payload = $this->createPayload([
            'paciente' => 'X',
            'estrategia_id' => $this->strategyA->id,
            'consultorio_reserva' => $reservation,
        ]);

        $this->actingAs($admin)->from(route('expedientes.create'))
            ->post(route('expedientes.store'), $payload)
            ->assertRedirect(route('expedientes.create'))
            ->assertSessionHasErrors(['paciente', 'consultorio_reserva.hora_fin']);

        $errorMessage = session('errors')->first('consultorio_reserva.hora_fin');
        $response = $this->get(route('expedientes.create'))->assertOk()->assertSeeText($errorMessage);
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);

        $expectedInputs = [
            'expediente-asignacion-fecha' => $reservation['fecha'],
            'expediente-asignacion-hora-inicio' => '09:15',
            'expediente-asignacion-hora-fin' => '08:45',
            'expediente-consultorio-numero' => '5',
            'expediente-consultorio-cubiculo' => '2',
            'expediente-consultorio-estrategia' => $this->strategyA->nombre,
        ];
        foreach ($expectedInputs as $id => $expected) {
            $this->assertSame($expected, $xpath->evaluate('string(//input[@id="'.$id.'"]/@value)'), $id);
        }

        $expectedSelections = [
            'estrategia_id' => (string) $this->strategyA->id,
            'expediente-asignacion-consultorio' => '5',
            'resumen_clinico_cubiculo' => '2',
            'expediente-asignacion-estrategia' => $this->strategyA->nombre,
            'expediente-asignacion-usuario-atendido' => (string) $facilitator->id,
            'expediente-asignacion-estratega' => (string) $teacher->id,
        ];
        foreach ($expectedSelections as $id => $expected) {
            $this->assertSame($expected, $xpath->evaluate('string(//select[@id="'.$id.'"]/option[@selected]/@value)'), $id);
        }
        $alerts = $xpath->query('//div[@role="alert" and contains(@class, "alert-danger")]');
        $this->assertTrue(collect($alerts)->contains(fn ($alert) => str_contains($alert->textContent, $errorMessage)));
        $this->assertDatabaseCount('expedientes', 0);
        $this->assertDatabaseCount('consultorio_reservas', 0);

        $payload['paciente'] = 'Paciente ficticio con validación corregida';
        $payload['consultorio_reserva']['hora_fin'] = '10:15';
        $this->post(route('expedientes.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $created = Expediente::query()->firstOrFail();
        $this->assertSame($this->strategyA->id, (int) $created->estrategia_id);
        $this->assertDatabaseHas('consultorio_reservas', [
            'fecha' => $reservation['fecha'],
            'hora_inicio' => '09:15',
            'hora_fin' => '10:15',
            'consultorio_numero' => 5,
            'cubiculo_numero' => 2,
            'estrategia' => $this->strategyA->nombre,
            'usuario_atendido_id' => $facilitator->id,
            'estratega_id' => $teacher->id,
        ]);
    }

    public function test_global_manager_cannot_newly_assign_an_inactive_strategy_on_create_or_update(): void
    {
        $admin = $this->userWithRole('admin');
        $this->strategyB->update(['activo' => false]);
        $expediente = $this->expediente(['estrategia_id' => $this->strategyA->id]);

        $this->actingAs($admin)->postJson(route('expedientes.store'), $this->createPayload([
            'estrategia_id' => $this->strategyB->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('estrategia_id');
        $this->putJson(route('expedientes.update', $expediente), ['estrategia_id' => $this->strategyB->id])
            ->assertUnprocessable()->assertJsonValidationErrors('estrategia_id');

        $this->assertDatabaseCount('expedientes', 1);
        $this->assertSame($this->strategyA->id, (int) $expediente->fresh()->estrategia_id);
    }

    public function test_global_manager_can_preserve_the_current_strategy_after_it_becomes_inactive(): void
    {
        $admin = $this->userWithRole('admin');
        $expediente = $this->expediente(['estrategia_id' => $this->strategyA->id]);
        $this->strategyA->update(['activo' => false]);

        $this->actingAs($admin)->get(route('expedientes.edit', $expediente))
            ->assertOk()->assertSeeText($this->strategyA->nombre);
        $this->put(route('expedientes.update', $expediente), [
            'estrategia_id' => $this->strategyA->id,
            'observaciones_relevantes' => 'Nota clínica conservando la estrategia histórica.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($this->strategyA->id, (int) $expediente->fresh()->estrategia_id);
        $this->assertSame('Nota clínica conservando la estrategia histórica.', $expediente->fresh()->observaciones_relevantes);
    }

    public static function additionalRestrictedRoles(): array
    {
        return [
            'coordinador y facilitador' => [['alumno']],
            'coordinador y docente' => [['docente']],
            'coordinador y estratega' => [['estratega']],
            'todos los roles operativos' => [['alumno', 'docente', 'estratega']],
        ];
    }

    public static function restrictedAssignmentRoles(): array
    {
        return [['alumno'], ['docente']];
    }

    public static function globalManagerRoles(): array
    {
        return [['admin'], ['paps']];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['approved_at' => $role === 'paps' ? now() : null]);
        $user->assignRole($role);

        return $user;
    }

    private function coordinator(array $strategies): User
    {
        $user = $this->userWithRole('coordinador');
        $user->estrategiasCoordinadas()->sync($strategies);

        return $user;
    }

    private function createPayload(array $attributes = []): array
    {
        return $attributes + [
            'no_control' => 'CA-'.now()->format('Y').'-8802',
            'paciente' => 'Paciente ficticio de alta por estrategia',
            'apertura' => now()->toDateString(),
            'carrera' => 'Psicología de prueba',
            'turno' => 'Matutino de prueba',
        ];
    }

    private function reservationPayload(string $strategy): array
    {
        CatalogoConsultorio::query()->firstOrCreate(['numero' => 5], ['nombre' => 'Consultorio ficticio', 'activo' => true]);
        CatalogoCubiculo::query()->firstOrCreate(['numero' => 2], ['nombre' => 'Cubículo ficticio', 'activo' => true]);

        return [
            'fecha' => now()->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fin' => '09:00',
            'consultorio_numero' => 5,
            'cubiculo_numero' => 2,
            'estrategia' => $strategy,
        ];
    }

    private function expediente(array $attributes): Expediente
    {
        return Expediente::factory()->create($attributes + [
            'paciente' => 'Paciente ficticio para prueba de alcance',
            'estado' => 'abierto',
            'apertura' => now()->subDays(60)->toDateString(),
            'carrera' => 'Psicología de prueba',
            'turno' => 'Matutino de prueba',
            'creado_por' => $this->neutral->id,
            'facilitador_id' => $this->neutral->id,
            'tutor_id' => $this->neutral->id,
            'coordinador_id' => $this->neutral->id,
            'observaciones_relevantes' => 'Nota inicial ficticia.',
            'resumen_clinico' => Expediente::defaultClinicalSummary(),
        ]);
    }

    private function makeSession(Expediente $expediente, array $attributes = []): Sesion
    {
        return Sesion::factory()->create($attributes + [
            'expediente_id' => $expediente->id,
            'realizada_por' => $this->neutral->id,
            'status_revision' => 'pendiente',
            'validada_por' => $this->neutral->id,
            'created_at' => now()->subDays(30),
            'updated_at' => now()->subDays(25),
        ]);
    }

    private function event(Expediente $expediente, Sesion $session): TimelineEvento
    {
        return TimelineEvento::query()->create([
            'expediente_id' => $expediente->id,
            'actor_id' => $this->neutral->id,
            'evento' => 'sesion.actualizada',
            'payload' => ['sesion_id' => $session->id, 'detalle' => 'Evento ficticio de prueba'],
            'created_at' => now()->subDays(25),
        ]);
    }

    private function reservation(string $strategy, array $attributes): ConsultorioReserva
    {
        return ConsultorioReserva::query()->create($attributes + [
            'fecha' => now()->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fin' => '09:00',
            'consultorio_numero' => 5,
            'cubiculo_numero' => 2,
            'estrategia' => $strategy,
            'creado_por' => $this->neutral->id,
            'supervisor_id' => $this->neutral->id,
        ]);
    }

    private function assertVisibleIds(User $user, array $expected): void
    {
        sort($expected);

        $this->assertSame($expected, Expediente::query()->visibleTo($user)->orderBy('id')->pluck('id')->all());
    }
}
