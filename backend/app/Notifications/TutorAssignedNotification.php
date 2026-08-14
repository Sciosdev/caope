<?php

namespace App\Notifications;

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

class TutorAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Expediente $expediente,
        private readonly ?User $actor,
    ) {}

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
                'expediente' => $this->expediente,
                'actor' => $this->actor,
                'tutor' => $notifiable instanceof User ? $notifiable : null,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'expediente_id' => $this->expediente->id,
            'expediente_no_control' => $this->expediente->no_control,
            'paciente' => $this->expediente->paciente,
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
            'message' => sprintf(
                'Se te asignó el expediente %s (%s).',
                $this->expediente->no_control,
                $this->expediente->paciente,
            ),
        ];
    }

    private function canReceive(object $notifiable): bool
    {
        $expediente = $this->expediente->fresh();

        return $notifiable instanceof User
            && $notifiable->is_active
            && $expediente instanceof Expediente
            && (int) $expediente->tutor_id === (int) $notifiable->getKey()
            && Gate::forUser($notifiable)->allows('view', $expediente);
    }
}
