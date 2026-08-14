<?php

namespace App\Notifications;

use App\Models\Sesion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

class SesionValidatedNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    private ?int $sesionId = null;

    private ?int $expedienteId = null;

    // Compatibility fields for queued notifications created before this release.
    private ?Sesion $sesion = null;

    private ?User $actor = null;

    private ?string $observaciones = null;

    public function __construct(Sesion $sesion)
    {
        $this->sesionId = (int) $sesion->getKey();
        $this->expedienteId = (int) $sesion->expediente_id;
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
            ->subject('Sesión validada correctamente')
            ->view('emails.sesion-validated', [
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
            'sesion_id' => $this->resolvedSesionId(),
            'message' => 'Una sesión fue validada.',
        ];
    }

    private function canReceive(object $notifiable): bool
    {
        $sesion = $this->currentSesion();

        return $notifiable instanceof User
            && $notifiable->is_active
            && $sesion instanceof Sesion
            && Gate::forUser($notifiable)->allows('view', $sesion);
    }

    private function currentSesion(): ?Sesion
    {
        $id = $this->resolvedSesionId();

        return $id ? Sesion::query()->with('expediente')->find($id) : null;
    }

    private function resolvedSesionId(): ?int
    {
        return $this->sesionId ?? $this->sesion?->getKey();
    }

    private function resolvedExpedienteId(): ?int
    {
        return $this->expedienteId ?? $this->sesion?->expediente_id;
    }

    private function actionUrl(): string
    {
        $expedienteId = $this->resolvedExpedienteId();
        $sesionId = $this->resolvedSesionId();

        return $expedienteId && $sesionId
            ? route('expedientes.sesiones.show', [$expedienteId, $sesionId])
            : route('dashboard');
    }
}
