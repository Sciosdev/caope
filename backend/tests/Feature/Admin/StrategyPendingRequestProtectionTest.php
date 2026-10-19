<?php

namespace Tests\Feature\Admin;

use App\Models\CatalogoEstrategia;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StrategyPendingRequestProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_cannot_rename_a_strategy_proposed_by_a_pending_request(): void
    {
        [$strategy, $reservation, $request] = $this->pendingRequest();
        $originalReservation = $reservation->fresh()->getAttributes();
        $originalRequest = $request->fresh()->getAttributes();

        $this->actingAs($this->admin())
            ->put(route('admin.catalogos.estrategias.update', $strategy), [
                'nombre' => 'Nombre diferente',
                'activo' => false,
            ])
            ->assertRedirect(route('admin.catalogos.estrategias.edit', $strategy))
            ->assertSessionHasErrors('nombre');

        $this->assertSame('Estrategia propuesta', $strategy->fresh()->nombre);
        $this->assertTrue($strategy->fresh()->activo);
        $this->assertSame($originalReservation, $reservation->fresh()->getAttributes());
        $this->assertSame($originalRequest, $request->fresh()->getAttributes());
    }

    public function test_admin_cannot_permanently_delete_a_strategy_proposed_from_another_strategy(): void
    {
        [$strategy, $reservation, $request] = $this->pendingRequest();
        $this->assertNotSame($strategy->nombre, $reservation->estrategia);
        $originalReservation = $reservation->fresh()->getAttributes();
        $originalRequest = $request->fresh()->getAttributes();

        $this->actingAs($this->admin())
            ->delete(route('admin.catalogos.estrategias.force-destroy', $strategy))
            ->assertRedirect(route('admin.catalogos.estrategias.index'))
            ->assertSessionHasErrors('catalogo');

        $this->assertDatabaseHas('catalogo_estrategias', ['id' => $strategy->id, 'nombre' => $strategy->nombre]);
        $this->assertSame($originalReservation, $reservation->fresh()->getAttributes());
        $this->assertSame($originalRequest, $request->fresh()->getAttributes());
    }

    public function test_attending_the_request_allows_strategy_rename(): void
    {
        [$strategy, , $request] = $this->pendingRequest();
        $request->update(['status' => 'atendida']);

        $this->actingAs($this->admin())
            ->put(route('admin.catalogos.estrategias.update', $strategy), [
                'nombre' => 'Nombre actualizado',
                'activo' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.catalogos.estrategias.index'));

        $this->assertSame('Nombre actualizado', $strategy->fresh()->nombre);
        $this->assertSame('atendida', $request->fresh()->status);
    }

    public function test_attended_requests_do_not_block_permanent_deletion(): void
    {
        [$strategy, , $request] = $this->pendingRequest();
        $request->update(['status' => 'atendida']);

        $this->actingAs($this->admin())
            ->delete(route('admin.catalogos.estrategias.force-destroy', $strategy))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.catalogos.estrategias.index'));

        $this->assertDatabaseMissing('catalogo_estrategias', ['id' => $strategy->id]);
        $this->assertSame('atendida', $request->fresh()->status);
    }

    public function test_pending_requests_allow_same_name_and_activation_changes(): void
    {
        [$strategy, , $request] = $this->pendingRequest();
        $this->actingAs($this->admin())
            ->put(route('admin.catalogos.estrategias.update', $strategy), [
                'nombre' => $strategy->nombre,
                'activo' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.catalogos.estrategias.index'));

        $this->assertFalse($strategy->fresh()->activo);
        $this->assertSame('pendiente', $request->fresh()->status);

        $this->patch(route('admin.catalogos.estrategias.toggle-active', $strategy))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.catalogos.estrategias.index'));
        $this->assertTrue($strategy->fresh()->activo);

        $this->delete(route('admin.catalogos.estrategias.destroy', $strategy))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.catalogos.estrategias.index'));
        $this->assertFalse($strategy->fresh()->activo);
    }

    public function test_unrelated_pending_requests_do_not_block_another_strategy_or_new_names(): void
    {
        $this->pendingRequest();
        $other = CatalogoEstrategia::create(['nombre' => 'Otra estrategia', 'activo' => true]);
        $this->actingAs($this->admin());

        $this->put(route('admin.catalogos.estrategias.update', $other), [
            'nombre' => 'Otra estrategia actualizada',
            'activo' => true,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.catalogos.estrategias.index'));

        $this->post(route('admin.catalogos.estrategias.store'), [
            'nombre' => 'Nueva estrategia',
            'activo' => true,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.catalogos.estrategias.index'));

        $this->delete(route('admin.catalogos.estrategias.force-destroy', $other))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.catalogos.estrategias.index'));

        $this->assertDatabaseMissing('catalogo_estrategias', ['id' => $other->id]);
        $this->assertDatabaseHas('catalogo_estrategias', ['nombre' => 'Nueva estrategia']);
    }

    public function test_non_admin_without_catalog_permissions_cannot_rename_or_delete_strategy(): void
    {
        [$strategy] = $this->pendingRequest();
        $this->actingAs(User::factory()->create());

        $this->putJson(route('admin.catalogos.estrategias.update', $strategy), [
            'nombre' => 'Nombre no autorizado',
        ])->assertForbidden();

        $this->deleteJson(route('admin.catalogos.estrategias.force-destroy', $strategy))->assertForbidden();
        $this->assertSame('Estrategia propuesta', $strategy->fresh()->nombre);
    }

    private function pendingRequest(): array
    {
        $proposed = CatalogoEstrategia::create(['nombre' => 'Estrategia propuesta', 'activo' => true]);
        $current = CatalogoEstrategia::create(['nombre' => 'Estrategia actual', 'activo' => true]);
        $requester = User::factory()->create();
        $reservation = ConsultorioReserva::create([
            'fecha' => '2026-10-20',
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => 1,
            'cubiculo_numero' => 1,
            'estrategia' => $current->nombre,
            'creado_por' => $requester->id,
            'origen_expediente' => true,
        ]);
        $request = ConsultorioReservaSolicitud::create([
            'consultorio_reserva_id' => $reservation->id,
            'requested_by' => $requester->id,
            'tipo' => 'edicion',
            'payload' => ['estrategia' => $proposed->nombre],
            'status' => 'pendiente',
        ]);

        return [$proposed, $reservation, $request];
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}
