<?php

namespace Tests\Feature\Expedientes;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteCreationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
        CatalogoCarrera::query()->create(['nombre' => 'Carrera de prueba', 'activo' => true]);
        CatalogoTurno::query()->create(['nombre' => 'Turno de prueba', 'activo' => true]);
        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();
    }

    public function test_facilitator_cannot_see_or_open_the_expediente_creation_form(): void
    {
        $facilitator = $this->userWithRoles(['alumno']);

        $this->actingAs($facilitator)
            ->get(route('expedientes.index'))
            ->assertOk()
            ->assertDontSee('Nuevo expediente')
            ->assertDontSee('href="'.route('expedientes.create').'"', false);
        $this->get(route('expedientes.create'))->assertForbidden();

        $this->assertTrue(Gate::forUser($facilitator)->denies('create', Expediente::class));
        $this->assertDatabaseCount('expedientes', 0);
        $this->assertDatabaseCount('consultorio_reservas', 0);
    }

    public function test_direct_json_creation_is_forbidden_even_with_forged_roles_or_management_permission(): void
    {
        $facilitator = $this->userWithRoles(['alumno']);
        $payload = $this->creationPayload() + [
            'roles' => ['admin', 'paps'],
            'permissions' => ['expedientes.manage'],
            'approved' => true,
            'approved_at' => now()->toDateTimeString(),
            'facilitador_id' => $facilitator->id,
        ];

        $this->actingAs($facilitator)
            ->postJson(route('expedientes.store'), $payload)
            ->assertForbidden();

        $facilitator->givePermissionTo('expedientes.manage');
        $this->assertTrue($facilitator->can('expedientes.manage'));
        $this->assertTrue(Gate::forUser($facilitator)->denies('create', Expediente::class));
        $this->postJson(route('expedientes.store'), $payload)->assertForbidden();

        $this->assertEqualsCanonicalizing(['alumno'], $facilitator->fresh()->getRoleNames()->all());
        $this->assertDatabaseCount('expedientes', 0);
        $this->assertDatabaseCount('consultorio_reservas', 0);
    }

    public function test_unapproved_paps_with_facilitator_role_cannot_create_or_self_approve(): void
    {
        $facilitator = $this->userWithRoles(['paps', 'alumno']);
        $facilitator->forceFill(['approved_at' => null])->save();

        $this->assertTrue(Gate::forUser($facilitator)->denies('create', Expediente::class));
        $this->actingAs($facilitator)
            ->get(route('expedientes.create'))
            ->assertForbidden();
        $this->postJson(route('expedientes.store'), $this->creationPayload() + [
            'approved' => true,
            'approved_at' => now()->toDateTimeString(),
        ])->assertForbidden();

        $this->assertNull($facilitator->fresh()->approved_at);
        $this->assertDatabaseCount('expedientes', 0);
        $this->assertDatabaseCount('consultorio_reservas', 0);
    }

    public function test_assigned_facilitator_keeps_existing_case_work_without_access_to_foreign_cases(): void
    {
        $facilitator = $this->userWithRoles(['alumno']);
        $other = $this->userWithRoles(['alumno']);
        $creator = $this->userWithRoles(['admin']);
        $assigned = $this->expediente($creator, $facilitator, 'Paciente sintético asignado');
        $foreign = $this->expediente($creator, $other, 'Paciente sintético ajeno');

        $this->actingAs($facilitator)
            ->get(route('expedientes.index'))
            ->assertOk()
            ->assertSee($assigned->no_control)
            ->assertDontSee($foreign->no_control);
        $this->get(route('expedientes.show', $assigned))->assertOk();
        $this->get(route('expedientes.edit', $assigned))->assertOk();
        $this->put(route('expedientes.update', $assigned), [
            'paciente' => 'Paciente sintético actualizado',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('Paciente sintético actualizado', $assigned->fresh()->paciente);
        $this->assertTrue(Gate::forUser($facilitator)->allows('create', [Sesion::class, $assigned]));
        $this->assertFalse(Gate::forUser($facilitator)->allows('create', [Sesion::class, $foreign]));
        $this->get(route('expedientes.show', $foreign))->assertForbidden();
        $this->get(route('expedientes.edit', $foreign))->assertForbidden();
        $this->putJson(route('expedientes.update', $foreign), [
            'paciente' => 'No debe modificarse',
        ])->assertForbidden();

        $this->assertSame('Paciente sintético ajeno', $foreign->fresh()->paciente);
        $this->assertDatabaseCount('expedientes', 2);
        $this->assertDatabaseCount('consultorio_reservas', 0);
    }

    private function userWithRoles(array $roles): User
    {
        $user = User::factory()->create(['approved_at' => now(), 'is_active' => true]);
        $user->syncRoles($roles);

        return $user;
    }

    private function creationPayload(): array
    {
        return [
            'paciente' => 'Paciente sintético de creación',
            'apertura' => now()->toDateString(),
            'carrera' => 'Carrera de prueba',
            'turno' => 'Turno de prueba',
        ];
    }

    private function expediente(User $creator, User $facilitator, string $name): Expediente
    {
        return Expediente::factory()->create([
            'paciente' => $name,
            'estado' => 'abierto',
            'carrera' => 'Carrera de prueba',
            'turno' => 'Turno de prueba',
            'creado_por' => $creator->id,
            'facilitador_id' => $facilitator->id,
            'tutor_id' => null,
            'coordinador_id' => null,
        ]);
    }
}
