<?php

namespace App\Services;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DeveloperHealthService
{
    public function __construct(
        private Migrator $migrator,
        private DeveloperConsoleSettings $settings,
        private ProductionSecurityAudit $securityAudit,
    ) {}

    /**
     * @return list<array{id: string, label: string, status: string, summary: string, details: ?string}>
     */
    public function run(): array
    {
        $securityProfile = $this->securityAudit->profileForCurrentEnvironment();

        return [
            $this->deployedVersion(),
            ...$this->securityAudit->run($securityProfile),
            $this->phpRuntime(),
            $this->database(),
            $this->migrations(),
            $this->cache(),
            $this->privateStorage(),
            $this->frontendAssets(),
            $this->queue(),
            $this->scheduler(),
            $this->backup(),
            $this->deploymentRuntime(),
            $this->githubDeployment(),
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: ?string}
     */
    private function deployedVersion(): array
    {
        $markerPath = storage_path('app/deployment/version.json');

        try {
            if (is_file($markerPath) && is_readable($markerPath)) {
                $marker = json_decode((string) file_get_contents($markerPath), true);
                $sha = is_array($marker) ? (string) ($marker['sha'] ?? '') : '';
                $deployedAt = is_array($marker) ? (string) ($marker['deployed_at'] ?? '') : '';

                if ($sha !== '') {
                    return $this->result(
                        'version',
                        'Versión desplegada',
                        'ok',
                        substr($sha, 0, 12),
                        $deployedAt !== '' ? $deployedAt : null
                    );
                }
            }

            $sha = $this->readGitHead();

            return $this->result(
                'version',
                'Versión desplegada',
                'warning',
                $sha ? substr($sha, 0, 12) : 'No identificada',
                'El marcador se generará en el primer despliegue automatizado.'
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->result('version', 'Versión desplegada', 'error', 'No fue posible identificar la versión.');
        }
    }

    private function readGitHead(): ?string
    {
        $repositoryRoot = dirname(base_path());
        $headPath = $repositoryRoot.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'HEAD';

        if (! is_file($headPath)) {
            return null;
        }

        $head = trim((string) file_get_contents($headPath));

        if (! str_starts_with($head, 'ref: ')) {
            return preg_match('/^[a-f0-9]{40}$/i', $head) === 1 ? $head : null;
        }

        $ref = substr($head, 5);
        $refPath = $repositoryRoot.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $ref);

        if (is_file($refPath)) {
            return trim((string) file_get_contents($refPath));
        }

        $packedRefs = $repositoryRoot.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'packed-refs';
        if (! is_file($packedRefs)) {
            return null;
        }

        foreach (file($packedRefs, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_ends_with($line, ' '.$ref)) {
                return strtok($line, ' ') ?: null;
            }
        }

        return null;
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: ?string}
     */
    private function phpRuntime(): array
    {
        if (version_compare(PHP_VERSION, '8.3.0', '<')) {
            return $this->result(
                'php',
                'PHP',
                'error',
                'CAOPE requiere PHP 8.3 o posterior.',
                'Versión activa: '.PHP_VERSION
            );
        }

        $required = ['ctype', 'fileinfo', 'json', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'zip'];
        $missing = array_values(array_filter($required, fn (string $extension): bool => ! extension_loaded($extension)));

        if ($missing !== []) {
            return $this->result(
                'php',
                'PHP',
                'error',
                'Faltan extensiones requeridas.',
                implode(', ', $missing)
            );
        }

        return $this->result('php', 'PHP', 'ok', 'PHP '.PHP_VERSION.' está disponible.');
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: ?string}
     */
    private function database(): array
    {
        try {
            DB::select('select 1');

            return $this->result(
                'database',
                'Base de datos',
                'ok',
                'La conexión responde correctamente.',
                (string) DB::connection()->getDriverName()
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->result('database', 'Base de datos', 'error', 'No fue posible consultar la base de datos.');
        }
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: ?string}
     */
    private function migrations(): array
    {
        try {
            $repository = $this->migrator->getRepository();

            if (! $repository->repositoryExists()) {
                return $this->result('migrations', 'Migraciones', 'error', 'La tabla de migraciones no existe.');
            }

            $files = $this->migrator->getMigrationFiles(database_path('migrations'));
            $pending = array_values(array_diff(array_keys($files), $repository->getRan()));

            if ($pending !== []) {
                return $this->result(
                    'migrations',
                    'Migraciones',
                    'warning',
                    count($pending).' migración(es) pendiente(s).',
                    implode(', ', array_slice($pending, 0, 5))
                );
            }

            return $this->result('migrations', 'Migraciones', 'ok', 'El esquema está actualizado.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->result('migrations', 'Migraciones', 'error', 'No fue posible comprobar las migraciones.');
        }
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: ?string}
     */
    private function cache(): array
    {
        $key = 'developer-console:health:'.Str::uuid();

        try {
            Cache::put($key, 'ok', 10);
            $works = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $works
                ? $this->result('cache', 'Caché', 'ok', 'La escritura y lectura funcionan.', (string) config('cache.default'))
                : $this->result('cache', 'Caché', 'error', 'La caché no devolvió el valor de comprobación.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->result('cache', 'Caché', 'error', 'No fue posible escribir en la caché.');
        }
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: ?string}
     */
    private function privateStorage(): array
    {
        $diskName = (string) config('filesystems.private_default', 'private');
        $path = 'health-checks/'.Str::uuid().'.txt';

        try {
            $disk = Storage::disk($diskName);
            $written = $disk->put($path, 'ok');
            $works = $written && $disk->get($path) === 'ok';
            $disk->delete($path);

            return $works
                ? $this->result('storage', 'Almacenamiento privado', 'ok', 'La escritura y lectura funcionan.', $diskName)
                : $this->result('storage', 'Almacenamiento privado', 'error', 'El disco no completó la comprobación.', $diskName);
        } catch (Throwable $exception) {
            report($exception);

            return $this->result('storage', 'Almacenamiento privado', 'error', 'No fue posible utilizar el disco privado.', $diskName);
        }
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: ?string}
     */
    private function frontendAssets(): array
    {
        $buildPath = trim((string) config('vite.build_path', 'assets/build'), '/\\');
        $manifestName = ltrim((string) config('vite.manifest', 'manifest.json'), '/\\');
        $manifestPath = public_path($buildPath.DIRECTORY_SEPARATOR.$manifestName);

        if (! is_file($manifestPath) || ! is_readable($manifestPath)) {
            return $this->result('assets', 'Assets frontend', 'error', 'No se encontró el manifiesto compilado.', $manifestPath);
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($decoded) || $decoded === []) {
            return $this->result('assets', 'Assets frontend', 'error', 'El manifiesto está vacío o no es válido.');
        }

        return $this->result('assets', 'Assets frontend', 'ok', 'El manifiesto compilado está disponible.', count($decoded).' entrada(s)');
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: ?string}
     */
    private function queue(): array
    {
        $connection = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connection}.driver", $connection);

        try {
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;
            $pending = $driver === 'database' && Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;

            if ($failed !== null && $failed > 0) {
                return $this->result('queue', 'Colas', 'warning', $failed.' trabajo(s) fallido(s).', "Conexión: {$connection}");
            }

            if (app()->environment('production') && $driver === 'sync') {
                return $this->result('queue', 'Colas', 'warning', 'La cola se ejecuta de forma síncrona en producción.', $connection);
            }

            $details = "Conexión: {$connection}";
            if ($pending !== null) {
                $details .= " · Pendientes: {$pending}";
            }

            return $this->result('queue', 'Colas', 'ok', 'La configuración de colas está disponible.', $details);
        } catch (Throwable $exception) {
            report($exception);

            return $this->result('queue', 'Colas', 'error', 'No fue posible comprobar las colas.', $connection);
        }
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: ?string}
     */
    private function scheduler(): array
    {
        try {
            $lastRun = Cache::get('system.scheduler.last_run');
            $lastRunAt = is_string($lastRun) ? Carbon::parse($lastRun) : null;

            if (! $lastRunAt) {
                return $this->result('scheduler', 'Tareas programadas', 'error', 'Todavía no se ha registrado el heartbeat del scheduler.');
            }

            if ($lastRunAt->lt(now()->subMinutes(3))) {
                return $this->result(
                    'scheduler',
                    'Tareas programadas',
                    'error',
                    'El scheduler no se ha ejecutado recientemente.',
                    $lastRunAt->format('d/m/Y H:i:s')
                );
            }

            return $this->result('scheduler', 'Tareas programadas', 'ok', 'El scheduler está activo.', $lastRunAt->format('d/m/Y H:i:s'));
        } catch (Throwable $exception) {
            report($exception);

            return $this->result('scheduler', 'Tareas programadas', 'error', 'No fue posible leer el heartbeat del scheduler.');
        }
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: ?string}
     */
    private function backup(): array
    {
        $diskName = (string) data_get(config('backup'), 'backup.destination.disks.0', 'private');
        $backupName = (string) data_get(config('backup'), 'backup.name', config('app.name', 'laravel-backup'));

        try {
            $disk = Storage::disk($diskName);
            $files = collect($disk->allFiles($backupName))
                ->filter(fn (string $path): bool => Str::endsWith(Str::lower($path), '.zip'));

            if ($files->isEmpty()) {
                return $this->result('backup', 'Respaldos', 'warning', 'No se encontró un respaldo previo.', $diskName);
            }

            $latestTimestamp = $files
                ->map(fn (string $path): int => $disk->lastModified($path))
                ->max();
            $latest = Carbon::createFromTimestamp((int) $latestTimestamp);
            $status = $latest->lt(now()->subHours(26)) ? 'warning' : 'ok';

            return $this->result(
                'backup',
                'Respaldos',
                $status,
                $status === 'ok' ? 'Existe un respaldo reciente.' : 'El último respaldo tiene más de 26 horas.',
                $latest->format('d/m/Y H:i:s')
            );
        } catch (Throwable $exception) {
            report($exception);

            // The deployment script creates and validates the backup as the
            // scheduler user before changing the checkout. A web process that
            // cannot inspect root-owned archive metadata must not block that
            // independently enforced safety step.
            return $this->result('backup', 'Respaldos', 'warning', 'No fue posible revisar los respaldos desde la web.', $diskName);
        }
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: ?string}
     */
    private function githubDeployment(): array
    {
        $repository = $this->settings->githubRepository();
        $workflow = $this->settings->githubWorkflow();
        $token = $this->settings->githubToken();

        if ($repository === '' || $workflow === '' || $token === '') {
            return $this->result('github', 'Despliegue GitHub', 'warning', 'Falta completar la configuración de GitHub Actions.');
        }

        return $this->result('github', 'Despliegue GitHub', 'ok', 'Las credenciales de despliegue están configuradas.', "{$repository} · {$workflow}");
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: ?string}
     */
    private function deploymentRuntime(): array
    {
        $script = $this->settings->deployScript();
        $missing = [];

        if (! function_exists('proc_open')) {
            $missing[] = 'proc_open';
        }

        if (! is_executable('/bin/bash')) {
            $missing[] = '/bin/bash';
        }

        if ($script === '' || ! is_readable($script)) {
            $missing[] = $script !== '' ? $script : 'script de despliegue';
        }

        if ($missing !== []) {
            return $this->result(
                'deployment_runtime',
                'Motor de despliegue',
                'error',
                'El servidor no puede ejecutar despliegues autónomos.',
                implode(', ', $missing)
            );
        }

        try {
            $repositoryRoot = dirname(base_path());
            $git = fn (array $command) => Process::path($repositoryRoot)
                ->env(['GIT_TERMINAL_PROMPT' => '0'])
                ->timeout(5)
                ->run($command);
            $status = $git(['git', 'status', '--porcelain=v1', '--untracked-files=no']);

            if ($status->failed()) {
                return $this->result(
                    'deployment_runtime',
                    'Motor de despliegue',
                    'error',
                    'La instalación no es un checkout Git utilizable por el scheduler.'
                );
            }

            $branch = $git(['git', 'symbolic-ref', '--quiet', '--short', 'HEAD']);
            $upstream = $git(['git', 'rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{upstream}']);
            $origin = $git(['git', 'remote', 'get-url', 'origin']);

            if ($branch->failed()
                || $upstream->failed()
                || trim($branch->output()) !== 'main'
                || trim($upstream->output()) !== 'origin/main') {
                return $this->result(
                    'deployment_runtime',
                    'Motor de despliegue',
                    'error',
                    'El checkout debe permanecer en la rama main con seguimiento de origin/main.'
                );
            }

            if ($origin->failed() || preg_match(
                '~^(?:https://github\.com/|git@github\.com:|ssh://git@github\.com/)Sciosdev/caope(?:\.git)?/?$~i',
                trim($origin->output())
            ) !== 1) {
                return $this->result(
                    'deployment_runtime',
                    'Motor de despliegue',
                    'error',
                    'El remoto origin no corresponde al repositorio oficial de CAOPE.'
                );
            }

            if (trim($status->output()) !== '') {
                $requiresCleanCheckout = $this->settings->hasEncryptedSettings();

                return $this->result(
                    'deployment_runtime',
                    'Motor de despliegue',
                    $requiresCleanCheckout ? 'error' : 'warning',
                    $requiresCleanCheckout
                        ? 'El checkout contiene cambios locales en archivos versionados.'
                        : 'Pruebas contiene cambios locales versionados; se permitirá el despliegue legado.',
                    $requiresCleanCheckout ? null : 'Producción exigirá un checkout limpio.'
                );
            }

            $writableTargets = [$repositoryRoot, $repositoryRoot.DIRECTORY_SEPARATOR.'.git', base_path(), storage_path()];
            $notWritable = array_values(array_filter(
                $writableTargets,
                fn (string $path): bool => ! is_dir($path) || ! is_writable($path)
            ));

            if ($notWritable !== []) {
                return $this->result(
                    'deployment_runtime',
                    'Motor de despliegue',
                    'error',
                    'El usuario del scheduler no puede escribir el checkout o el almacenamiento privado.',
                    implode(', ', $notWritable)
                );
            }

            return $this->result(
                'deployment_runtime',
                'Motor de despliegue',
                'ok',
                'El servidor puede ejecutar despliegues autónomos.',
                'main · scheduler'
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->result('deployment_runtime', 'Motor de despliegue', 'error', 'No fue posible ejecutar Git desde Laravel.');
        }
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: ?string}
     */
    private function result(string $id, string $label, string $status, string $summary, ?string $details = null): array
    {
        return compact('id', 'label', 'status', 'summary', 'details');
    }
}
