<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DeploymentScriptsTest extends TestCase
{
    private string $repositoryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryRoot = dirname(__DIR__, 3);
    }

    #[Test]
    public function cpanel_uses_the_standalone_bootstrap(): void
    {
        $configuration = $this->read('.cpanel.yml');

        $this->assertStringContainsString('/bin/bash scripts/bootstrap-cpanel.sh', $configuration);
        $this->assertStringNotContainsString('deploy-scheduled.sh', $configuration);
    }

    #[Test]
    public function bootstrap_does_not_require_an_authorization_or_change_git(): void
    {
        $script = $this->read('scripts/bootstrap-cpanel.sh');

        $this->assertStringNotContainsString('expected.json', $script);
        $this->assertDoesNotMatchRegularExpression('/\bgit\s+(?:fetch|merge|pull|reset|checkout)\b/i', $script);
        $this->assertStringNotContainsString('db:seed', $script);
        $this->assertStringNotContainsString('migrate:fresh', $script);
    }

    #[Test]
    public function bootstrap_contains_the_required_safety_steps(): void
    {
        $script = $this->read('scripts/bootstrap-cpanel.sh');

        foreach ([
            'version_compare(PHP_VERSION, "8.3.0", "<")',
            '"ctype", "fileinfo", "json", "mbstring", "openssl", "pdo", "tokenizer", "zip"',
            'https://composer.github.io/installer.sig',
            'hash_equals($expected, $actual)',
            'backup:run --only-db --no-interaction',
            'artisan down --retry=60',
            '"${COMPOSER_COMMAND[@]}" install',
            '--no-dev',
            'artisan migrate --force',
            'artisan config:cache',
            'artisan view:cache',
            'artisan storage:link --force',
            'artisan queue:restart',
            '"request_id" => "cpanel-bootstrap"',
            'git -C "${REPOSITORY_ROOT}" rev-parse HEAD',
            'artisan up',
        ] as $requiredStep) {
            $this->assertStringContainsString($requiredStep, $script);
        }
    }

    #[Test]
    public function scheduled_deployment_keeps_exact_authorization_checks(): void
    {
        $script = $this->read('scripts/deploy-scheduled.sh');

        $this->assertStringContainsString('expected.json', $script);
        $this->assertStringContainsString('request_id', $script);
        $this->assertStringContainsString('git merge --ff-only', $script);
    }

    #[Test]
    public function legacy_staging_script_is_a_compatibility_alias(): void
    {
        $script = $this->read('scripts/deploy-cpanel-staging.sh');

        $this->assertStringContainsString('exec /bin/bash "${SCRIPT_DIRECTORY}/deploy-scheduled.sh"', $script);
        $this->assertStringNotContainsString('git fetch', $script);
        $this->assertStringNotContainsString('composer install', $script);
    }

    #[Test]
    public function deployment_scripts_validate_composer_candidates_in_fallback_order(): void
    {
        foreach ([
            'scripts/bootstrap-cpanel.sh',
            'scripts/deploy-scheduled.sh',
        ] as $relativePath) {
            $script = $this->read($relativePath);
            $globalValidation = '"${PHP_BIN}" "${GLOBAL_COMPOSER_BIN}" --version >/dev/null 2>&1';
            $privateValidation = '"${PHP_BIN}" "${LOCAL_COMPOSER_PATH}" --version >/dev/null 2>&1';
            $downloadMessage = 'se preparará una copia privada verificada';

            $this->assertStringContainsString($globalValidation, $script);
            $this->assertStringContainsString($privateValidation, $script);
            $this->assertStringContainsString($downloadMessage, $script);

            $globalPosition = strpos($script, $globalValidation);
            $privatePosition = strpos($script, $privateValidation);
            $downloadPosition = strpos($script, $downloadMessage);

            $this->assertIsInt($globalPosition);
            $this->assertIsInt($privatePosition);
            $this->assertIsInt($downloadPosition);
            $this->assertLessThan($privatePosition, $globalPosition);
            $this->assertLessThan($downloadPosition, $privatePosition);
        }
    }

    #[Test]
    public function apache_forces_supported_hosts_to_https_without_reflecting_the_host_header(): void
    {
        $configuration = $this->read('backend/public/.htaccess');

        $this->assertStringContainsString(
            'https://caope.ayudafesi.com%{REQUEST_URI}',
            $configuration,
        );
        $this->assertStringContainsString(
            'https://xocoyotzin.iztacala.unam.mx%{REQUEST_URI}',
            $configuration,
        );
        $this->assertStringContainsString('caope\.ayudafesi\.com(?::[0-9]+)?$', $configuration);
        $this->assertStringContainsString('xocoyotzin\.iztacala\.unam\.mx(?::[0-9]+)?$', $configuration);
        $this->assertStringNotContainsString('HTTP:X-Forwarded-Proto', $configuration);
        $this->assertStringNotContainsString('https://%{HTTP_HOST}', $configuration);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->repositoryRoot.DIRECTORY_SEPARATOR.$relativePath);

        $this->assertNotFalse($contents, "No se pudo leer {$relativePath}.");

        return $contents;
    }
}
