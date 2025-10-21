<?php

namespace Tests\Feature\Comentarios;

use App\Models\Comentario;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\TimelineEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ComentarioApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::create(['name' => 'alumno']);
        Role::create(['name' => 'docente']);
        Role::create(['name' => 'admin']);

        Permission::create(['name' => 'expedientes.manage']);
        Permission::create(['name' => 'expedientes.view']);
    }

    public function test_usuario_puede_crear_comentario_en_expediente(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'creado_por' => $usuario->id,
        ]);

        $payload = [
            'comentable_type' => 'expediente',
            'comentable_id' => $expediente->id,
            'contenido' => 'Comentario de prueba',
        ];

        $response = $this->actingAs($usuario)->postJson(route('api.comentarios.store'), $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('comentarios', [
            'user_id' => $usuario->id,
            'comentable_type' => Expediente::class,
            'comentable_id' => $expediente->id,
            'contenido' => 'Comentario de prueba',
        ]);

        $this->assertSame('Comentario de prueba', $response->json('data.contenido'));
        $this->assertSame($usuario->id, $response->json('data.autor.id'));

        $evento = TimelineEvento::query()
            ->where('expediente_id', $expediente->id)
            ->where('evento', 'comentario.creado')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($evento);
        $this->assertSame($payload['comentable_type'], strtolower(class_basename($evento->payload['comentable_type'])));
        $this->assertSame($expediente->id, $evento->payload['comentable_id']);
        $this->assertSame($response->json('data.id'), $evento->payload['comentario_id']);
    }

    public function test_usuario_puede_listar_comentarios_de_sesion(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole('docente');

        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'tutor_id' => $docente->id,
            'creado_por' => $alumno->id,
        ]);

        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $alumno->id,
        ]);

        Comentario::factory()
            ->for($sesion, 'comentable')
            ->for($docente, 'autor')
            ->create(['contenido' => 'Seguimiento del caso']);

        Comentario::factory()
            ->for($sesion, 'comentable')
            ->for($alumno, 'autor')
            ->create(['contenido' => 'Respuesta del alumno']);

        $response = $this->actingAs($docente)->getJson(route('api.comentarios.index', [
            'comentable_type' => 'sesion',
            'comentable_id' => $sesion->id,
        ]));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $this->assertSame('Seguimiento del caso', $response->json('data.0.contenido'));
        $this->assertSame($docente->id, $response->json('data.0.autor.id'));
    }
}
