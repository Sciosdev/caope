<?php

namespace Tests\Feature\Security;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionProperty;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\DbDumper\Databases\MySql;
use Tests\TestCase;
use ZipArchive;

class BackupDataSecurityTest extends TestCase
{
    public function test_mysql_dump_tls_can_be_disabled_without_changing_the_application_connection(): void
    {
        $connectionOptions = config('database.connections.mysql.options');

        config([
            'database.connections.mysql.dump.skip_ssl' => true,
        ]);

        $dumper = DbDumperFactory::createFromConnection('mysql');
        $skipSsl = new ReflectionProperty(MySql::class, 'skipSsl');

        $this->assertTrue($skipSsl->getValue($dumper));
        $this->assertSame($connectionOptions, config('database.connections.mysql.options'));
    }

    public function test_production_backup_excludes_secrets_and_uses_the_private_destination(): void
    {
        $exclusions = config('backup.backup.source.files.exclude');
        $privateDisk = config('filesystems.private_default');

        $this->assertContains(base_path('.env*'), $exclusions);
        $this->assertContains(base_path('*.log'), $exclusions);
        $this->assertContains(base_path('bootstrap/cache'), $exclusions);
        $this->assertContains(storage_path('logs'), $exclusions);
        $this->assertContains(storage_path('framework'), $exclusions);
        $this->assertContains(storage_path('app/developer-console'), $exclusions);
        $this->assertContains(storage_path('app/deployment'), $exclusions);
        $this->assertContains(storage_path('app/tools'), $exclusions);
        $this->assertContains(storage_path('app/private/exports'), $exclusions);
        $this->assertContains(storage_path('app/exports'), $exclusions);
        $this->assertSame(base_path(), config('backup.backup.source.files.relative_path'));
        $this->assertSame([$privateDisk], config('backup.backup.destination.disks'));
        $this->assertSame([$privateDisk], config('backup.monitor_backups.0.disks'));
        $this->assertNotContains(storage_path('app/private/anexos'), $exclusions);
        $this->assertNotContains(storage_path('app/private/consentimientos'), $exclusions);
    }

    public function test_backup_archive_omits_runtime_secrets_and_encrypts_clinical_files(): void
    {
        $source = storage_path('framework/testing/backup-security-source-'.Str::random(12));
        $temporary = storage_path('framework/testing/backup-security-temp-'.Str::random(12));
        $password = 'backup-security-test-password-32-characters';

        File::ensureDirectoryExists($source.'/storage/logs');
        File::ensureDirectoryExists($source.'/bootstrap/cache');
        File::ensureDirectoryExists($source.'/storage/app/developer-console');
        File::ensureDirectoryExists($source.'/storage/app/private/anexos');
        File::put($source.'/.env', 'APP_KEY=must-not-be-backed-up');
        File::put($source.'/storage/logs/laravel.log', 'patient information');
        File::put($source.'/bootstrap/cache/config.php', '<?php return [\'app_key\' => \'cached-secret\'];');
        File::put($source.'/storage/app/developer-console/settings.enc', 'deployment-token');
        File::put($source.'/storage/app/private/anexos/clinical.txt', 'clinical-evidence');

        Storage::fake('backup-security-test');
        config([
            'backup.backup.name' => 'SecurityTest',
            'backup.backup.source.files.include' => [$source],
            'backup.backup.source.files.exclude' => [
                $source.'/.env*',
                $source.'/bootstrap/cache',
                $source.'/storage/logs',
                $source.'/storage/app/developer-console',
            ],
            'backup.backup.source.files.relative_path' => $source,
            'backup.backup.source.databases' => [],
            'backup.backup.destination.disks' => ['backup-security-test'],
            'backup.backup.temporary_directory' => $temporary,
            'backup.backup.password' => $password,
            'backup.backup.encryption' => 'default',
        ]);

        try {
            $this->artisan('backup:run', [
                '--only-files' => true,
                '--only-to-disk' => 'backup-security-test',
                '--disable-notifications' => true,
            ])->assertSuccessful();

            $archives = Storage::disk('backup-security-test')->allFiles('SecurityTest');
            $this->assertCount(1, $archives);

            $zip = new ZipArchive;
            $this->assertTrue($zip->open(Storage::disk('backup-security-test')->path($archives[0])));

            $entries = collect(range(0, $zip->numFiles - 1))
                ->map(function (int $index) use ($zip): array {
                    $actual = (string) $zip->getNameIndex($index);

                    return [
                        'actual' => $actual,
                        'normalized' => str_replace('\\', '/', $actual),
                    ];
                });

            $this->assertFalse($entries->contains(fn (array $entry): bool => Str::endsWith($entry['normalized'], '.env')));
            $this->assertFalse($entries->contains(fn (array $entry): bool => Str::contains($entry['normalized'], 'laravel.log')));
            $this->assertFalse($entries->contains(fn (array $entry): bool => Str::contains($entry['normalized'], 'bootstrap/cache/config.php')));
            $this->assertFalse($entries->contains(fn (array $entry): bool => Str::contains($entry['normalized'], 'settings.enc')));

            $clinicalEntry = $entries->first(fn (array $entry): bool => Str::endsWith($entry['normalized'], 'anexos/clinical.txt'));
            $this->assertIsArray($clinicalEntry);
            $this->assertFalse(@$zip->getFromName($clinicalEntry['actual']));
            $this->assertTrue($zip->setPassword($password));
            $this->assertSame('clinical-evidence', $zip->getFromName($clinicalEntry['actual']));
            $zip->close();
        } finally {
            File::deleteDirectory($source);
            File::deleteDirectory($temporary);
        }
    }

    public function test_backup_lifecycle_commands_are_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertScheduledCommand($events, 'backup:run --disable-notifications', '0 2 * * *');
        $this->assertScheduledCommand($events, 'backup:clean --disable-notifications', '0 3 * * *');
        $this->assertScheduledCommand($events, 'backup:monitor', '0 4 * * *');
    }

    private function assertScheduledCommand($events, string $command, string $expression): void
    {
        $event = $events->first(
            fn ($event): bool => is_string($event->command) && Str::contains($event->command, $command)
        );

        $this->assertNotNull($event, "The scheduled command [{$command}] was not registered.");
        $this->assertSame($expression, $event->expression);
    }
}
