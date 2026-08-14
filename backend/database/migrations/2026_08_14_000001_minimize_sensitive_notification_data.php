<?php

use App\Notifications\ExpedienteClosedNotification;
use App\Notifications\ExpedienteClosureAttemptNotification;
use App\Notifications\SesionObservedNotification;
use App\Notifications\SesionValidatedNotification;
use App\Notifications\TutorAssignedNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<class-string, string> */
    private const MESSAGES = [
        TutorAssignedNotification::class => 'Se te asignó un expediente.',
        SesionObservedNotification::class => 'Una sesión fue observada y requiere tu atención.',
        SesionValidatedNotification::class => 'Una sesión fue validada.',
        ExpedienteClosureAttemptNotification::class => 'Un expediente no pudo cerrarse y requiere revisión.',
        ExpedienteClosedNotification::class => 'Un expediente fue cerrado.',
    ];

    public function up(): void
    {
        $this->sanitizeStoredNotifications();
        $this->removeLegacyQueuedNotifications();
    }

    public function down(): void
    {
        // Sensitive payloads cannot and must not be restored.
    }

    private function sanitizeStoredNotifications(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        DB::table('notifications')
            ->whereIn('type', array_keys(self::MESSAGES))
            ->orderBy('id')
            ->chunk(500, function ($notifications): void {
                foreach ($notifications as $notification) {
                    $data = json_decode((string) $notification->data, true);
                    $data = is_array($data) ? $data : [];
                    $minimal = [
                        'expediente_id' => $this->positiveInteger($data['expediente_id'] ?? null),
                        'message' => self::MESSAGES[$notification->type],
                    ];

                    if (in_array($notification->type, [
                        SesionObservedNotification::class,
                        SesionValidatedNotification::class,
                    ], true)) {
                        $minimal['sesion_id'] = $this->positiveInteger($data['sesion_id'] ?? null);
                    }

                    DB::table('notifications')
                        ->where('id', $notification->id)
                        ->update(['data' => json_encode($minimal, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                }
            });
    }

    private function removeLegacyQueuedNotifications(): void
    {
        foreach (['jobs', 'failed_jobs'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->select(['id', 'payload'])
                ->orderBy('id')
                ->chunkById(500, function ($jobs) use ($table): void {
                    foreach ($jobs as $job) {
                        if ($this->containsSensitiveNotification((string) $job->payload)) {
                            DB::table($table)->where('id', $job->id)->delete();
                        }
                    }
                });
        }
    }

    private function containsSensitiveNotification(string $payload): bool
    {
        $normalized = str_replace('\\\\', '\\', $payload);

        foreach (array_keys(self::MESSAGES) as $notificationClass) {
            if (str_contains($normalized, $notificationClass)) {
                return true;
            }
        }

        return false;
    }

    private function positiveInteger(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
};
