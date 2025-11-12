<?php

namespace Tests\Feature;

use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use App\Notifications\ExpedienteClosedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'expedientes.manage']);
    }

    public function test_cerrar_expediente_redirige_con_mensaje_de_exito(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $actor->givePermissionTo('expedientes.manage');

        $creador = User::factory()->create();
        $tutor = User::factory()->create();
        $coordinador = User::factory()->create();

        $expediente = Expediente::factory()->create([
            'estado' => 'revision',
            'creado_por' => $creador->id,
            'tutor_id' => $tutor->id,
            'coordinador_id' => $coordinador->id,
        ]);

        Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'status_revision' => 'validada',
            'validada_por' => User::factory()->create()->id,
        ]);

        Consentimiento::factory()->create([
            'expediente_id' => $expediente->id,
            'requerido' => true,
            'aceptado' => true,
            'archivo_path' => 'consentimientos/archivo.pdf',
        ]);

        $response = $this->actingAs($actor)->post(route('expedientes.change-state', $expediente), [
            'estado' => 'cerrado',
        ]);

        $response
            ->assertRedirect(route('expedientes.show', $expediente))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Estado del expediente actualizado correctamente.');

        $this->assertSame('cerrado', $expediente->fresh()->estado);

        foreach ([$tutor, $creador, $coordinador] as $destinatario) {
            Notification::assertSentTo($destinatario, ExpedienteClosedNotification::class);
        }
    }
}
