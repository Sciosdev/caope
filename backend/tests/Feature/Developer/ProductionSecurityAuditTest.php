<?php

namespace Tests\Feature\Developer;

use App\Services\ProductionSecurityAudit;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductionSecurityAuditTest extends TestCase
{
    private const BACKUP_PASSWORD = 'audit-password-must-not-be-printed';

    private string $validKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validKey = 'base64:'.base64_encode(random_bytes(32));

        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => $this->validKey,
            'app.cipher' => 'AES-256-CBC',
            'app.url' => 'https://caope.ayudafesi.com',
            'security.trusted_hosts' => ['caope.ayudafesi.com'],
            'session.secure' => true,
            'backup.backup.password' => self::BACKUP_PASSWORD,
        ]);
    }

    public function test_production_profile_accepts_only_a_safe_configuration(): void
    {
        $checks = app(ProductionSecurityAudit::class)->run();

        $this->assertCount(6, $checks);
        $this->assertSame([], array_values(array_filter(
            $checks,
            static fn (array $check): bool => $check['status'] !== 'ok'
        )));
        $this->assertStringNotContainsString($this->validKey, json_encode($checks, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString(self::BACKUP_PASSWORD, json_encode($checks, JSON_THROW_ON_ERROR));
    }

    public function test_production_profile_reports_every_unsafe_setting_without_values(): void
    {
        $invalidKey = 'invalid-key-value-must-not-be-printed';
        config([
            'app.env' => 'staging',
            'app.debug' => true,
            'app.key' => $invalidKey,
            'app.url' => 'http://untrusted.example.test',
            'session.secure' => false,
            'backup.backup.password' => null,
        ]);

        $checks = app(ProductionSecurityAudit::class)->run();

        $this->assertSame(
            [
                'security_environment',
                'security_debug',
                'security_app_key',
                'security_app_url',
                'security_session_cookie',
                'security_backup_encryption',
            ],
            array_column(array_filter(
                $checks,
                static fn (array $check): bool => $check['status'] === 'error'
            ), 'id')
        );
        $this->assertStringNotContainsString($invalidKey, json_encode($checks, JSON_THROW_ON_ERROR));
    }

    public function test_staging_profile_warns_about_its_environment_but_keeps_security_requirements(): void
    {
        config(['app.env' => 'staging']);

        $checks = app(ProductionSecurityAudit::class)->run(ProductionSecurityAudit::PROFILE_STAGING);

        $this->assertSame('warning', $checks[0]['status']);
        $this->assertSame([], app(ProductionSecurityAudit::class)->errors(ProductionSecurityAudit::PROFILE_STAGING));

        config(['backup.backup.password' => '']);

        $this->assertSame(
            ['security_backup_encryption'],
            array_column(app(ProductionSecurityAudit::class)->errors(ProductionSecurityAudit::PROFILE_STAGING), 'id')
        );
    }

    public function test_deployed_profiles_reject_extra_hosts_in_the_http_allowlist(): void
    {
        config(['security.trusted_hosts' => ['caope.ayudafesi.com', 'attacker.example']]);

        $this->assertSame(
            ['security_app_url'],
            array_column(app(ProductionSecurityAudit::class)->errors(), 'id'),
        );
    }

    public function test_backup_password_must_be_long_and_distinct_from_the_application_key(): void
    {
        config(['backup.backup.password' => 'too-short']);

        $this->assertSame(
            ['security_backup_encryption'],
            array_column(app(ProductionSecurityAudit::class)->errors(), 'id')
        );

        config(['backup.backup.password' => $this->validKey]);

        $this->assertSame(
            ['security_backup_encryption'],
            array_column(app(ProductionSecurityAudit::class)->errors(), 'id')
        );
    }

    public function test_a_valid_but_previously_exposed_application_key_is_rejected(): void
    {
        $keyBytes = random_bytes(32);
        config([
            'app.key' => 'base64:'.base64_encode($keyBytes),
            'security.compromised_app_key_hashes' => [hash('sha256', $keyBytes)],
        ]);

        $this->assertSame(
            ['security_app_key'],
            array_column(app(ProductionSecurityAudit::class)->errors(), 'id'),
        );
    }

    public function test_a_previously_exposed_key_cannot_remain_as_a_fallback_key(): void
    {
        $previousKeyBytes = random_bytes(32);
        config([
            'app.previous_keys' => ['base64:'.base64_encode($previousKeyBytes)],
            'security.compromised_app_key_hashes' => [hash('sha256', $previousKeyBytes)],
        ]);

        $this->assertSame(
            ['security_app_key'],
            array_column(app(ProductionSecurityAudit::class)->errors(), 'id'),
        );
    }

    public function test_command_returns_failure_for_an_unsafe_profile_and_never_prints_secrets(): void
    {
        $exitCode = Artisan::call('caope:security-audit', [
            '--profile' => 'production',
            '--no-interaction' => true,
        ]);
        $safeOutput = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('La configuración cumple el perfil solicitado.', $safeOutput);
        $this->assertStringNotContainsString($this->validKey, $safeOutput);
        $this->assertStringNotContainsString(self::BACKUP_PASSWORD, $safeOutput);

        config(['backup.backup.password' => null]);

        $this->assertSame(1, Artisan::call('caope:security-audit', [
            '--profile' => 'production',
            '--no-interaction' => true,
        ]));
        $unsafeOutput = Artisan::output();

        $this->assertStringContainsString('BACKUP_ARCHIVE_PASSWORD no cumple', $unsafeOutput);
        $this->assertStringNotContainsString($this->validKey, $unsafeOutput);
        $this->assertStringNotContainsString(self::BACKUP_PASSWORD, $unsafeOutput);
    }

    public function test_security_audit_command_is_registered(): void
    {
        $this->assertSame(0, Artisan::call('list', ['--raw' => true]));
        $this->assertStringContainsString('caope:security-audit', Artisan::output());
    }
}
