<?php

namespace Tests\Feature\Admin;

use App\Models\CatalogoEstrategia;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ParticipantStrategyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'paps', 'alumno', 'docente', 'estratega', 'coordinador', 'tutor', 'developer'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->account(['admin']))
            ->withSession(['auth.password_confirmed_at' => time()]);
    }

    #[DataProvider('participantRoles')]
    public function test_each_operational_role_can_participate_in_multiple_strategies_without_becoming_a_coordinator(array $roles): void
    {
        $first = $this->strategy('Participación primera');
        $second = $this->strategy('Participación segunda');

        $this->post(route('admin.users.store'), $this->creationPayload([
            'roles' => $roles,
            'participante_estrategia_ids' => [(string) $first->id, (string) $second->id],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'participante@example.test')->firstOrFail();
        $this->assertEqualsCanonicalizing($roles, $created->getRoleNames()->all());
        $this->assertMemberships($created, [$first->id, $second->id]);
        $this->assertCount(0, $created->estrategiasCoordinadas);
    }

    public static function participantRoles(): array
    {
        return [
            'facilitador' => [['alumno']],
            'docente' => [['docente']],
            'estratega' => [['estratega']],
            'varios roles' => [['alumno', 'docente', 'estratega']],
        ];
    }

    public function test_coordination_and_participation_are_independent_for_a_multi_role_account(): void
    {
        $coordinated = $this->strategy('Solo coordinación');
        $participated = $this->strategy('Solo participación');
        $shared = $this->strategy('Ambas funciones');

        $this->post(route('admin.users.store'), $this->creationPayload([
            'roles' => ['coordinador', 'estratega', 'docente'],
            'estrategia_ids' => [$coordinated->id, $shared->id],
            'participante_estrategia_ids' => [$participated->id, $shared->id],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'participante@example.test')->firstOrFail();
        $this->assertMemberships($created, [$participated->id, $shared->id]);
        $this->assertEqualsCanonicalizing([$coordinated->id, $shared->id], $created->estrategiasCoordinadas->modelKeys());
        $this->assertEqualsCanonicalizing(['coordinador', 'estratega', 'docente'], $created->getRoleNames()->all());
    }

    public function test_payload_without_an_operational_role_does_not_create_memberships_or_roles(): void
    {
        $strategy = $this->strategy('No autoasignar');

        $this->post(route('admin.users.store'), $this->creationPayload([
            'roles' => ['coordinador'],
            'participante_estrategia_ids' => [$strategy->id],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'participante@example.test')->firstOrFail();
        $this->assertMemberships($created, []);
        $this->assertSame(['coordinador'], $created->getRoleNames()->all());
        $this->assertCount(0, $created->estrategiasCoordinadas);
    }

    public function test_approved_paps_can_replace_participation_and_revoke_sessions_without_changing_coordination(): void
    {
        $old = $this->strategy('Participación anterior');
        $new = $this->strategy('Participación nueva');
        $coordinated = $this->strategy('Coordinación conservada');
        $target = $this->account(['coordinador', 'alumno', 'docente']);
        $target->estrategiasCoordinadas()->attach($coordinated);
        $target->estrategiasParticipadas()->attach($old);
        $rememberToken = $target->remember_token;
        $this->createBrowserSession($target);

        $this->actingAs($this->account(['paps']))
            ->put(route('admin.users.update', $target), $this->updatePayload($target, [
                'participante_estrategia_ids' => [$new->id],
            ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $this->assertMemberships($target, [$new->id]);
        $this->assertSame([$coordinated->id], $target->estrategiasCoordinadas()->pluck('catalogo_estrategias.id')->all());
        $this->assertEqualsCanonicalizing(['coordinador', 'alumno', 'docente'], $target->fresh()->getRoleNames()->all());
        $this->assertDatabaseMissing('sessions', ['id' => 'participant-browser']);
        $this->assertNotSame($rememberToken, $target->fresh()->remember_token);
    }

    #[DataProvider('unchangedSelections')]
    public function test_omitted_or_unchanged_selection_preserves_memberships_and_sessions(bool $includeSelection): void
    {
        $first = $this->strategy('Conservar primera');
        $second = $this->strategy('Conservar segunda');
        $target = $this->account(['alumno', 'estratega']);
        $target->estrategiasParticipadas()->attach([$first->id, $second->id]);
        $rememberToken = $target->remember_token;
        $this->createBrowserSession($target);
        $changes = $includeSelection ? ['participante_estrategia_ids' => [(string) $second->id, (string) $first->id]] : [];

        $this->put(route('admin.users.update', $target), $this->updatePayload($target, $changes))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $this->assertMemberships($target, [$first->id, $second->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'participant-browser']);
        $this->assertSame($rememberToken, $target->fresh()->remember_token);
    }

    public static function unchangedSelections(): array
    {
        return ['omitted' => [false], 'same IDs reordered' => [true]];
    }

    #[DataProvider('emptySelections')]
    public function test_explicit_empty_selection_removes_only_participation_and_revokes_sessions(array $selection): void
    {
        $strategy = $this->strategy('Revocar participación');
        $target = $this->account(['coordinador', 'estratega']);
        $target->estrategiasParticipadas()->attach($strategy);
        $target->estrategiasCoordinadas()->attach($strategy);
        $this->createBrowserSession($target);

        $this->put(route('admin.users.update', $target), $this->updatePayload($target, $selection))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $this->assertMemberships($target, []);
        $this->assertSame([$strategy->id], $target->estrategiasCoordinadas()->pluck('catalogo_estrategias.id')->all());
        $this->assertTrue($target->fresh()->hasRole('estratega'));
        $this->assertDatabaseMissing('sessions', ['id' => 'participant-browser']);
    }

    public static function emptySelections(): array
    {
        return [
            'HTML unchecked group' => [['participante_estrategia_ids_present' => '1']],
            'explicit empty array' => [['participante_estrategia_ids' => []]],
        ];
    }

    public function test_removing_all_operational_roles_clears_participation_but_keeps_coordination(): void
    {
        $strategy = $this->strategy('Coordinación permanece');
        $target = $this->account(['coordinador', 'docente', 'alumno']);
        $target->estrategiasParticipadas()->attach($strategy);
        $target->estrategiasCoordinadas()->attach($strategy);

        $this->put(route('admin.users.update', $target), $this->updatePayload($target, [
            'roles' => ['coordinador'],
            'participante_estrategia_ids' => [$strategy->id],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $this->assertMemberships($target, []);
        $this->assertSame([$strategy->id], $target->estrategiasCoordinadas()->pluck('catalogo_estrategias.id')->all());
        $this->assertSame(['coordinador'], $target->fresh()->getRoleNames()->all());
    }

    public function test_retaining_one_operational_role_preserves_memberships_when_field_is_omitted(): void
    {
        $strategy = $this->strategy('Continuar participación');
        $target = $this->account(['alumno', 'docente']);
        $target->estrategiasParticipadas()->attach($strategy);

        $this->put(route('admin.users.update', $target), $this->updatePayload($target, ['roles' => ['docente']]))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $this->assertMemberships($target, [$strategy->id]);
        $this->assertSame(['docente'], $target->fresh()->getRoleNames()->all());
    }

    #[DataProvider('invalidStrategyIds')]
    public function test_invalid_or_duplicate_ids_reject_the_whole_update_without_mutating_any_access(mixed $invalid): void
    {
        $saved = $this->strategy('Asignación intacta');
        $target = $this->account(['docente', 'coordinador']);
        $target->estrategiasParticipadas()->attach($saved);
        $target->estrategiasCoordinadas()->attach($saved);
        $before = $target->fresh()->getAttributes();
        $this->createBrowserSession($target);

        $this->json('PUT', route('admin.users.update', $target), $this->updatePayload($target, [
            'name' => 'Cambio que no debe aplicarse',
            'roles' => ['alumno'],
            'participante_estrategia_ids' => [$saved->id, $invalid === 'duplicate' ? (string) $saved->id : $invalid],
        ]), [], JSON_PRESERVE_ZERO_FRACTION)->assertUnprocessable()->assertJsonValidationErrors('participante_estrategia_ids.1');

        $this->assertSame($before, $target->fresh()->getAttributes());
        $this->assertEqualsCanonicalizing(['docente', 'coordinador'], $target->fresh()->getRoleNames()->all());
        $this->assertMemberships($target, [$saved->id]);
        $this->assertSame([$saved->id], $target->estrategiasCoordinadas()->pluck('catalogo_estrategias.id')->all());
        $this->assertDatabaseHas('sessions', ['id' => 'participant-browser']);
    }

    public static function invalidStrategyIds(): array
    {
        return [
            'boolean true must not mean ID one' => [true],
            'boolean false' => [false],
            'float must not mean integer ID' => [1.0],
            'decimal string' => ['1.0'],
            'scientific notation' => ['1e0'],
            'negative' => [-1],
            'zero' => [0],
            'null' => [null],
            'nested array' => [[1]],
            'missing ID' => [999999],
            'duplicate int and string' => ['duplicate'],
        ];
    }

    public function test_inactive_membership_can_be_retained_but_cannot_be_copied_from_coordination_or_assigned_new(): void
    {
        $assigned = $this->strategy('Participación inactiva conservada', false);
        $coordinated = $this->strategy('Solo coordinación inactiva', false);
        $target = $this->account(['docente', 'coordinador']);
        $target->estrategiasParticipadas()->attach($assigned);
        $target->estrategiasCoordinadas()->attach($coordinated);

        $this->put(route('admin.users.update', $target), $this->updatePayload($target, [
            'participante_estrategia_ids' => [$assigned->id],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $this->putJson(route('admin.users.update', $target), $this->updatePayload($target, [
            'participante_estrategia_ids' => [$assigned->id, $coordinated->id],
        ]))->assertUnprocessable()->assertJsonValidationErrors('participante_estrategia_ids.1');

        $this->postJson(route('admin.users.store'), $this->creationPayload([
            'participante_estrategia_ids' => [$assigned->id],
        ]))->assertUnprocessable()->assertJsonValidationErrors('participante_estrategia_ids.0');

        $this->assertMemberships($target, [$assigned->id]);
        $this->assertDatabaseMissing('users', ['email' => 'participante@example.test']);
    }

    public function test_forms_keep_participation_separate_and_restore_old_input_including_unchecked_group(): void
    {
        $active = $this->strategy('Disponible');
        $participated = $this->strategy('Inactiva participante', false);
        $coordinated = $this->strategy('Inactiva coordinada', false);
        $hidden = $this->strategy('Inactiva ajena', false);
        $target = $this->account(['docente', 'coordinador']);
        $target->estrategiasParticipadas()->attach($participated);
        $target->estrategiasCoordinadas()->attach($coordinated);

        $create = $this->get(route('admin.users.create'))->assertOk();
        $this->assertCheckboxes($create, [$active->id], []);
        $create->assertSee('name="participante_estrategia_ids_present"', false)
            ->assertSee('Docentes y estrategas requieren tanto la estrategia como la responsabilidad del expediente.')
            ->assertSee('Los facilitadores conservan acceso solo a sus casos asignados;')
            ->assertDontSee($participated->nombre)->assertDontSee($coordinated->nombre)->assertDontSee($hidden->nombre);

        $edit = $this->get(route('admin.users.edit', $target))->assertOk();
        $this->assertCheckboxes($edit, [$active->id, $participated->id], [$participated->id]);
        $this->get(route('admin.users.index'))->assertOk()->assertSee('Participación:')->assertSee($participated->nombre);

        $oldInput = $this->withSession(['_old_input' => [
            'participante_estrategia_ids_present' => '1',
            'participante_estrategia_ids' => [(string) $active->id],
        ]])->get(route('admin.users.edit', $target))->assertOk();
        $this->assertCheckboxes($oldInput, [$active->id, $participated->id], [$active->id]);

        $empty = $this->withSession(['_old_input' => ['participante_estrategia_ids_present' => '1']])
            ->get(route('admin.users.edit', $target))->assertOk();
        $this->assertCheckboxes($empty, [$active->id, $participated->id], []);
        $this->assertMemberships($target, [$participated->id]);
    }

    public function test_unapproved_paps_cannot_assign_memberships_or_mutate_target(): void
    {
        $strategy = $this->strategy('Protegida');
        $target = $this->account(['docente']);
        $paps = $this->account(['paps']);
        $paps->forceFill(['approved_at' => null])->save();

        $this->actingAs($paps)->putJson(route('admin.users.update', $target), $this->updatePayload($target, [
            'participante_estrategia_ids' => [$strategy->id],
        ]))->assertForbidden();

        $this->assertMemberships($target, []);
    }

    public function test_paps_cannot_grant_privileged_roles_while_assigning_participation(): void
    {
        $strategy = $this->strategy('Sin elevación');
        $target = $this->account(['docente']);
        $this->actingAs($this->account(['paps']));

        foreach (['admin', 'developer'] as $forgedRole) {
            $this->putJson(route('admin.users.update', $target), $this->updatePayload($target, [
                'roles' => ['docente', $forgedRole],
                'participante_estrategia_ids' => [$strategy->id],
            ]))->assertUnprocessable()->assertJsonValidationErrors('roles.1');
        }

        $this->assertMemberships($target, []);
        $this->assertSame(['docente'], $target->fresh()->getRoleNames()->all());
    }

    public function test_operational_accounts_cannot_change_memberships_through_management_or_forged_profile_fields(): void
    {
        $strategy = $this->strategy('Sin autoasignación');
        $target = $this->account(['docente']);
        $this->actingAs($target)->putJson(route('admin.users.update', $target), $this->updatePayload($target, [
            'participante_estrategia_ids' => [$strategy->id],
        ]))->assertForbidden();

        $this->patchJson(route('profile.update'), [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['admin', 'docente'],
            'participante_estrategia_ids' => [$strategy->id],
            'participante_estrategia_ids_present' => true,
        ])->assertRedirect(route('profile.edit'));

        $this->assertMemberships($target, []);
        $this->assertSame(['docente'], $target->fresh()->getRoleNames()->all());
    }

    private function account(array $roles): User
    {
        $user = User::factory()->create(['is_active' => true, 'approved_at' => now()]);
        $user->syncRoles($roles);

        return $user;
    }

    private function strategy(string $name, bool $active = true): CatalogoEstrategia
    {
        return CatalogoEstrategia::create(['nombre' => $name, 'activo' => $active]);
    }

    private function creationPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Cuenta operativa sintética',
            'email' => 'participante@example.test',
            'password' => 'Clave-Prueba-2468',
            'password_confirmation' => 'Clave-Prueba-2468',
            'roles' => ['docente'],
            'approved' => true,
            'is_active' => true,
        ], $overrides);
    }

    private function updatePayload(User $user, array $overrides = []): array
    {
        return array_replace([
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->all(),
        ], $overrides);
    }

    private function assertMemberships(User $user, array $expected): void
    {
        $this->assertEqualsCanonicalizing($expected, $user->estrategiasParticipadas()->pluck('catalogo_estrategias.id')->all());
    }

    private function createBrowserSession(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => 'participant-browser',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Synthetic participant strategy test',
            'payload' => 'test-payload',
            'last_activity' => time(),
        ]);
    }

    private function assertCheckboxes(TestResponse $response, array $available, array $checked): void
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//input[@name="participante_estrategia_ids[]"]');
        $actualAvailable = [];
        $actualChecked = [];
        foreach ($nodes as $node) {
            $this->assertSame('checkbox', $node->getAttribute('type'));
            $actualAvailable[] = (int) $node->getAttribute('value');
            if ($node->hasAttribute('checked')) {
                $actualChecked[] = (int) $node->getAttribute('value');
            }
        }
        $this->assertEqualsCanonicalizing($available, $actualAvailable);
        $this->assertEqualsCanonicalizing($checked, $actualChecked);
    }
}
