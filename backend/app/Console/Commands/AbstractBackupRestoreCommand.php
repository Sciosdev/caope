<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestinationFactory;
use Spatie\Backup\Config\Config;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Throwable;
use ZipArchive;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

abstract class AbstractBackupRestoreCommand extends Command
{
    protected function latestBackup(): ?Backup
    {
        $config = Config::fromArray(config('backup'));

        return BackupDestinationFactory::createFromArray($config)
            ->map(fn ($destination) => $destination->newestBackup())
            ->filter()
            ->sortByDesc(fn (Backup $backup) => $backup->date()->timestamp)
            ->first();
    }

    /**
     * @return array{directory: TemporaryDirectory, extracted: string, zip: string}
     */
    protected function extractBackup(Backup $backup): array
    {
        $temporaryDirectory = (new TemporaryDirectory())->create();
        $zipPath = $temporaryDirectory->path('backup.zip');

        $stream = $backup->stream();
        $destination = fopen($zipPath, 'w+b');

        if ($destination === false) {
            throw new RuntimeException('Unable to create temporary archive.');
        }

        try {
            stream_copy_to_stream($stream, $destination);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            fclose($destination);
        }

        $extractedPath = $temporaryDirectory->path('extracted');
        if (! is_dir($extractedPath) && ! mkdir($extractedPath, 0755, true) && ! is_dir($extractedPath)) {
            throw new RuntimeException('Unable to prepare extraction directory.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open the backup archive.');
        }

        try {
            if (! $zip->extractTo($extractedPath)) {
                throw new RuntimeException('Unable to extract the backup archive.');
            }
        } finally {
            $zip->close();
        }

        return [
            'directory' => $temporaryDirectory,
            'extracted' => $extractedPath,
            'zip' => $zipPath,
        ];
    }

    protected function locateDumpFile(string $directory, callable $filter): ?string
    {
        if (! is_dir($directory)) {
            return null;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();

            if ($filter($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function artifactDiskName(): string
    {
        $disks = config('backup.backup.destination.disks', []);

        return $disks[0] ?? config('filesystems.private_default', 'private');
    }

    protected function logResult(string $type, string $status, array $context = []): void
    {
        $payload = array_merge([
            'type' => $type,
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
        ], $context);

        Log::channel(config('logging.default', 'stack'))->info(
            sprintf('[backup:%s] %s', $type, $status),
            $payload
        );

        $logDirectory = storage_path('logs');
        if (! is_dir($logDirectory)) {
            mkdir($logDirectory, 0755, true);
        }

        $logFile = $logDirectory.'/backup-restore-tests.log';
        file_put_contents($logFile, json_encode($payload, JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND);

        try {
            $disk = Storage::disk($this->artifactDiskName());
            $disk->makeDirectory('backup-restore-tests');

            $disk->put(
                sprintf(
                    'backup-restore-tests/%s-%s.json',
                    $type,
                    Str::of($payload['timestamp'])->replace([':', '.'], '')->__toString()
                ),
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        } catch (Throwable $exception) {
            Log::warning('Unable to persist backup restore artefact.', [
                'type' => $type,
                'status' => $status,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
