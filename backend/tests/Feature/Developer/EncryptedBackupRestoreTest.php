<?php

namespace Tests\Feature\Developer;

use App\Console\Commands\AbstractBackupRestoreCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Tests\TestCase;
use Throwable;
use ZipArchive;

class EncryptedBackupRestoreTest extends TestCase
{
    public function test_restore_commands_can_extract_a_password_protected_backup(): void
    {
        $password = 'restore-test-password-32-characters';
        $sourcePath = storage_path('framework/testing/encrypted-backup-test.zip');
        File::ensureDirectoryExists(dirname($sourcePath));
        File::delete($sourcePath);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($sourcePath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $this->assertTrue($zip->addFromString('db-dumps/database.sql', 'select 1;'));
        $this->assertTrue($zip->setEncryptionName('db-dumps/database.sql', ZipArchive::EM_AES_256, $password));
        $this->assertTrue($zip->close());

        Storage::fake('encrypted-restore-test');
        Storage::disk('encrypted-restore-test')->put('backup.zip', (string) File::get($sourcePath));
        config(['backup.backup.password' => $password]);

        $backup = new Backup(Storage::disk('encrypted-restore-test'), 'backup.zip');
        $extracted = (new TestableBackupRestoreCommand)->extractForTest($backup);

        try {
            $this->assertSame(
                'select 1;',
                File::get($extracted['extracted'].DIRECTORY_SEPARATOR.'db-dumps'.DIRECTORY_SEPARATOR.'database.sql')
            );
        } finally {
            $extracted['directory']->delete();
            File::delete($sourcePath);
        }
    }

    public function test_wrong_password_removes_partially_extracted_temporary_data(): void
    {
        $sourcePath = $this->createArchive('correct-password', true);
        $temporaryRoot = storage_path('framework/testing/failed-backup-restore');
        File::deleteDirectory($temporaryRoot);
        Storage::fake('encrypted-restore-failure-test');
        Storage::disk('encrypted-restore-failure-test')->put('backup.zip', (string) File::get($sourcePath));
        config(['backup.backup.password' => 'incorrect-password-that-is-long-enough']);

        $command = new TestableBackupRestoreCommand($temporaryRoot);
        $exception = null;

        try {
            $command->extractForTest(new Backup(Storage::disk('encrypted-restore-failure-test'), 'backup.zip'));
        } catch (Throwable $caught) {
            $exception = $caught;
        } finally {
            File::delete($sourcePath);
        }

        $this->assertNotNull($exception, 'The encrypted archive unexpectedly accepted the wrong password.');
        $this->assertDirectoryDoesNotExist($temporaryRoot.DIRECTORY_SEPARATOR.'restore');
        File::deleteDirectory($temporaryRoot);
    }

    public function test_configured_password_remains_compatible_with_legacy_unencrypted_backups(): void
    {
        $sourcePath = $this->createArchive('', false);
        Storage::fake('legacy-restore-test');
        Storage::disk('legacy-restore-test')->put('backup.zip', (string) File::get($sourcePath));
        config(['backup.backup.password' => 'configured-password-32-characters']);

        $extracted = (new TestableBackupRestoreCommand)->extractForTest(
            new Backup(Storage::disk('legacy-restore-test'), 'backup.zip')
        );

        try {
            $this->assertSame(
                'select 1;',
                File::get($extracted['extracted'].DIRECTORY_SEPARATOR.'db-dumps'.DIRECTORY_SEPARATOR.'database.sql')
            );
        } finally {
            $extracted['directory']->delete();
            File::delete($sourcePath);
        }
    }

    private function createArchive(string $password, bool $encrypted): string
    {
        $sourcePath = storage_path('framework/testing/backup-'.bin2hex(random_bytes(8)).'.zip');
        File::ensureDirectoryExists(dirname($sourcePath));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($sourcePath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $this->assertTrue($zip->addFromString('db-dumps/database.sql', 'select 1;'));

        if ($encrypted) {
            $this->assertTrue($zip->setEncryptionName('db-dumps/database.sql', ZipArchive::EM_AES_256, $password));
        }

        $this->assertTrue($zip->close());

        return $sourcePath;
    }
}

class TestableBackupRestoreCommand extends AbstractBackupRestoreCommand
{
    protected $signature = 'testing:extract-encrypted-backup';

    public function __construct(private readonly ?string $temporaryRoot = null)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return self::SUCCESS;
    }

    /**
     * @return array{directory: \Spatie\TemporaryDirectory\TemporaryDirectory, extracted: string, zip: string}
     */
    public function extractForTest(Backup $backup): array
    {
        return $this->extractBackup($backup);
    }

    protected function createTemporaryDirectory(): TemporaryDirectory
    {
        if ($this->temporaryRoot === null) {
            return parent::createTemporaryDirectory();
        }

        return (new TemporaryDirectory($this->temporaryRoot))
            ->name('restore')
            ->force()
            ->create();
    }
}
