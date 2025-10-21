<?php

namespace App\Console\Commands;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use mysqli;
use RuntimeException;
use Throwable;

class TestMysqlBackupRestoreCommand extends AbstractBackupRestoreCommand
{
    protected $signature = 'backup:test-restore-mysql
        {--connection=mysql : Connection name to validate.}
        {--keep-database : Do not drop the temporary database after the test.}
        {--allow-missing : Treat missing dumps as a successful, skipped check.}
        {--allow-unavailable : Treat unreachable database servers as a successful, skipped check.}';

    protected $description = 'Run a trial restoration of the latest MySQL backup archive.';

    public function handle(): int
    {
        if (! extension_loaded('mysqli')) {
            if ($this->option('allow-unavailable')) {
                $this->logResult('mysql', 'skipped', [
                    'reason' => 'missing_mysqli_extension',
                ]);

                $this->components->warn('Skipping MySQL restore test because the mysqli extension is not available.');

                return self::SUCCESS;
            }

            $this->components->error('The mysqli extension is required to execute this command.');

            return self::FAILURE;
        }

        $connectionName = (string) $this->option('connection');
        $config = config("database.connections.{$connectionName}");

        if (! is_array($config)) {
            if ($this->option('allow-unavailable')) {
                $this->logResult('mysql', 'skipped', [
                    'connection' => $connectionName,
                    'reason' => 'missing_connection_configuration',
                ]);

                $this->components->warn(sprintf('Skipping MySQL restore test because connection "%s" is not configured.', $connectionName));

                return self::SUCCESS;
            }

            $this->components->error(sprintf('Connection "%s" is not configured.', $connectionName));

            return self::FAILURE;
        }

        $backup = $this->latestBackup();
        if (! $backup) {
            if ($this->option('allow-missing')) {
                $this->logResult('mysql', 'skipped', [
                    'reason' => 'backup_not_found',
                ]);

                $this->components->warn('Skipping MySQL restore test because no backups were found.');

                return self::SUCCESS;
            }

            $this->components->error('No backup archives were found on the configured disks.');

            return self::FAILURE;
        }

        $this->components->info(sprintf('Using backup %s from disk %s', $backup->path(), $this->artifactDiskName()));

        $paths = $this->extractBackup($backup);
        $databaseName = $this->temporaryDatabaseName(Arr::get($config, 'database'));
        $mysqli = null;

        try {
            $dumpDirectory = $paths['extracted'].'/db-dumps';
            $dumpPath = $this->locateDumpFile($dumpDirectory, function (string $path) use ($connectionName) {
                $basename = basename($path);

                return Str::contains($basename, $connectionName) && Str::of($basename)->endsWith('.sql');
            });

            if (! $dumpPath) {
                if ($this->option('allow-missing')) {
                    $this->logResult('mysql', 'skipped', [
                        'backup' => $backup->path(),
                        'reason' => 'dump_not_found',
                    ]);

                    $this->components->warn('Skipping MySQL restore test because the archive does not contain a MySQL dump.');

                    return self::SUCCESS;
                }

                throw new RuntimeException('Unable to locate a MySQL dump inside the archive.');
            }

            try {
                $mysqli = $this->openConnection($config);
            } catch (Throwable $exception) {
                if ($this->option('allow-unavailable')) {
                    $this->logResult('mysql', 'skipped', [
                        'backup' => $backup->path(),
                        'reason' => 'connection_failed',
                        'error' => $exception->getMessage(),
                    ]);

                    $this->components->warn('Skipping MySQL restore test because the database connection is not available.');

                    return self::SUCCESS;
                }

                throw $exception;
            }
            $this->createDatabase($mysqli, $databaseName, $config);
            $this->importDump($mysqli, $databaseName, $dumpPath);

            $tables = $this->countMysqlTables($mysqli, $databaseName);

            $this->logResult('mysql', 'passed', [
                'backup' => $backup->path(),
                'dump' => Str::after($dumpPath, $paths['extracted'].'/'),
                'database' => $databaseName,
                'tables' => $tables,
            ]);

            $this->components->info(sprintf('MySQL restore test completed successfully with %d tables.', $tables));

            if (! $this->option('keep-database')) {
                $this->dropDatabase($mysqli, $databaseName);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($mysqli instanceof mysqli && $databaseName !== null && ! $this->option('keep-database')) {
                try {
                    $this->dropDatabase($mysqli, $databaseName);
                } catch (Throwable) {
                    // noop
                }
            }

            $this->logResult('mysql', 'failed', [
                'backup' => $backup?->path(),
                'database' => $databaseName,
                'error' => $exception->getMessage(),
            ]);

            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($mysqli instanceof mysqli) {
                $mysqli->close();
            }

            $paths['directory']->delete();
        }
    }

    protected function openConnection(array $config): mysqli
    {
        $mysqli = mysqli_init();
        if ($mysqli === false) {
            throw new RuntimeException('Unable to initialise mysqli.');
        }

        $host = $config['host'] ?? '127.0.0.1';
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $port = (int) ($config['port'] ?? 3306);
        $socket = $config['unix_socket'] ?? null;

        if (! $mysqli->real_connect($host, $username, $password, null, $port, $socket)) {
            $error = $mysqli->connect_error ?: 'Unknown error establishing the database connection.';

            throw new RuntimeException($error);
        }

        if (isset($config['charset'])) {
            $mysqli->set_charset($config['charset']);
        }

        return $mysqli;
    }

    protected function createDatabase(mysqli $mysqli, string $database, array $config): void
    {
        $charset = $config['charset'] ?? 'utf8mb4';
        $collation = $config['collation'] ?? 'utf8mb4_unicode_ci';

        $statement = sprintf('CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s', $database, $charset, $collation);

        if (! $mysqli->query($statement)) {
            throw new RuntimeException(sprintf('Unable to create database %s: %s', $database, $mysqli->error));
        }
    }

    protected function importDump(mysqli $mysqli, string $database, string $dumpPath): void
    {
        $contents = file_get_contents($dumpPath);
        if ($contents === false) {
            throw new RuntimeException('Unable to read MySQL dump contents.');
        }

        $mysqli->query("USE `{$database}`");

        $delimiter = ';';
        $buffer = '';

        foreach (preg_split('/\r\n|\r|\n/', $contents) as $line) {
            $trimmed = ltrim($line);

            if ($trimmed === '' && $buffer === '') {
                continue;
            }

            if (str_starts_with($trimmed, '--')) {
                continue;
            }

            if (preg_match('/^\/\*!\d+\s+(.*)\*\/;?$/', $trimmed, $matches)) {
                $line = $matches[1].';';
            }

            if (str_starts_with(strtoupper($trimmed), 'DELIMITER ')) {
                $delimiter = trim(substr($trimmed, 9));
                continue;
            }

            $buffer .= $line."\n";

            if ($this->bufferEndsWithDelimiter($buffer, $delimiter)) {
                $statement = $this->trimDelimiter($buffer, $delimiter);
                $buffer = '';

                $statement = trim($statement);
                if ($statement === '') {
                    continue;
                }

                if (preg_match('/^CREATE DATABASE/i', $statement) || preg_match('/^USE\s+/i', $statement)) {
                    continue;
                }

                if (! $mysqli->query($statement)) {
                    throw new RuntimeException(sprintf('Failed executing statement: %s', $mysqli->error));
                }
            }
        }

        $remaining = trim($buffer);
        if ($remaining !== '') {
            if (! $mysqli->query($remaining)) {
                throw new RuntimeException(sprintf('Failed executing statement: %s', $mysqli->error));
            }
        }
    }

    protected function bufferEndsWithDelimiter(string $buffer, string $delimiter): bool
    {
        if ($delimiter === '') {
            return false;
        }

        $buffer = rtrim($buffer);

        return str_ends_with($buffer, $delimiter);
    }

    protected function trimDelimiter(string $buffer, string $delimiter): string
    {
        $buffer = rtrim($buffer);

        if ($delimiter !== '' && str_ends_with($buffer, $delimiter)) {
            return substr($buffer, 0, -strlen($delimiter));
        }

        return $buffer;
    }

    protected function countMysqlTables(mysqli $mysqli, string $database): int
    {
        $statement = sprintf(
            "SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = '%s'",
            $mysqli->real_escape_string($database)
        );

        $result = $mysqli->query($statement);

        if ($result === false) {
            throw new RuntimeException(sprintf('Unable to inspect tables for %s: %s', $database, $mysqli->error));
        }

        $row = $result->fetch_assoc();
        $result->free();

        return (int) ($row['total'] ?? 0);
    }

    protected function dropDatabase(mysqli $mysqli, string $database): void
    {
        if (! $mysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $database))) {
            throw new RuntimeException(sprintf('Unable to drop database %s: %s', $database, $mysqli->error));
        }
    }

    protected function temporaryDatabaseName(?string $base): string
    {
        $base = $base ?: 'database';

        return Str::of($base)
            ->slug('_')
            ->append('_restore_test_', Str::random(8))
            ->toString();
    }
}
