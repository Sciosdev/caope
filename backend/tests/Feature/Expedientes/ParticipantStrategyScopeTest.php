<?php

namespace Tests\Feature\Expedientes;

use App\Exports\ExpedientesExport;
use App\Models\Anexo;
use App\Models\CatalogoCarrera;
use App\Models\CatalogoEstrategia;
use App\Models\CatalogoTurno;
use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\SesionAdjunto;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ParticipantStrategyScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $facilitator;

    private CatalogoEstrategia $first;

    private CatalogoEstrategia $second;

    private CatalogoEstrategia $other;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'dashboard.cache_ttl' => 600]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
        Storage::fake('private');
        $this->admin = $this->user('admin');
        $this->facilitator = $this->user('alumno');
        CatalogoCarrera::query()->create(['nombre' => 'Carrera sintética', 'activo' => true]);
        CatalogoTurno::query()->create(['nombre' => 'Matutino', 'activo' => true]);
        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();
        $this->first = CatalogoEstrategia::query()->create(['nombre' => 'CREAS sintética', 'activo' => true]);
        $this->second = CatalogoEstrategia::query()->create(['nombre' => 'Segunda estrategia sintética', 'activo' => true]);
        $this->other = CatalogoEstrategia::query()->create(['nombre' => 'Estrategia no asignada sintética', 'activo' => true]);
    }

    #[DataProvider('teacherRoles')]
    public function test_strategy_and_responsibility_are_both_required_in_queries_and_policies(string $role): void
    {
        $actor = $this->user($role);
        $actor->estrategiasParticipadas()->sync([$this->first->id, $this->second->id]);
        $mine = [$this->caseFor($actor, $this->first), $this->caseFor($actor, $this->second)];
        $hidden = [
            $this->caseFor($this->user($role), $this->first),
            $this->caseFor($actor, $this->other),
            $this->caseFor($actor, null),
        ];

        $this->assertEqualsCanonicalizing(array_column($mine, 'id'), Expediente::query()->visibleTo($actor)->pluck('id')->all());
        $this->assertEqualsCanonicalizing(array_column($mine, 'id'), Expediente::query()->validatableBy($actor)->pluck('id')->all());
        foreach ($mine as $case) {
            $session = $this->sessionFor($case);
            $this->assertTrue($actor->isTutorOf($case));
            $this->assertTrue(Gate::forUser($actor)->allows('view', $case));
            $this->assertTrue(Gate::forUser($actor)->allows('validate', $session));
            $this->actingAs($actor)->get(route('expedientes.show', $case))->assertOk();
        }
        foreach ($hidden as $case) {
            $session = $this->sessionFor($case);
            $this->assertFalse($actor->isTutorOf($case));
            $this->assertFalse(Gate::forUser($actor)->allows('view', $case));
            $this->assertFalse(Gate::forUser($actor)->allows('update', $case));
            $this->assertFalse(Gate::forUser($actor)->allows('validate', $session));
            $this->actingAs($actor)->get(route('expedientes.show', $case))->assertForbidden();
        }
        $this->assertEqualsCanonicalizing(
            array_map(fn ($case) => $case->sesiones()->sole()->id, $mine),
            Sesion::query()->visibleTo($actor)->pluck('id')->all(),
        );
    }

    #[DataProvider('teacherRoles')]
    public function test_no_memberships_denies_responsible_cases_without_falling_back_to_old_coordinator_assignments(string $role): void
    {
        $actor = $this->user($role);
        $actor->estrategiasCoordinadas()->attach($this->first);
        $case = $this->caseFor($actor, $this->first);

        $this->assertSame([], Expediente::query()->visibleTo($actor)->pluck('id')->all());
        $this->assertFalse($actor->isTutorOf($case));
        $this->actingAs($actor)->get(route('expedientes.show', $case))->assertForbidden();
    }

    #[DataProvider('teacherRoles')]
    public function test_direct_clinical_document_urls_remain_denied_when_one_condition_is_missing(string $role): void
    {
        $actor = $this->user($role);
        $actor->estrategiasParticipadas()->attach($this->first);
        foreach ([$this->caseFor($this->user($role), $this->first), $this->caseFor($actor, $this->other)] as $case) {
            $session = $this->sessionFor($case);
            $attachment = $this->attachment($session);
            $consent = Consentimiento::factory()->create(['expediente_id' => $case->id]);
            $annex = Anexo::factory()->create(['expediente_id' => $case->id]);
            $this->assertFalse(Gate::forUser($actor)->allows('view', $consent));
            $this->assertFalse(Gate::forUser($actor)->allows('view', $annex));
            foreach ([
                route('expedientes.sesiones.index', $case),
                route('expedientes.sesiones.show', [$case, $session]),
                route('expedientes.sesiones.adjuntos.download', [$case, $session, $attachment]),
                route('expedientes.sesiones.adjuntos.preview', [$case, $session, $attachment]),
            ] as $url) {
                $this->actingAs($actor)->get($url)->assertForbidden()->assertDontSee($attachment->nombre_original);
            }
            Storage::disk('private')->assertExists($attachment->ruta);
        }
    }

    #[DataProvider('teacherRoles')]
    public function test_dashboard_and_export_use_the_same_intersection_without_clinical_leaks(string $role): void
    {
        $actor = $this->user($role);
        $actor->estrategiasParticipadas()->sync([$this->first->id, $this->second->id]);
        $first = $this->caseFor($actor, $this->first);
        $second = $this->caseFor($actor, $this->second);
        $this->caseFor($this->user($role), $this->first);
        $this->caseFor($actor, $this->other);
        $this->caseFor($actor, null);

        $export = new ExpedientesExport([], $actor->id);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $export->query()->pluck('id')->all());
        $this->actingAs($actor)->getJson(route('dashboard.metrics'))->assertOk()->assertJsonPath('expedientes.total', 2);
        $response = $this->get(route('expedientes.index'))->assertOk();
        $response->assertSee($first->no_control)->assertSee($second->no_control);
    }

    #[DataProvider('teacherRoles')]
    public function test_removing_a_membership_revokes_cached_urls_and_loaded_user_relations_immediately(string $role): void
    {
        $actor = $this->user($role);
        $actor->estrategiasParticipadas()->sync([$this->first->id, $this->second->id]);
        $case = $this->caseFor($actor, $this->first);
        $other = $this->caseFor($actor, $this->second);
        $session = $this->sessionFor($case);
        $attachment = $this->attachment($session);
        $url = route('expedientes.sesiones.adjuntos.download', [$case, $session, $attachment]);
        $actor->load('estrategiasParticipadas');
        $this->actingAs($actor)->get($url)->assertOk();
        $this->getJson(route('dashboard.metrics'))->assertOk()->assertJsonPath('expedientes.total', 2);

        User::query()->findOrFail($actor->id)->estrategiasParticipadas()->detach($this->first);

        $this->assertFalse($actor->isTutorOf($case));
        $this->assertSame([$other->id], Expediente::query()->visibleTo($actor)->pluck('id')->all());
        $this->get($url)->assertForbidden();
        $this->getJson(route('dashboard.metrics'))->assertOk()->assertJsonPath('expedientes.total', 1);
        Storage::disk('private')->assertExists($attachment->ruta);
    }

    public function test_multiple_operational_roles_do_not_turn_the_two_requirements_into_alternatives(): void
    {
        $actor = $this->user('docente');
        $actor->assignRole('estratega');
        $actor->estrategiasParticipadas()->attach($this->first);
        $allowed = $this->caseFor($actor, $this->first);
        $outside = $this->caseFor($actor, $this->other);
        $notResponsible = $this->caseFor($this->user('docente'), $this->first);

        $this->assertSame([$allowed->id], Expediente::query()->visibleTo($actor)->pluck('id')->all());
        $this->assertFalse(Gate::forUser($actor)->allows('view', $outside));
        $this->assertFalse(Gate::forUser($actor)->allows('view', $notResponsible));
    }

    public function test_facilitator_memberships_do_not_grant_other_cases_or_replace_the_existing_case_assignment(): void
    {
        $this->facilitator->estrategiasParticipadas()->attach($this->second);
        $assigned = $this->caseFor($this->user('docente'), $this->first);
        $other = $this->caseFor($this->user('docente'), $this->second);
        $other->update(['facilitador_id' => $this->user('alumno')->id]);

        $this->assertSame([$assigned->id], Expediente::query()->visibleTo($this->facilitator)->pluck('id')->all());
        $this->assertFalse(Gate::forUser($this->facilitator)->allows('view', $other));
        $this->assertFalse(Gate::forUser($this->facilitator)->allows('create', Expediente::class));
    }

    public function test_coordination_scope_is_not_bypassed_by_an_additional_teacher_membership(): void
    {
        $actor = $this->user('coordinador');
        $actor->assignRole('docente');
        $actor->estrategiasCoordinadas()->attach($this->first);
        $actor->estrategiasParticipadas()->attach($this->second);
        $coordinated = $this->caseFor($this->user('docente'), $this->first);
        $outside = $this->caseFor($actor, $this->second);

        $this->assertSame([$coordinated->id], Expediente::query()->visibleTo($actor)->pluck('id')->all());
        $this->assertFalse(Gate::forUser($actor)->allows('view', $outside));
    }

    public function test_a_strategy_with_participant_assignments_cannot_be_deleted_silently(): void
    {
        $this->user('docente')->estrategiasParticipadas()->attach($this->first);
        $this->actingAs($this->admin)->delete(route('admin.catalogos.estrategias.force-destroy', $this->first))
            ->assertSessionHasErrors('catalogo');
        $this->assertDatabaseHas('catalogo_estrategias', ['id' => $this->first->id]);
        $this->assertDatabaseCount('participante_estrategia', 1);
    }

    public static function teacherRoles(): array
    {
        return [['docente'], ['estratega']];
    }

    public function test_admin_can_select_both_docentes_and_estrategas_as_responsible_people(): void
    {
        $docente = $this->user('docente');
        $estratega = $this->user('estratega');
        $inactive = $this->user('estratega');
        $inactive->forceFill(['is_active' => false])->save();

        $this->actingAs($this->admin)->get(route('expedientes.create'))->assertOk()
            ->assertViewHas('tutores', fn ($users) => $users->contains('id', $docente->id) && $users->contains('id', $estratega->id));
        $this->get(route('consultorios.index'))->assertOk()
            ->assertViewHas('docentes', fn ($users) => $users->contains('id', $docente->id)
                && $users->contains('id', $estratega->id) && ! $users->contains('id', $inactive->id));
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['approved_at' => now(), 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function caseFor(User $teacher, ?CatalogoEstrategia $strategy): Expediente
    {
        return Expediente::factory()->create([
            'paciente' => 'Paciente sintético para control de estrategias',
            'estado' => 'abierto', 'carrera' => 'Carrera sintética', 'turno' => 'Matutino',
            'creado_por' => $this->admin->id, 'facilitador_id' => $this->facilitator->id,
            'tutor_id' => $teacher->id, 'estrategia_id' => $strategy?->id,
        ]);
    }

    private function sessionFor(Expediente $case): Sesion
    {
        return Sesion::factory()->create(['expediente_id' => $case->id, 'realizada_por' => $this->facilitator->id, 'status_revision' => 'pendiente']);
    }

    private function attachment(Sesion $session): SesionAdjunto
    {
        $path = 'sesiones/'.$session->id.'/documento-sintetico.pdf';
        Storage::disk('private')->put($path, "%PDF-1.4\n%%EOF\n");

        return $session->adjuntos()->create([
            'nombre_original' => 'Documento clínico sintético.pdf', 'ruta' => $path,
            'disk' => 'private', 'mime_type' => 'application/pdf', 'tamano' => 15,
            'subido_por' => $this->facilitator->id,
        ]);
    }
}
