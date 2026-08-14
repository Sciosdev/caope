<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class RepositoryHygieneTest extends TestCase
{
    private string $repositoryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryRoot = dirname(__DIR__, 3);
    }

    #[Test]
    public function runtime_secrets_and_cpanel_artifacts_are_ignored(): void
    {
        foreach ([
            '.env',
            '.env.example',
            'backend/.env.local',
            'backend/.env.staging',
            'cron.artisan.log',
            'developer-setup.log',
            'backend/error_log',
            'backend/composer.phar',
            'backend/database/runtime.sqlite',
            'backup.sql',
            'backend/private-key.pem',
            '_template/nobleui/new.tmp',
        ] as $path) {
            $this->assertTrue($this->isIgnored($path), "Expected {$path} to be ignored.");
        }
    }

    #[Test]
    public function only_reviewed_backend_environment_templates_are_unignored(): void
    {
        foreach (['backend/.env.example', 'backend/.env.testing'] as $path) {
            $this->assertFalse($this->isIgnored($path), "Expected {$path} to remain versionable.");
        }
    }

    #[Test]
    public function ci_rejects_tracked_secrets_and_creates_an_empty_runtime_database(): void
    {
        $workflow = $this->read('.github/workflows/ci.yml');
        $hygieneScript = $this->read('scripts/check-repository-hygiene.sh');

        $this->assertStringContainsString('bash scripts/check-repository-hygiene.sh', $workflow);
        $this->assertStringContainsString('git ls-files -z', $hygieneScript);
        $this->assertStringContainsString('git cat-file -s ":${FILE}"', $hygieneScript);
        $this->assertStringContainsString('backend/.env.example|backend/.env.testing', $hygieneScript);
        $this->assertStringContainsString(': > database/database.sqlite', $workflow);
        $this->assertStringContainsString('test ! -s database/database.sqlite', $workflow);
        $this->assertStringNotContainsString('Create it and commit it', $workflow);
    }

    #[Test]
    public function every_deployment_gate_runs_hygiene_and_the_security_baseline(): void
    {
        foreach ([
            '.github/workflows/ci.yml',
            '.github/workflows/deploy.yml',
            '.github/workflows/deploy-staging.yml',
        ] as $workflowPath) {
            $workflow = $this->read($workflowPath);

            $this->assertStringContainsString('bash scripts/check-repository-hygiene.sh', $workflow);
            $this->assertStringContainsString('composer test:security-baseline', $workflow);
        }
    }

    #[Test]
    public function composer_setup_creates_but_never_overwrites_its_runtime_database(): void
    {
        $composer = json_decode($this->read('backend/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $setup = $composer['scripts']['setup'];
        $databaseStep = array_values(array_filter(
            $setup,
            static fn (string $step): bool => str_contains($step, 'database/database.sqlite')
        ));

        $this->assertCount(1, $databaseStep);
        $this->assertLessThan(
            array_search('@php artisan key:generate', $setup, true),
            array_search($databaseStep[0], $setup, true)
        );
        $this->assertMatchesRegularExpression('/^@php -r "(.+)"$/', $databaseStep[0]);
        preg_match('/^@php -r "(.+)"$/', $databaseStep[0], $matches);

        $temporaryRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'caope-composer-setup-'.bin2hex(random_bytes(8));
        $databaseDirectory = $temporaryRoot.DIRECTORY_SEPARATOR.'database';
        $databasePath = $databaseDirectory.DIRECTORY_SEPARATOR.'database.sqlite';
        $this->assertTrue(mkdir($databaseDirectory, 0700, true));

        try {
            (new Process([PHP_BINARY, '-r', $matches[1]], $temporaryRoot))->mustRun();
            $this->assertFileExists($databasePath);
            $this->assertSame(0, filesize($databasePath));

            file_put_contents($databasePath, 'existing-database');
            (new Process([PHP_BINARY, '-r', $matches[1]], $temporaryRoot))->mustRun();
            $this->assertSame('existing-database', file_get_contents($databasePath));
        } finally {
            @unlink($databasePath);
            @rmdir($databaseDirectory);
            @rmdir($temporaryRoot);
        }
    }

    #[Test]
    public function demo_seeder_does_not_contain_a_literal_password(): void
    {
        $seeder = $this->read('backend/database/seeders/DatabaseSeeder.php');

        $this->assertStringContainsString("app()->environment('local', 'testing')", $seeder);
        $this->assertStringContainsString('Str::password(32)', $seeder);
        $this->assertDoesNotMatchRegularExpression('/Hash::make\([\'\"][^\'\"]+[\'\"]\)/', $seeder);
    }

    private function isIgnored(string $path): bool
    {
        $command = sprintf(
            'git -C %s check-ignore -v --no-index -- %s',
            escapeshellarg($this->repositoryRoot),
            escapeshellarg($path)
        );
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, "Git did not find an ignore rule for {$path}.");

        $rule = implode("\n", $output);
        $tabPosition = strpos($rule, "\t");
        $metadata = $tabPosition === false ? $rule : substr($rule, 0, $tabPosition);

        return ! str_contains($metadata, ':!');
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->repositoryRoot.DIRECTORY_SEPARATOR.$relativePath);

        $this->assertNotFalse($contents, "Unable to read {$relativePath}.");

        return $contents;
    }
}
