<?php

namespace App\Notifications;

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpedienteClosedNotification extends Notification
{
    public function __construct(
        private readonly Expediente $expediente,
        private readonly ?User $actor,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Expediente cerrado')
            ->view('emails.expediente-closed', [
                'expediente' => $this->expediente,
                'actor' => $this->actor,
                'destinatario' => $notifiable instanceof User ? $notifiable : null,
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
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
            'message' => sprintf(
                'El expediente %s (%s) fue cerrado.',
                $this->expediente->no_control,
                $this->expediente->paciente,
            ),
        ];
    }
}
