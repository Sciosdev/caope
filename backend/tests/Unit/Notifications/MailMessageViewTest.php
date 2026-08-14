<?php

namespace Tests\Unit\Notifications;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use App\Notifications\ExpedienteClosedNotification;
use App\Notifications\ExpedienteClosureAttemptNotification;
use App\Notifications\SesionObservedNotification;
use App\Notifications\SesionValidatedNotification;
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

    public function test_notification_emails_use_generic_views_without_clinical_data(): void
    {
        $expediente = Expediente::factory()->create([
            'carrera' => 'Psicología',
            'turno' => 'Matutino',
            'no_control' => 'SECURITY-CONTROL-MARKER',
            'paciente' => 'SECURITY-STUDENT-MARKER',
            'observaciones_relevantes' => 'SECURITY-CLOSURE-ERROR-MARKER',
        ]);
        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'status_revision' => 'pendiente',
            'realizada_por' => User::factory()->create()->id,
            'fecha' => Carbon::now(),
            'nota' => 'SECURITY-OBSERVATION-MARKER',
        ]);
        $destinatario = User::factory()->create();
        $sensitiveMarkers = [
            'SECURITY-CONTROL-MARKER',
            'SECURITY-STUDENT-MARKER',
            'SECURITY-OBSERVATION-MARKER',
            'SECURITY-CLOSURE-ERROR-MARKER',
        ];
        $notifications = [
            [new TutorAssignedNotification($expediente), 'emails.tutor-assigned'],
            [new SesionObservedNotification($sesion), 'emails.sesion-observed'],
            [new SesionValidatedNotification($sesion), 'emails.sesion-validated'],
            [new ExpedienteClosureAttemptNotification($expediente), 'emails.expediente-closure-attempt'],
            [new ExpedienteClosedNotification($expediente), 'emails.expediente-closed'],
        ];

        foreach ($notifications as [$notification, $expectedView]) {
            $mail = $notification->toMail($destinatario);
            $html = view($mail->view, $mail->viewData)->render();

            $this->assertInstanceOf(MailMessage::class, $mail);
            $this->assertSame($expectedView, $mail->view);
            $this->assertSame(['actionUrl'], array_keys($mail->viewData));
            $this->assertStringContainsString('Por seguridad', $html);

            foreach ($sensitiveMarkers as $marker) {
                $this->assertStringNotContainsString($marker, $html);
                $this->assertStringNotContainsString($marker, serialize($notification));
            }
        }
    }
}
