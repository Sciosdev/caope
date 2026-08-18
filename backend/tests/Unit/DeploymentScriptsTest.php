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
            'REQUIRED_PHP_EXTENSIONS=(ctype fileinfo json mbstring openssl pdo tokenizer zip)',
            'if ! PHP_MODULES="$("${PHP_BIN}" -m 2>/dev/null)"; then',
            "fail 'PHP no pudo enumerar las extensiones disponibles.'",
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
        $this->assertStringContainsString('rollback_failed_deployment()', $script);
        $this->assertStringContainsString('git reset --hard "${ORIGINAL_SHA}"', $script);
        $this->assertStringContainsString('"request_id" => "automatic-rollback"', $script);
        $this->assertStringContainsString("[[ \"\${CAOPE_REQUIRE_CLEAN_CHECKOUT:-0}\" == '1' ]]", $script);
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
    public function root_deployments_restore_web_runtime_permissions(): void
    {
        foreach ([
            'scripts/bootstrap-cpanel.sh',
            'scripts/deploy-scheduled.sh',
        ] as $relativePath) {
            $script = $this->read($relativePath);

            $this->assertStringContainsString('normalize_runtime_permissions()', $script);
            $this->assertStringContainsString('CAOPE_RUNTIME_GROUP', $script);
            $this->assertStringContainsString('storage/app/deployment', $script);
            $this->assertStringContainsString('storage/framework/cache', $script);
            $this->assertStringContainsString('chgrp -R -- "${runtime_group}"', $script);
            $this->assertStringContainsString('chmod 2770', $script);
            $this->assertStringContainsString('chmod 0660', $script);
            $this->assertGreaterThanOrEqual(3, substr_count($script, 'normalize_runtime_permissions'));
        }
    }

    #[Test]
    public function production_agent_runs_laravel_without_root_and_uses_database_control_planes(): void
    {
        $installer = $this->read('scripts/install-production-agent.sh');
        $this->assertStringContainsString('AGENT_USER="${CAOPE_AGENT_USER:-caope-deploy}"', $installer);
        $this->assertStringContainsString('useradd', $installer);
        $this->assertStringContainsString('--shell /usr/sbin/nologin', $installer);
        $this->assertStringContainsString('[[ "${AGENT_USER}" != \'root\'', $installer);
        $this->assertStringContainsString('usermod', $installer);
        $this->assertStringContainsString('--lock', $installer);
        $this->assertStringContainsString('User=${AGENT_USER}', $installer);
        $this->assertStringContainsString('NoNewPrivileges=true', $installer);
        $this->assertStringContainsString('UMask=0007', $installer);
        $this->assertStringContainsString('ExecStartPre=/bin/bash ${REPOSITORY_ROOT}/scripts/repair-runtime.sh', $installer);
        $this->assertStringContainsString('safe.directory=${REPOSITORY_ROOT}', $installer);
        $this->assertStringContainsString('caope-scheduler.timer', $installer);
        $this->assertStringContainsString('caope-queue.service', $installer);
        $this->assertStringContainsString('CACHE_STORE" => "database', $installer);
        $this->assertStringContainsString('QUEUE_CONNECTION" => "database', $installer);
        $this->assertStringContainsString('run_bootstrap_with_retries()', $installer);
        $this->assertStringContainsString('intento ${attempt}/3', $installer);
        $this->assertStringContainsString('COMPOSER_MAX_PARALLEL_HTTP=4', $installer);
        $finalApplicationDirectory = strrpos($installer, 'cd -- "${APPLICATION_ROOT}"');
        $finalSecurityAudit = strrpos($installer, 'artisan caope:security-audit --profile=production');
        $finalEnvironmentCheck = strrpos($installer, 'artisan about --only=environment');
        $this->assertNotFalse($finalApplicationDirectory);
        $this->assertNotFalse($finalSecurityAudit);
        $this->assertNotFalse($finalEnvironmentCheck);
        $this->assertLessThan($finalSecurityAudit, $finalApplicationDirectory);
        $this->assertLessThan($finalEnvironmentCheck, $finalApplicationDirectory);
        $this->assertStringNotContainsString('NOPASSWD', $installer);
        $this->assertStringNotContainsString('/etc/sudoers', $installer);
    }

    #[Test]
    public function runtime_repair_is_unprivileged_and_limited_to_laravel_runtime_paths(): void
    {
        $script = $this->read('scripts/repair-runtime.sh');

        $this->assertStringContainsString('[[ "$(id -u)" -ne 0 ]]', $script);
        $this->assertStringContainsString('bootstrap/cache', $script);
        $this->assertStringContainsString('storage/app/deployment', $script);
        $this->assertStringContainsString('storage/framework/cache', $script);
        $this->assertStringContainsString('storage/logs', $script);
        $this->assertStringContainsString('chmod 0660', $script);
        $this->assertStringContainsString('mkdir -p -- "${runtime_path}"', $script);
        $this->assertStringNotContainsString('install -d -m 2770', $script);
        $this->assertStringNotContainsString('chmod 2770', $script);
        $this->assertStringNotContainsString('sudo', $script);
        $this->assertStringNotContainsString('chown', $script);
        $this->assertStringNotContainsString('git ', $script);
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

    #[Test]
    public function web_health_check_inspects_root_owned_git_checkout_without_impersonating_the_scheduler(): void
    {
        $service = $this->read('backend/app/Services/DeveloperHealthService.php');

        $this->assertStringContainsString('safe.directory={$repositoryRoot}', $service);
        $this->assertStringNotContainsString('$writableTargets = [$repositoryRoot', $service);
        $this->assertStringContainsString("'main · scheduler'", $service);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->repositoryRoot.DIRECTORY_SEPARATOR.$relativePath);

        $this->assertNotFalse($contents, "No se pudo leer {$relativePath}.");

        return $contents;
    }
}
