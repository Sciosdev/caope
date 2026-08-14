<?php

namespace Tests\Feature\Errors;

use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ForbiddenPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);

        Route::middleware('web')->get('/__testing/forbidden-page', function (): never {
            abort(403, 'Expediente confidencial CAOPE-123');
        });
    }

    public function test_forbidden_response_uses_friendly_page_without_exposing_internal_message(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('alumno');

        $response = $this->actingAs($user)->get('/__testing/forbidden-page');

        $response->assertForbidden();
        $response->assertSee('Acceso restringido');
        $response->assertSee('No tienes acceso a esta sección');
        $response->assertSee('Tu perfil no cuenta con el permiso necesario');
        $response->assertDontSee('Expediente confidencial CAOPE-123');
        $response->assertDontSee('Error 403');
    }

    public function test_foreign_expediente_uses_contextual_message_and_safe_navigation(): void
    {
        $this->seed(RoleSeeder::class);

        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');
        $otroUsuario = User::factory()->create();
        $expedienteAjeno = Expediente::factory()->create([
            'creado_por' => $otroUsuario->id,
        ]);

        $response = $this->actingAs($facilitador)->get(route('expedientes.show', $expedienteAjeno));

        $response->assertForbidden();
        $response->assertSee('Este expediente no está disponible para tu perfil');
        $response->assertSee('Solo puedes consultar expedientes que estén asignados o vinculados a tu cuenta.');
        $response->assertSee('href="'.route('expedientes.index').'"', false);
        $response->assertSee('Volver a mis expedientes');
        $response->assertSee('href="'.route('dashboard').'"', false);
        $response->assertSee('Ir al inicio');
        $response->assertDontSee('This action is unauthorized.');
    }

    public function test_forbidden_page_does_not_offer_expedientes_to_user_without_access(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/__testing/forbidden-page');

        $response->assertForbidden();
        $response->assertDontSee('href="'.route('expedientes.index').'"', false);
        $response->assertDontSee('Volver a mis expedientes');
        $response->assertSee('href="'.route('dashboard').'"', false);
        $response->assertSee('Ir al inicio');
    }

    public function test_json_forbidden_response_remains_json(): void
    {
        $this->seed(RoleSeeder::class);

        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');
        $otroUsuario = User::factory()->create();
        $expedienteAjeno = Expediente::factory()->create([
            'creado_por' => $otroUsuario->id,
        ]);

        $response = $this->actingAs($facilitador)->getJson(route('expedientes.show', $expedienteAjeno));

        $response->assertForbidden();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonStructure(['message']);
        $response->assertDontSee('Acceso restringido');
        $response->assertDontSee('Volver a mis expedientes');
    }

    public function test_foreign_expediente_is_not_linked_from_facilitator_index(): void
    {
        $this->seed(RoleSeeder::class);

        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');
        $otroUsuario = User::factory()->create();
        $propio = Expediente::factory()->create([
            'no_control' => 'PROPIO-001',
            'creado_por' => $facilitador->id,
        ]);
        $ajeno = Expediente::factory()->create([
            'no_control' => 'AJENO-999',
            'creado_por' => $otroUsuario->id,
        ]);

        $response = $this->actingAs($facilitador)->get(route('expedientes.index'));

        $response->assertOk();
        $response->assertSee('PROPIO-001');
        $response->assertSee('href="'.route('expedientes.show', $propio).'"', false);
        $response->assertDontSee('AJENO-999');
        $response->assertDontSee('href="'.route('expedientes.show', $ajeno).'"', false);
    }
}
