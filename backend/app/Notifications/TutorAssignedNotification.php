<?php

namespace App\Notifications;

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

class TutorAssignedNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    private ?int $expedienteId = null;

    // Compatibility fields for queued notifications created before this release.
    private ?Expediente $expediente = null;

    private ?User $actor = null;

    public function __construct(Expediente $expediente)
    {
        $this->expedienteId = (int) $expediente->getKey();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->canReceive($notifiable) ? ['mail', 'database'] : [];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->canReceive($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nuevo expediente asignado')
            ->view('emails.tutor-assigned', [
                'actionUrl' => $this->actionUrl(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'expediente_id' => $this->resolvedExpedienteId(),
            'message' => 'Se te asignó un expediente.',
        ];
    }

    private function canReceive(object $notifiable): bool
    {
        $expediente = $this->currentExpediente();

        return $notifiable instanceof User
            && $notifiable->is_active
            && $expediente instanceof Expediente
            && (int) $expediente->tutor_id === (int) $notifiable->getKey()
            && Gate::forUser($notifiable)->allows('view', $expediente);
    }

    private function currentExpediente(): ?Expediente
    {
        $id = $this->resolvedExpedienteId();

        return $id ? Expediente::query()->find($id) : null;
    }

    private function resolvedExpedienteId(): ?int
    {
        return $this->expedienteId ?? $this->expediente?->getKey();
    }

    private function actionUrl(): string
    {
        $id = $this->resolvedExpedienteId();

        return $id ? route('expedientes.show', $id) : route('dashboard');
    }
}
