<?php

namespace Tests\Feature\Security;

use App\Casts\SafeDate;
use App\Models\Expediente;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SensitiveDataLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_removes_only_expired_runtime_data(): void
    {
        Carbon::setTestNow('2026-08-14 12:00:00');
        Storage::fake('local');
        Storage::fake('private');
        $legacyLogPath = storage_path('logs/backup-restore-tests.log');
        $legacyLogContents = File::exists($legacyLogPath) ? File::get($legacyLogPath) : null;

        try {
            $this->seedExportFiles();
            $this->seedBackupRestoreArtifacts();
            $notificationIds = $this->seedNotifications();
            $this->seedPasswordResets();
            $this->seedFailedJobs();
            $this->seedJobBatches();
            $this->seedSessions();
            $this->seedCacheEntries();

            $this->artisan('caope:purge-sensitive-data')
                ->expectsOutput('exports: 1')
                ->expectsOutput('restore_artifacts: 2')
                ->expectsOutput('notifications: 2')
                ->expectsOutput('password_resets: 2')
                ->expectsOutput('failed_jobs: 1')
                ->expectsOutput('job_batches: 1')
                ->expectsOutput('sessions: 1')
                ->expectsOutput('cache: 2')
                ->assertSuccessful();

            Storage::disk('local')->assertMissing('exports/expired.xlsx');
            Storage::disk('local')->assertExists('exports/current.xlsx');
            Storage::disk('private')->assertMissing('backup-restore-tests/expired.json');
            Storage::disk('private')->assertExists('backup-restore-tests/current.json');
            $this->assertFileDoesNotExist(storage_path('logs/backup-restore-tests.log'));

            $this->assertDatabaseMissing('notifications', ['id' => $notificationIds['old_read']]);
            $this->assertDatabaseMissing('notifications', ['id' => $notificationIds['old_unread']]);
            $this->assertDatabaseHas('notifications', ['id' => $notificationIds['recent_read']]);
            $this->assertDatabaseHas('notifications', ['id' => $notificationIds['recent_unread']]);

            $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'expired@example.test']);
            $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'missing-date@example.test']);
            $this->assertDatabaseHas('password_reset_tokens', ['email' => 'current@example.test']);
            $this->assertDatabaseMissing('failed_jobs', ['uuid' => 'expired-job']);
            $this->assertDatabaseHas('failed_jobs', ['uuid' => 'current-job']);
            $this->assertDatabaseMissing('job_batches', ['id' => 'expired-batch']);
            $this->assertDatabaseHas('job_batches', ['id' => 'current-batch']);
            $this->assertDatabaseMissing('sessions', ['id' => 'expired-session']);
            $this->assertDatabaseHas('sessions', ['id' => 'current-session']);
            $this->assertDatabaseMissing('cache', ['key' => 'expired-cache']);
            $this->assertDatabaseHas('cache', ['key' => 'current-cache']);
            $this->assertDatabaseMissing('cache_locks', ['key' => 'expired-lock']);
            $this->assertDatabaseHas('cache_locks', ['key' => 'current-lock']);
        } finally {
            Carbon::setTestNow();

            if ($legacyLogContents === null) {
                File::delete($legacyLogPath);
            } else {
                File::put($legacyLogPath, $legacyLogContents);
            }
        }
    }

    public function test_sensitive_runtime_purge_is_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())->first(
            fn ($event): bool => is_string($event->command)
                && Str::contains($event->command, 'caope:purge-sensitive-data')
        );

        $this->assertNotNull($event);
        $this->assertSame('30 1 * * *', $event->expression);
    }

    public function test_invalid_date_logs_metadata_without_the_submitted_value(): void
    {
        $sensitiveValue = '1999-99-99-sensitive-student-marker';
        Log::spy();

        $result = (new SafeDate)->get(
            new Expediente,
            'fecha_nacimiento',
            $sensitiveValue,
            []
        );

        $this->assertNull($result);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($sensitiveValue): bool {
                return $message === 'Failed to parse date attribute'
                    && ($context['attribute'] ?? null) === 'fecha_nacimiento'
                    && ($context['value_type'] ?? null) === 'string'
                    && ! Str::contains(json_encode($context), $sensitiveValue);
            });
    }

    private function seedExportFiles(): void
    {
        $disk = Storage::disk('local');
        $disk->put('exports/expired.xlsx', 'expired clinical export');
        $disk->put('exports/current.xlsx', 'current clinical export');
        touch($disk->path('exports/expired.xlsx'), now()->subHours(3)->getTimestamp());
        touch($disk->path('exports/current.xlsx'), now()->subMinutes(30)->getTimestamp());
    }

    private function seedBackupRestoreArtifacts(): void
    {
        $disk = Storage::disk('private');
        $disk->put('backup-restore-tests/expired.json', '{}');
        $disk->put('backup-restore-tests/current.json', '{}');
        touch($disk->path('backup-restore-tests/expired.json'), now()->subDays(31)->getTimestamp());
        touch($disk->path('backup-restore-tests/current.json'), now()->subDay()->getTimestamp());

        File::ensureDirectoryExists(storage_path('logs'));
        File::put(storage_path('logs/backup-restore-tests.log'), 'legacy duplicate log');
    }

    /** @return array<string, string> */
    private function seedNotifications(): array
    {
        $ids = [
            'old_read' => (string) Str::uuid(),
            'old_unread' => (string) Str::uuid(),
            'recent_read' => (string) Str::uuid(),
            'recent_unread' => (string) Str::uuid(),
        ];

        foreach ([
            [$ids['old_read'], now()->subDays(31), now()->subDays(31)],
            [$ids['old_unread'], now()->subDays(91), null],
            [$ids['recent_read'], now()->subDays(2), now()->subDay()],
            [$ids['recent_unread'], now()->subDays(2), null],
        ] as [$id, $createdAt, $readAt]) {
            DB::table('notifications')->insert([
                'id' => $id,
                'type' => 'SecurityTestNotification',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => 1,
                'data' => '{}',
                'read_at' => $readAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        return $ids;
    }

    private function seedPasswordResets(): void
    {
        DB::table('password_reset_tokens')->insert([
            ['email' => 'expired@example.test', 'token' => 'expired', 'created_at' => now()->subHours(3)],
            ['email' => 'missing-date@example.test', 'token' => 'missing-date', 'created_at' => null],
            ['email' => 'current@example.test', 'token' => 'current', 'created_at' => now()->subMinutes(30)],
        ]);
    }

    private function seedFailedJobs(): void
    {
        DB::table('failed_jobs')->insert([
            [
                'uuid' => 'expired-job',
                'connection' => 'database',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'expired',
                'failed_at' => now()->subDays(8),
            ],
            [
                'uuid' => 'current-job',
                'connection' => 'database',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'current',
                'failed_at' => now()->subDay(),
            ],
        ]);
    }

    private function seedJobBatches(): void
    {
        foreach ([
            ['expired-batch', now()->subDays(8)->getTimestamp()],
            ['current-batch', now()->subDay()->getTimestamp()],
        ] as [$id, $createdAt]) {
            DB::table('job_batches')->insert([
                'id' => $id,
                'name' => $id,
                'total_jobs' => 1,
                'pending_jobs' => 0,
                'failed_jobs' => 0,
                'failed_job_ids' => '[]',
                'options' => null,
                'cancelled_at' => null,
                'created_at' => $createdAt,
                'finished_at' => $createdAt,
            ]);
        }
    }

    private function seedSessions(): void
    {
        DB::table('sessions')->insert([
            [
                'id' => 'expired-session',
                'user_id' => null,
                'ip_address' => null,
                'user_agent' => null,
                'payload' => '',
                'last_activity' => now()->subDays(2)->getTimestamp(),
            ],
            [
                'id' => 'current-session',
                'user_id' => null,
                'ip_address' => null,
                'user_agent' => null,
                'payload' => '',
                'last_activity' => now()->subHour()->getTimestamp(),
            ],
        ]);
    }

    private function seedCacheEntries(): void
    {
        DB::table('cache')->insert([
            ['key' => 'expired-cache', 'value' => 'expired', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => 'current-cache', 'value' => 'current', 'expiration' => now()->addHour()->getTimestamp()],
        ]);
        DB::table('cache_locks')->insert([
            ['key' => 'expired-lock', 'owner' => 'expired', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => 'current-lock', 'owner' => 'current', 'expiration' => now()->addHour()->getTimestamp()],
        ]);
    }
}
