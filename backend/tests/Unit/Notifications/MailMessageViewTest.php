<?php

namespace Tests\Unit\Notifications;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use App\Notifications\ExpedienteClosureAttemptNotification;
use App\Notifications\SesionObservedNotification;
use App\Notifications\TutorAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MailMessageViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CatalogoCarrera::firstOrCreate(['nombre' => 'Psicología'], ['activo' => true]);
        CatalogoTurno::firstOrCreate(['nombre' => 'Matutino'], ['activo' => true]);
    }

    public function test_tutor_assignment_mail_uses_custom_view(): void
    {
        $expediente = Expediente::factory()->create([
            'carrera' => 'Psicología',
            'turno' => 'Matutino',
        ]);

        $actor = User::factory()->create();
        $tutor = User::factory()->create();

        $notification = new TutorAssignedNotification($expediente, $actor);

        $mail = $notification->toMail($tutor);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('emails.tutor-assigned', $mail->view);
        $this->assertArrayHasKey('expediente', $mail->viewData);
    }

    public function test_session_observed_mail_includes_view_and_observations(): void
    {
        $expediente = Expediente::factory()->create([
            'carrera' => 'Psicología',
            'turno' => 'Matutino',
        ]);

        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'status_revision' => 'pendiente',
            'realizada_por' => User::factory()->create()->id,
            'fecha' => Carbon::now(),
        ]);

        $actor = User::factory()->create();
        $destinatario = User::factory()->create();

        $notification = new SesionObservedNotification($sesion->fresh(), $actor, 'Agregar bitácora.');

        $mail = $notification->toMail($destinatario);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('emails.sesion-observed', $mail->view);
        $this->assertSame('Agregar bitácora.', $mail->viewData['observaciones']);
    }

    public function test_closure_attempt_mail_uses_view_and_error_list(): void
    {
        $expediente = Expediente::factory()->create([
            'carrera' => 'Psicología',
            'turno' => 'Matutino',
        ]);

        $actor = User::factory()->create();
        $destinatario = User::factory()->create();

        $notification = new ExpedienteClosureAttemptNotification(
            $expediente,
            $actor,
            ['Debe existir una sesión validada.']
        );

        $mail = $notification->toMail($destinatario);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('emails.expediente-closure-attempt', $mail->view);
        $this->assertEquals(['Debe existir una sesión validada.'], $mail->viewData['errores']);
    }
}
