<?php

namespace App\Notifications;

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpedienteClosureAttemptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $errores
     */
    public function __construct(
        private readonly Expediente $expediente,
        private readonly ?User $actor,
        private readonly array $errores,
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
            ->subject('Intento de cierre de expediente con observaciones')
            ->view('emails.expediente-closure-attempt', [
                'expediente' => $this->expediente,
                'actor' => $this->actor,
                'errores' => $this->errores,
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
            'errores' => $this->errores,
            'message' => 'El expediente no pudo cerrarse debido a observaciones pendientes.',
        ];
    }
}
