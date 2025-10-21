<?php

namespace Tests\Feature\Admin;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoPadecimiento;
use App\Models\CatalogoTratamiento;
use App\Models\CatalogoTurno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CatalogoManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[DataProvider('catalogProvider')]
    public function test_guest_is_redirected_from_catalog_index(string $resource): void
    {
        $response = $this->get(route("admin.catalogos.{$resource}.index"));

        $response->assertRedirect(route('login'));
    }

    #[DataProvider('catalogProvider')]
    public function test_non_admin_user_cannot_access_catalog_index(string $resource): void
    {
        $this->seedRoles();

        $user = User::factory()->create();
        $user->syncRoles(['tutor']);

        $this->actingAs($user);

        $response = $this->get(route("admin.catalogos.{$resource}.index"));

        $response->assertForbidden();
    }

    #[DataProvider('catalogProvider')]
    public function test_admin_can_create_catalog_entry(string $resource, string $modelClass, string $table): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $response = $this->post(route("admin.catalogos.{$resource}.store"), [
            'nombre' => 'Elemento de prueba',
            'activo' => 1,
        ]);

        $response->assertRedirect(route("admin.catalogos.{$resource}.index"));

        $this->assertDatabaseHas($table, [
            'nombre' => 'Elemento de prueba',
            'activo' => true,
        ]);
    }

    #[DataProvider('catalogProvider')]
    public function test_admin_cannot_create_duplicate_catalog_entry(string $resource, string $modelClass, string $table): void
    {
        $modelClass::create([
            'nombre' => 'Elemento duplicado',
            'activo' => true,
        ]);

        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $response = $this->from(route("admin.catalogos.{$resource}.create"))
            ->post(route("admin.catalogos.{$resource}.store"), [
                'nombre' => 'Elemento duplicado',
                'activo' => 1,
            ]);

        $response->assertRedirect(route("admin.catalogos.{$resource}.create"));
        $response->assertSessionHasErrors('nombre');
    }

    #[DataProvider('catalogProvider')]
    public function test_admin_can_update_catalog_entry(string $resource, string $modelClass, string $table): void
    {
        $item = $modelClass::create([
            'nombre' => 'Nombre original',
            'activo' => true,
        ]);

        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $response = $this->put(route("admin.catalogos.{$resource}.update", $item), [
            'nombre' => 'Nombre actualizado',
            'activo' => 0,
        ]);

        $response->assertRedirect(route("admin.catalogos.{$resource}.index"));

        $this->assertDatabaseHas($table, [
            'id' => $item->id,
            'nombre' => 'Nombre actualizado',
            'activo' => false,
        ]);
    }

    #[DataProvider('catalogProvider')]
    public function test_admin_can_deactivate_catalog_entry(string $resource, string $modelClass, string $table): void
    {
        $item = $modelClass::create([
            'nombre' => 'Elemento activo',
            'activo' => true,
        ]);

        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $response = $this->delete(route("admin.catalogos.{$resource}.destroy", $item));

        $response->assertRedirect(route("admin.catalogos.{$resource}.index"));

        $this->assertDatabaseHas($table, [
            'id' => $item->id,
            'activo' => false,
        ]);
    }

    public static function catalogProvider(): array
    {
        return [
            'carreras' => ['carreras', CatalogoCarrera::class, 'catalogo_carreras'],
            'tratamientos' => ['tratamientos', CatalogoTratamiento::class, 'catalogo_tratamientos'],
            'padecimientos' => ['padecimientos', CatalogoPadecimiento::class, 'catalogo_padecimientos'],
            'turnos' => ['turnos', CatalogoTurno::class, 'catalogo_turnos'],
        ];
    }

    private function createAdmin(): User
    {
        $this->seedRoles();

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        return $admin;
    }

    private function seedRoles(): void
    {
        if (Role::query()->where('name', 'admin')->doesntExist()) {
            Role::create(['name' => 'admin']);
        }

        if (Role::query()->where('name', 'tutor')->doesntExist()) {
            Role::create(['name' => 'tutor']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
