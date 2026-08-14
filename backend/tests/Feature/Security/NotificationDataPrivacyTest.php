<?php

namespace Tests\Feature\Security;

use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use App\Notifications\ExpedienteClosedNotification;
use App\Notifications\ExpedienteClosureAttemptNotification;
use App\Notifications\SesionObservedNotification;
use App\Notifications\SesionValidatedNotification;
use App\Notifications\TutorAssignedNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationDataPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_payloads_and_serialized_jobs_exclude_clinical_data(): void
    {
        $recipient = User::factory()->create();
        $expediente = Expediente::factory()->create([
            'no_control' => 'PRIVATE-CONTROL-MARKER',
            'paciente' => 'PRIVATE-STUDENT-MARKER',
            'observaciones_relevantes' => 'PRIVATE-CLINICAL-MARKER',
        ]);
        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $recipient->id,
            'nota' => 'PRIVATE-SESSION-MARKER',
        ]);
        $notifications = [
            new TutorAssignedNotification($expediente),
            new SesionObservedNotification($sesion),
            new SesionValidatedNotification($sesion),
            new ExpedienteClosureAttemptNotification($expediente),
            new ExpedienteClosedNotification($expediente),
        ];
        $markers = [
            'PRIVATE-CONTROL-MARKER',
            'PRIVATE-STUDENT-MARKER',
            'PRIVATE-CLINICAL-MARKER',
            'PRIVATE-SESSION-MARKER',
        ];

        foreach ($notifications as $notification) {
            $this->assertInstanceOf(ShouldBeEncrypted::class, $notification);
            $this->assertTrue((new SendQueuedNotifications($recipient, $notification))->shouldBeEncrypted);

            $storedPayload = json_encode($notification->toArray($recipient), JSON_THROW_ON_ERROR);
            $queuedPayload = serialize($notification);

            foreach ($markers as $marker) {
                $this->assertStringNotContainsString($marker, $storedPayload);
                $this->assertStringNotContainsString($marker, $queuedPayload);
            }
        }
    }

    public function test_migration_sanitizes_existing_notifications_and_removes_legacy_jobs(): void
    {
        $notificationId = (string) Str::uuid();
        $unrelatedNotificationId = (string) Str::uuid();
        $legacyPayload = json_encode([
            'expediente_id' => 41,
            'sesion_id' => 73,
            'expediente_no_control' => 'PRIVATE-CONTROL-MARKER',
            'paciente' => 'PRIVATE-STUDENT-MARKER',
            'actor_name' => 'PRIVATE-ACTOR-MARKER',
            'observaciones' => 'PRIVATE-CLINICAL-MARKER',
            'message' => 'Mensaje con datos privados',
        ], JSON_THROW_ON_ERROR);

        DB::table('notifications')->insert([
            [
                'id' => $notificationId,
                'type' => SesionObservedNotification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => 1,
                'data' => $legacyPayload,
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $unrelatedNotificationId,
                'type' => 'App\\Notifications\\UnrelatedNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => 1,
                'data' => '{"preserve":"value"}',
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $legacyJobId = DB::table('jobs')->insertGetId($this->jobPayload(SesionObservedNotification::class));
        $unrelatedJobId = DB::table('jobs')->insertGetId($this->jobPayload('App\\Jobs\\UnrelatedJob'));
        DB::table('failed_jobs')->insert([
            'uuid' => 'legacy-sensitive-notification',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => ExpedienteClosedNotification::class], JSON_THROW_ON_ERROR),
            'exception' => 'legacy notification failed',
            'failed_at' => now(),
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => 'unrelated-failed-job',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\UnrelatedJob'], JSON_THROW_ON_ERROR),
            'exception' => 'unrelated job failed',
            'failed_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_08_14_000001_minimize_sensitive_notification_data.php');
        $migration->up();

        $data = json_decode((string) DB::table('notifications')->where('id', $notificationId)->value('data'), true);
        $this->assertSame([
            'expediente_id' => 41,
            'message' => 'Una sesión fue observada y requiere tu atención.',
            'sesion_id' => 73,
        ], $data);
        $this->assertSame(
            '{"preserve":"value"}',
            DB::table('notifications')->where('id', $unrelatedNotificationId)->value('data')
        );
        $this->assertDatabaseMissing('jobs', ['id' => $legacyJobId]);
        $this->assertDatabaseHas('jobs', ['id' => $unrelatedJobId]);
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => 'legacy-sensitive-notification']);
        $this->assertDatabaseHas('failed_jobs', ['uuid' => 'unrelated-failed-job']);
    }

    /** @return array<string, mixed> */
    private function jobPayload(string $displayName): array
    {
        $timestamp = now()->getTimestamp();

        return [
            'queue' => 'default',
            'payload' => json_encode(['displayName' => $displayName], JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $timestamp,
            'created_at' => $timestamp,
        ];
    }
}
