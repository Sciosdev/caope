<?php

namespace App\Notifications;

use App\Models\Sesion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SesionValidatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Sesion $sesion,
        private readonly ?User $actor,
        private readonly ?string $observaciones,
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
            ->subject('Sesión validada correctamente')
            ->view('emails.sesion-validated', [
                'sesion' => $this->sesion,
                'actor' => $this->actor,
                'observaciones' => $this->observaciones,
                'destinatario' => $notifiable instanceof User ? $notifiable : null,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $expediente = $this->sesion->expediente;

        return [
            'expediente_id' => $expediente?->id,
            'sesion_id' => $this->sesion->id,
            'fecha' => $this->sesion->fecha?->toDateString(),
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
            'observaciones' => $this->observaciones,
            'message' => sprintf(
                'La sesión #%d fue validada.',
                $this->sesion->id,
            ),
        ];
    }
}
