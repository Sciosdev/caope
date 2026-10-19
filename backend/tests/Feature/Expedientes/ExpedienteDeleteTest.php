<?php

namespace Tests\Feature\Expedientes;

use App\Models\CatalogoEstrategia;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpedienteDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public static function authorizedRoles(): array
    {
        return [
            'administrador' => ['admin'],
            'PAPS aprobado' => ['paps'],
        ];
    }

    #[DataProvider('authorizedRoles')]
    public function test_authorized_users_see_delete_action_on_index_and_detail(string $role): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $user->assignRole($role);
        $expediente = Expediente::factory()->create();

        foreach (['expedientes.index', 'expedientes.show'] as $route) {
            $response = $this->actingAs($user)->get(route($route, $route === 'expedientes.show' ? $expediente : []));

            $response->assertOk();
            $form = $this->deleteForm($response, $expediente);
            $this->assertNotNull($form, 'Debe aparecer la opción de eliminar en '.$route);
            $this->assertSame('post', strtolower($form->getAttribute('method')));
            $this->assertSame($expediente->no_control, $form->getAttribute('data-expediente'));
            $this->assertStringContainsString('return confirm(', $form->getAttribute('onsubmit'));
            $this->assertStringContainsString('Esta acción no se puede deshacer.', $form->getAttribute('onsubmit'));
            $this->assertSame(1, (new DOMXPath($form->ownerDocument))->query('.//input[@name="_token"]', $form)->length);
        }
    }

    #[DataProvider('authorizedRoles')]
    public function test_authorized_users_can_delete_only_the_selected_expediente(string $role): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $user->assignRole($role);
        $expediente = Expediente::factory()->create();
        $other = Expediente::factory()->create();

        $response = $this->actingAs($user)->delete(route('expedientes.destroy', $expediente));

        $response->assertRedirect(route('expedientes.index'));
        $response->assertSessionHas('status', 'Expediente eliminado correctamente.');
        $this->assertDatabaseMissing('expedientes', ['id' => $expediente->id]);
        $this->assertDatabaseHas('expedientes', ['id' => $other->id]);
        $this->get(route('expedientes.index'))
            ->assertOk()
            ->assertSeeText('Expediente eliminado correctamente.');
    }

    public static function restrictedRoles(): array
    {
        return [
            'facilitador' => ['alumno'],
            'docente' => ['docente'],
            'estratega' => ['estratega'],
            'coordinador' => ['coordinador'],
        ];
    }

    #[DataProvider('restrictedRoles')]
    public function test_assigned_users_without_delete_access_cannot_see_or_use_action(string $role): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $user->assignRole($role);
        $estrategiaId = null;
        if ($role === 'coordinador') {
            $estrategia = CatalogoEstrategia::query()->create([
                'nombre' => 'Estrategia del coordinador de prueba',
                'activo' => true,
            ]);
            $user->estrategiasCoordinadas()->attach($estrategia);
            $estrategiaId = $estrategia->id;
        }

        $expediente = Expediente::factory()->create([
            'estado' => 'abierto',
            'creado_por' => $user->id,
            'tutor_id' => $user->id,
            'coordinador_id' => $user->id,
            'estrategia_id' => $estrategiaId,
        ]);

        foreach (['expedientes.index', 'expedientes.show'] as $route) {
            $response = $this->actingAs($user)
                ->get(route($route, $route === 'expedientes.show' ? $expediente : []));

            $response->assertOk()->assertSeeText($expediente->no_control);
            $this->assertNull($this->deleteForm($response, $expediente));
        }

        $this->delete(route('expedientes.destroy', $expediente))->assertForbidden();
        $this->assertDatabaseHas('expedientes', ['id' => $expediente->id]);
    }

    public function test_unapproved_paps_cannot_delete_expedientes(): void
    {
        $user = User::factory()->create(['approved_at' => null]);
        $user->assignRole('paps');
        $expediente = Expediente::factory()->create();

        $this->actingAs($user)
            ->delete(route('expedientes.destroy', $expediente))
            ->assertForbidden();

        $this->assertDatabaseHas('expedientes', ['id' => $expediente->id]);
    }

    public function test_guest_cannot_delete_expedientes(): void
    {
        $expediente = Expediente::factory()->create();

        $this->delete(route('expedientes.destroy', $expediente))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('expedientes', ['id' => $expediente->id]);
    }

    private function deleteForm(TestResponse $response, Expediente $expediente): ?DOMElement
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());

        return (new DOMXPath($document))->query(sprintf(
            '//form[@action="%s"][input[@name="_method" and @value="DELETE"]]',
            route('expedientes.destroy', $expediente)
        ))->item(0);
    }
}
