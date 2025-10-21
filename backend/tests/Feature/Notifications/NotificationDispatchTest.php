<?php

namespace Tests\Feature\Notifications;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use App\Notifications\ExpedienteClosedNotification;
use App\Notifications\ExpedienteClosureAttemptNotification;
use App\Notifications\SesionObservedNotification;
use App\Notifications\TutorAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'expedientes.manage']);

        $this->createCatalogs();
    }

    public function test_assigning_tutor_creates_database_notification(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('expedientes.manage');

        $tutor = User::factory()->create();

        $expediente = Expediente::factory()->create([
            'tutor_id' => null,
            'creado_por' => $admin->id,
            'carrera' => 'Psicología',
            'turno' => 'Matutino',
            'estado' => 'abierto',
        ]);

        $payload = [
            'no_control' => $expediente->no_control,
            'paciente' => $expediente->paciente,
            'apertura' => $expediente->apertura->format('Y-m-d'),
            'estado' => $expediente->estado,
            'carrera' => 'Psicología',
            'turno' => 'Matutino',
            'tutor_id' => $tutor->id,
            'coordinador_id' => $expediente->coordinador_id,
        ];

        $response = $this->actingAs($admin)->put(route('expedientes.update', $expediente), $payload);

        $response
            ->assertSessionHas('status')
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $tutor->id,
            'type' => TutorAssignedNotification::class,
        ]);
    }

    public function test_observing_session_stores_notifications_for_participants(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo('expedientes.manage');

        $creador = User::factory()->create();
        $tutor = User::factory()->create();
        $coordinador = User::factory()->create();
        $realizadaPor = User::factory()->create();

        $expediente = Expediente::factory()->create([
            'estado' => 'revision',
            'creado_por' => $creador->id,
            'tutor_id' => $tutor->id,
            'coordinador_id' => $coordinador->id,
            'carrera' => 'Psicología',
            'turno' => 'Matutino',
        ]);

        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'status_revision' => 'pendiente',
            'realizada_por' => $realizadaPor->id,
            'fecha' => Carbon::now(),
        ]);

        $response = $this->actingAs($actor)->post(
            route('expedientes.sesiones.observe', [$expediente, $sesion]),
            ['observaciones' => 'Faltan anexos firmados.'],
        );

        $response
            ->assertSessionHas('status')
            ->assertRedirect();

        foreach ([$realizadaPor, $tutor, $creador, $coordinador] as $destinatario) {
            $this->assertDatabaseHas('notifications', [
                'notifiable_id' => $destinatario->id,
                'type' => SesionObservedNotification::class,
            ]);
        }
    }

    public function test_failed_closure_attempt_notifies_contacts(): void
    {
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
            'carrera' => 'Psicología',
            'turno' => 'Matutino',
        ]);

        Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'status_revision' => 'pendiente',
            'realizada_por' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($actor)->post(route('expedientes.change-state', $expediente), [
            'estado' => 'cerrado',
        ]);

        $response->assertSessionHasErrors('estado');

        foreach ([$tutor, $creador, $coordinador] as $destinatario) {
            $this->assertDatabaseHas('notifications', [
                'notifiable_id' => $destinatario->id,
                'type' => ExpedienteClosureAttemptNotification::class,
            ]);
        }
    }

    public function test_successful_closure_notifies_contacts(): void
    {
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
            'carrera' => 'Psicología',
            'turno' => 'Matutino',
        ]);

        $validator = User::factory()->create();

        Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'status_revision' => 'validada',
            'validada_por' => $validator->id,
        ]);

        Consentimiento::factory()->create([
            'expediente_id' => $expediente->id,
            'requerido' => true,
            'aceptado' => true,
            'archivo_path' => 'consentimientos/firma.pdf',
        ]);

        $response = $this->actingAs($actor)->post(route('expedientes.change-state', $expediente), [
            'estado' => 'cerrado',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        foreach ([$tutor, $creador, $coordinador] as $destinatario) {
            $this->assertDatabaseHas('notifications', [
                'notifiable_id' => $destinatario->id,
                'type' => ExpedienteClosedNotification::class,
            ]);
        }
    }

    private function createCatalogs(): void
    {
        CatalogoCarrera::firstOrCreate(['nombre' => 'Psicología'], ['activo' => true]);
        CatalogoTurno::firstOrCreate(['nombre' => 'Matutino'], ['activo' => true]);
    }
}
