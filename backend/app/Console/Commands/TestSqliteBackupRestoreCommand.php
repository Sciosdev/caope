<?php

namespace App\Console\Commands;

use Illuminate\Support\Str;
use RuntimeException;
use SQLite3;
use Throwable;

class TestSqliteBackupRestoreCommand extends AbstractBackupRestoreCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:test-restore-sqlite {--connection=sqlite : Connection name to validate.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a trial restoration of the latest SQLite backup archive.';

    public function handle(): int
    {
        if (! class_exists(SQLite3::class)) {
            $this->components->error('The SQLite3 extension is required to execute this command.');

            return self::FAILURE;
        }

        $connection = (string) $this->option('connection');

        $backup = $this->latestBackup();
        if (! $backup) {
            $this->components->error('No backup archives were found on the configured disks.');

            return self::FAILURE;
        }

        $this->components->info(sprintf('Using backup %s from disk %s', $backup->path(), $this->artifactDiskName()));

        $paths = $this->extractBackup($backup);

        try {
            $dumpDirectory = $paths['extracted'].'/db-dumps';

            $dumpPath = $this->locateDumpFile($dumpDirectory, function (string $path) use ($connection) {
                $basename = basename($path);

                if (! Str::contains($basename, $connection)) {
                    return false;
                }

                return Str::of($basename)->endsWith(['.sql', '.sqlite']);
            });

            if (! $dumpPath) {
                throw new RuntimeException('Unable to locate an SQLite dump inside the archive.');
            }

            $relativeDump = Str::after($dumpPath, $paths['extracted'].'/');
            $restoredPath = $paths['directory']->path('restored.sqlite');

            $integrity = $this->restoreSqliteDatabase($dumpPath, $restoredPath);

            $this->components->info('SQLite integrity check: '.$integrity);

            $tables = $this->countSqliteTables($restoredPath);

            $this->logResult('sqlite', 'passed', [
                'backup' => $backup->path(),
                'dump' => $relativeDump,
                'integrity' => $integrity,
                'tables' => $tables,
            ]);

            $this->components->info('SQLite backup restoration test completed successfully.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->logResult('sqlite', 'failed', [
                'backup' => $backup->path(),
                'error' => $exception->getMessage(),
            ]);

            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $paths['directory']->delete();
        }
    }

    protected function restoreSqliteDatabase(string $dumpPath, string $destination): string
    {
        if (Str::of($dumpPath)->lower()->endsWith('.sqlite')) {
            if (! copy($dumpPath, $destination)) {
                throw new RuntimeException('Unable to copy SQLite dump to the temporary location.');
            }
        } else {
            $contents = file_get_contents($dumpPath);
            if ($contents === false) {
                throw new RuntimeException('Unable to read SQLite dump contents.');
            }

            $database = new SQLite3($destination);
            $database->exec('PRAGMA foreign_keys = OFF;');

            if (! $database->exec($contents)) {
                $error = $database->lastErrorMsg();
                $database->close();

                throw new RuntimeException(sprintf('Failed to import SQLite dump: %s', $error));
            }

            $database->close();
        }

        $database = new SQLite3($destination);
        $integrity = (string) $database->querySingle('PRAGMA integrity_check;', false);
        $database->close();

        if (strtolower($integrity) !== 'ok') {
            throw new RuntimeException(sprintf('SQLite integrity check failed: %s', $integrity));
        }

        return $integrity;
    }

    protected function countSqliteTables(string $path): int
    {
        $database = new SQLite3($path);
        $query = $database->query("SELECT COUNT(*) AS total FROM sqlite_master WHERE type='table'");
        $result = $query->fetchArray(SQLITE3_ASSOC);
        $database->close();

        return (int) ($result['total'] ?? 0);
    }
}
