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
use App\Notifications\SesionValidatedNotification;
use App\Notifications\TutorAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $manage = Permission::firstOrCreate(['name' => 'expedientes.manage']);
        $view = Permission::firstOrCreate(['name' => 'expedientes.view']);

        Role::firstOrCreate(['name' => 'alumno'])->givePermissionTo($view);
        Role::firstOrCreate(['name' => 'docente'])->givePermissionTo($view);
        Role::firstOrCreate(['name' => 'coordinador'])->givePermissionTo([$manage, $view]);
        Role::firstOrCreate(['name' => 'admin'])->givePermissionTo([$manage, $view]);

        $this->createCatalogs();
    }

    public function test_assigning_tutor_creates_database_notification(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $tutor = User::factory()->create();
        $tutor->assignRole('docente');

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

        $creador->assignRole('alumno');
        $realizadaPor->assignRole('admin');
        $tutor->assignRole('docente');
        $coordinador->assignRole('coordinador');

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

        foreach ([$realizadaPor, $tutor, $coordinador] as $destinatario) {
            $this->assertDatabaseHas('notifications', [
                'notifiable_id' => $destinatario->id,
                'type' => SesionObservedNotification::class,
            ]);
        }

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $creador->id,
            'type' => SesionObservedNotification::class,
        ]);
    }

    public function test_validating_session_notifies_only_authorized_participants(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo('expedientes.manage');

        $creador = User::factory()->create();
        $realizadaPor = User::factory()->create();
        $tutor = User::factory()->create();
        $coordinador = User::factory()->create();

        $creador->assignRole('alumno');
        $realizadaPor->assignRole('admin');
        $tutor->assignRole('docente');
        $coordinador->assignRole('coordinador');

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
            'validada_por' => null,
        ]);

        $this->actingAs($actor)
            ->post(route('expedientes.sesiones.validate', [$expediente, $sesion]), [
                'observaciones' => 'Validación completada.',
            ])
            ->assertRedirect();

        foreach ([$realizadaPor, $tutor, $coordinador] as $recipient) {
            $this->assertDatabaseHas('notifications', [
                'notifiable_id' => $recipient->id,
                'type' => SesionValidatedNotification::class,
            ]);
        }

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $creador->id,
            'type' => SesionValidatedNotification::class,
        ]);
    }

    public function test_failed_closure_attempt_notifies_contacts(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('admin');

        $creador = User::factory()->create();
        $tutor = User::factory()->create();
        $coordinador = User::factory()->create();

        $creador->assignRole('alumno');
        $tutor->assignRole('docente');
        $coordinador->assignRole('coordinador');

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
        $actor->assignRole('admin');

        $creador = User::factory()->create();
        $tutor = User::factory()->create();
        $coordinador = User::factory()->create();

        $creador->assignRole('alumno');
        $tutor->assignRole('docente');
        $coordinador->assignRole('coordinador');

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

    public function test_queued_tutor_assignment_is_revoked_after_reassignment_or_deactivation(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('admin');

        $tutor = User::factory()->create();
        $tutor->assignRole('docente');
        $otherTutor = User::factory()->create();

        $expediente = Expediente::factory()->create([
            'tutor_id' => $tutor->id,
        ]);
        $notification = new TutorAssignedNotification($expediente, $actor);

        $this->assertSame(['mail', 'database'], $notification->via($tutor));
        $this->assertTrue($notification->shouldSend($tutor, 'mail'));
        $this->assertTrue($notification->shouldSend($tutor, 'database'));

        $expediente->update(['tutor_id' => $otherTutor->id]);

        $this->assertSame([], $notification->via($tutor));
        $this->assertFalse($notification->shouldSend($tutor, 'mail'));
        $this->assertFalse($notification->shouldSend($tutor, 'database'));

        $expediente->update(['tutor_id' => $tutor->id]);
        $tutor->update(['is_active' => false]);

        $this->assertSame([], $notification->via($tutor));
        $this->assertFalse($notification->shouldSend($tutor, 'mail'));
        $this->assertFalse($notification->shouldSend($tutor, 'database'));
    }

    public function test_queued_closure_notifications_are_revoked_after_reassignment_or_deactivation(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('admin');

        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');
        $otherFacilitator = User::factory()->create();

        $expediente = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
        ]);
        $notifications = [
            new ExpedienteClosureAttemptNotification($expediente, $actor, ['Sesión pendiente.']),
            new ExpedienteClosedNotification($expediente, $actor),
        ];

        foreach ($notifications as $notification) {
            $this->assertSame(['mail', 'database'], $notification->via($facilitador));
            $this->assertTrue($notification->shouldSend($facilitador, 'mail'));
            $this->assertTrue($notification->shouldSend($facilitador, 'database'));
        }

        $expediente->update(['creado_por' => $otherFacilitator->id]);

        foreach ($notifications as $notification) {
            $this->assertSame([], $notification->via($facilitador));
            $this->assertFalse($notification->shouldSend($facilitador, 'mail'));
            $this->assertFalse($notification->shouldSend($facilitador, 'database'));
        }

        $expediente->update(['creado_por' => $facilitador->id]);
        $facilitador->update(['is_active' => false]);

        foreach ($notifications as $notification) {
            $this->assertSame([], $notification->via($facilitador));
            $this->assertFalse($notification->shouldSend($facilitador, 'mail'));
            $this->assertFalse($notification->shouldSend($facilitador, 'database'));
        }
    }

    private function createCatalogs(): void
    {
        CatalogoCarrera::firstOrCreate(['nombre' => 'Psicología'], ['activo' => true]);
        CatalogoTurno::firstOrCreate(['nombre' => 'Matutino'], ['activo' => true]);
    }
}
