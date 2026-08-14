<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeSensitiveRuntimeData extends Command
{
    protected $signature = 'caope:purge-sensitive-data';

    protected $description = 'Elimina exportaciones y registros operativos sensibles que superaron su retención.';

    public function handle(): int
    {
        $counts = [
            'exports' => $this->purgeExports(),
            'restore_artifacts' => $this->purgeBackupRestoreArtifacts(),
            'notifications' => $this->purgeNotifications(),
            'password_resets' => $this->purgePasswordResets(),
            'failed_jobs' => $this->purgeFailedJobs(),
            'job_batches' => $this->purgeJobBatches(),
            'sessions' => $this->purgeSessions(),
            'cache' => $this->purgeExpiredCache(),
        ];

        foreach ($counts as $type => $count) {
            $this->line(sprintf('%s: %d', $type, $count));
        }

        return self::SUCCESS;
    }

    private function purgeExports(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subHours($this->retention('exports_hours', 2))->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles('exports') as $path) {
            try {
                if ($disk->lastModified($path) < $cutoff && $disk->delete($path)) {
                    $deleted++;
                }
            } catch (Throwable) {
                // A concurrent download or export may remove the file first.
            }
        }

        return $deleted;
    }

    private function purgeNotifications(): int
    {
        if (! Schema::hasTable('notifications')) {
            return 0;
        }

        $read = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($this->retention('read_notifications_days', 30)))
            ->delete();

        $expired = DB::table('notifications')
            ->where('created_at', '<', now()->subDays($this->retention('all_notifications_days', 90)))
            ->delete();

        return $read + $expired;
    }

    private function purgePasswordResets(): int
    {
        if (! Schema::hasTable('password_reset_tokens')) {
            return 0;
        }

        return DB::table('password_reset_tokens')
            ->where(function ($query): void {
                $query->whereNull('created_at')
                    ->orWhere('created_at', '<', now()->subHours($this->retention('password_reset_hours', 2)));
            })
            ->delete();
    }

    private function purgeBackupRestoreArtifacts(): int
    {
        $disk = Storage::disk((string) config('filesystems.private_default', 'private'));
        $cutoff = now()->subDays($this->retention('backup_restore_artifacts_days', 30))->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles('backup-restore-tests') as $path) {
            try {
                if ($disk->lastModified($path) < $cutoff && $disk->delete($path)) {
                    $deleted++;
                }
            } catch (Throwable) {
                // Another maintenance process may remove the artefact first.
            }
        }

        if (File::delete(storage_path('logs/backup-restore-tests.log'))) {
            $deleted++;
        }

        return $deleted;
    }

    private function purgeFailedJobs(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return DB::table('failed_jobs')
            ->where('failed_at', '<', now()->subDays($this->retention('failed_jobs_days', 7)))
            ->delete();
    }

    private function purgeJobBatches(): int
    {
        if (! Schema::hasTable('job_batches')) {
            return 0;
        }

        return DB::table('job_batches')
            ->where('created_at', '<', now()->subDays($this->retention('job_batches_days', 7))->getTimestamp())
            ->delete();
    }

    private function purgeSessions(): int
    {
        $table = (string) config('session.table', 'sessions');

        if ($table === '' || ! Schema::hasTable($table)) {
            return 0;
        }

        $graceMinutes = $this->retention('session_grace_minutes', 1440);
        $cutoff = now()->subMinutes((int) config('session.lifetime', 120) + $graceMinutes)->getTimestamp();

        return DB::table($table)->where('last_activity', '<', $cutoff)->delete();
    }

    private function purgeExpiredCache(): int
    {
        $deleted = 0;

        foreach (['cache', 'cache_locks'] as $table) {
            if (Schema::hasTable($table)) {
                $deleted += DB::table($table)->where('expiration', '<', now()->getTimestamp())->delete();
            }
        }

        return $deleted;
    }

    private function retention(string $key, int $default): int
    {
        return max(1, (int) config("security.retention.{$key}", $default));
    }
}
