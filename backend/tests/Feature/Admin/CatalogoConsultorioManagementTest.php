<?php

namespace Tests\Feature\Admin;

use App\Models\CatalogoConsultorio;
use App\Models\CatalogoCubiculo;
use App\Models\CatalogoEstrategia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CatalogoConsultorioManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_create_update_and_delete_consultorio_catalog_entry(): void
    {
        $admin = $this->createAdmin();

        $createResponse = $this->actingAs($admin)->post(route('admin.catalogos.consultorios.store'), [
            'nombre' => 'Consultorio Norte',
            'numero' => 20,
            'activo' => 1,
        ]);

        $createResponse->assertRedirect(route('admin.catalogos.consultorios.index'));
        $this->assertDatabaseHas('catalogo_consultorios', [
            'nombre' => 'Consultorio Norte',
            'numero' => 20,
            'activo' => true,
        ]);

        $consultorio = CatalogoConsultorio::query()->where('numero', 20)->firstOrFail();

        $updateResponse = $this->actingAs($admin)->put(route('admin.catalogos.consultorios.update', $consultorio), [
            'nombre' => 'Consultorio Norte A',
            'numero' => 21,
            'activo' => 1,
        ]);

        $updateResponse->assertRedirect(route('admin.catalogos.consultorios.index'));
        $this->assertDatabaseHas('catalogo_consultorios', [
            'id' => $consultorio->id,
            'nombre' => 'Consultorio Norte A',
            'numero' => 21,
            'activo' => true,
        ]);

        $deleteResponse = $this->actingAs($admin)->delete(route('admin.catalogos.consultorios.force-destroy', $consultorio));

        $deleteResponse->assertRedirect(route('admin.catalogos.consultorios.index'));
        $this->assertDatabaseMissing('catalogo_consultorios', ['id' => $consultorio->id]);
    }

    public function test_admin_can_create_update_and_delete_cubiculo_catalog_entry(): void
    {
        $admin = $this->createAdmin();

        $createResponse = $this->actingAs($admin)->post(route('admin.catalogos.cubiculos.store'), [
            'nombre' => 'Cubículo Norte',
            'numero' => 30,
            'activo' => 1,
        ]);

        $createResponse->assertRedirect(route('admin.catalogos.cubiculos.index'));
        $this->assertDatabaseHas('catalogo_cubiculos', [
            'nombre' => 'Cubículo Norte',
            'numero' => 30,
            'activo' => true,
        ]);

        $cubiculo = CatalogoCubiculo::query()->where('numero', 30)->firstOrFail();

        $updateResponse = $this->actingAs($admin)->put(route('admin.catalogos.cubiculos.update', $cubiculo), [
            'nombre' => 'Cubículo Norte A',
            'numero' => 31,
            'activo' => 1,
        ]);

        $updateResponse->assertRedirect(route('admin.catalogos.cubiculos.index'));
        $this->assertDatabaseHas('catalogo_cubiculos', [
            'id' => $cubiculo->id,
            'nombre' => 'Cubículo Norte A',
            'numero' => 31,
            'activo' => true,
        ]);

        $deleteResponse = $this->actingAs($admin)->delete(route('admin.catalogos.cubiculos.force-destroy', $cubiculo));

        $deleteResponse->assertRedirect(route('admin.catalogos.cubiculos.index'));
        $this->assertDatabaseMissing('catalogo_cubiculos', ['id' => $cubiculo->id]);
    }

    public function test_consultorio_and_sesion_forms_render_active_estrategias_from_catalog(): void
    {
        $admin = $this->createAdmin();

        CatalogoConsultorio::query()->create([
            'nombre' => 'Consultorio Principal',
            'numero' => 1,
            'activo' => true,
        ]);

        CatalogoEstrategia::query()->create([
            'nombre' => 'Estrategia de prueba',
            'activo' => true,
        ]);

        $consultoriosResponse = $this->actingAs($admin)->get(route('consultorios.index'));
        $consultoriosResponse
            ->assertOk()
            ->assertSee('Estrategia de prueba');

        $expediente = \App\Models\Expediente::factory()->create();
        $sesionCreateResponse = $this->actingAs($admin)->get(route('expedientes.sesiones.create', $expediente));
        $sesionCreateResponse
            ->assertOk()
            ->assertSee('Estrategia de prueba');
    }

    private function createAdmin(): User
    {
        if (Role::query()->where('name', 'admin')->doesntExist()) {
            Role::create(['name' => 'admin']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        return $admin;
    }
}
