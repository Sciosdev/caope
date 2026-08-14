<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DeploymentSecurityPreflightScriptsTest extends TestCase
{
    private string $repositoryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryRoot = dirname(__DIR__, 3);
    }

    #[Test]
    public function scheduled_deployment_audits_before_fetch_backup_or_maintenance(): void
    {
        $script = $this->read('scripts/deploy-scheduled.sh');
        $audit = strpos($script, 'artisan caope:security-audit');
        $fetch = strpos($script, 'fetch --no-tags origin main');
        $backup = strpos($script, 'artisan backup:run --only-db --no-interaction');
        $maintenance = strpos($script, 'artisan down --retry=60');

        $this->assertIsInt($audit);
        $this->assertIsInt($fetch);
        $this->assertIsInt($backup);
        $this->assertIsInt($maintenance);
        $this->assertLessThan($fetch, $audit);
        $this->assertLessThan($backup, $audit);
        $this->assertLessThan($maintenance, $audit);
        $this->assertStringContainsString('APP_CONFIG_CACHE=/dev/null', $script);
        $this->assertStringContainsString(
            'APP_CONFIG_CACHE=/dev/null "${PHP_BIN}" artisan backup:run --only-db --no-interaction',
            $script,
        );
        $this->assertStringContainsString('CAOPE_SECURITY_PROFILE:-auto', $script);
    }

    #[Test]
    public function bootstrap_audits_existing_and_new_installations_before_maintenance(): void
    {
        $script = $this->read('scripts/bootstrap-cpanel.sh');
        $firstAudit = strpos($script, 'artisan caope:security-audit');
        $lastAudit = strrpos($script, 'artisan caope:security-audit');
        $backup = strpos($script, 'artisan backup:run --only-db --no-interaction');
        $platformCheck = strpos($script, 'check-platform-reqs --no-dev');
        $lastMaintenance = strrpos($script, 'artisan down --retry=60');

        $this->assertIsInt($firstAudit);
        $this->assertIsInt($lastAudit);
        $this->assertIsInt($backup);
        $this->assertIsInt($platformCheck);
        $this->assertIsInt($lastMaintenance);
        $this->assertNotSame($firstAudit, $lastAudit);
        $this->assertLessThan($backup, $firstAudit);
        $this->assertLessThan($lastAudit, $platformCheck);
        $this->assertLessThan($lastMaintenance, $lastAudit);
        $this->assertStringContainsString('APP_CONFIG_CACHE=/dev/null', $script);
        $this->assertStringContainsString(
            'APP_CONFIG_CACHE=/dev/null "${PHP_BIN}" artisan backup:run --only-db --no-interaction',
            $script,
        );
        $this->assertStringContainsString('CAOPE_SECURITY_PROFILE:-auto', $script);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->repositoryRoot.DIRECTORY_SEPARATOR.$relativePath);

        $this->assertNotFalse($contents, "No se pudo leer {$relativePath}.");

        return $contents;
    }
}
