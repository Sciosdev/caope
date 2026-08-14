<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use InvalidArgumentException;
use ZipArchive;

final class ProductionSecurityAudit
{
    public const PROFILE_PRODUCTION = 'production';

    public const PROFILE_STAGING = 'staging';

    /**
     * @return list<array{id: string, label: string, status: string, summary: string, details: null}>
     */
    public function run(string $profile = self::PROFILE_PRODUCTION): array
    {
        if (! in_array($profile, [self::PROFILE_PRODUCTION, self::PROFILE_STAGING], true)) {
            throw new InvalidArgumentException('Perfil de auditoría desconocido.');
        }

        return [
            $this->environment($profile),
            $this->debugMode(),
            $this->applicationKey(),
            $this->applicationUrl($profile),
            $this->secureSessionCookie(),
            $this->revocableEncryptedSessions(),
            $this->encryptedBackups(),
        ];
    }

    /**
     * @return list<array{id: string, label: string, status: string, summary: string, details: null}>
     */
    public function errors(string $profile = self::PROFILE_PRODUCTION): array
    {
        return array_values(array_filter(
            $this->run($profile),
            static fn (array $check): bool => $check['status'] === 'error'
        ));
    }

    public function profileForCurrentEnvironment(): string
    {
        return config('app.env') === self::PROFILE_STAGING
            ? self::PROFILE_STAGING
            : self::PROFILE_PRODUCTION;
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: null}
     */
    private function environment(string $profile): array
    {
        $environment = (string) config('app.env');

        if ($profile === self::PROFILE_PRODUCTION) {
            return $environment === self::PROFILE_PRODUCTION
                ? $this->result('security_environment', 'Entorno de la aplicación', 'ok', 'APP_ENV corresponde al perfil productivo.')
                : $this->result('security_environment', 'Entorno de la aplicación', 'error', 'APP_ENV debe identificar este servidor como producción.');
        }

        if ($environment === self::PROFILE_STAGING) {
            return $this->result(
                'security_environment',
                'Entorno de la aplicación',
                'warning',
                'APP_ENV identifica este servidor como pruebas; no debe promoverse directamente a producción.'
            );
        }

        if ($environment === self::PROFILE_PRODUCTION) {
            return $this->result('security_environment', 'Entorno de la aplicación', 'ok', 'APP_ENV corresponde a un entorno desplegado.');
        }

        return $this->result(
            'security_environment',
            'Entorno de la aplicación',
            'error',
            'APP_ENV no corresponde a un entorno desplegable.'
        );
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: null}
     */
    private function debugMode(): array
    {
        return config('app.debug') === false
            ? $this->result('security_debug', 'Modo de depuración', 'ok', 'APP_DEBUG está desactivado.')
            : $this->result('security_debug', 'Modo de depuración', 'error', 'APP_DEBUG debe permanecer desactivado en servidores desplegados.');
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: null}
     */
    private function applicationKey(): array
    {
        $configuredKey = config('app.key');
        $cipher = (string) config('app.cipher');
        $key = $this->decodeApplicationKey($configuredKey);

        $compromisedHashes = config('security.compromised_app_key_hashes', []);
        $isCompromised = is_array($compromisedHashes)
            && in_array(hash('sha256', $key), $compromisedHashes, true);
        $previousKeys = config('app.previous_keys', []);
        $hasUnsafePreviousKey = ! is_array($previousKeys)
            || collect($previousKeys)->contains(function (mixed $previousKey) use ($cipher, $compromisedHashes): bool {
                $decoded = $this->decodeApplicationKey($previousKey);

                return $decoded === ''
                    || ! Encrypter::supported($decoded, $cipher)
                    || (is_array($compromisedHashes)
                        && in_array(hash('sha256', $decoded), $compromisedHashes, true));
            });

        return $key !== ''
            && Encrypter::supported($key, $cipher)
            && ! $isCompromised
            && ! $hasUnsafePreviousKey
            ? $this->result('security_app_key', 'Clave de la aplicación', 'ok', 'APP_KEY está configurada con un formato válido.')
            : $this->result('security_app_key', 'Clave de la aplicación', 'error', 'APP_KEY o APP_PREVIOUS_KEYS faltan, no son válidas o deben rotarse por exposición previa.');
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: null}
     */
    private function secureSessionCookie(): array
    {
        return config('session.secure') === true
            ? $this->result('security_session_cookie', 'Cookie de sesión', 'ok', 'La cookie de sesión está limitada a HTTPS.')
            : $this->result('security_session_cookie', 'Cookie de sesión', 'error', 'SESSION_SECURE_COOKIE debe estar activada en servidores desplegados.');
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: null}
     */
    private function revocableEncryptedSessions(): array
    {
        return config('session.driver') === 'database' && config('session.encrypt') === true
            ? $this->result('security_session_storage', 'Sesiones protegidas', 'ok', 'Las sesiones pueden revocarse y permanecen cifradas en la base de datos.')
            : $this->result('security_session_storage', 'Sesiones protegidas', 'error', 'SESSION_DRIVER debe ser database y SESSION_ENCRYPT debe estar activada.');
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: null}
     */
    private function applicationUrl(string $profile): array
    {
        $url = config('app.url');
        $components = is_string($url) ? parse_url($url) : false;
        $trustedHosts = config('security.trusted_hosts', []);
        $trustedHosts = is_array($trustedHosts)
            ? array_map(static fn (mixed $host): string => strtolower(rtrim(trim((string) $host), '.')), $trustedHosts)
            : [];
        $approvedHosts = config("security.trusted_host_profiles.{$profile}", []);
        $approvedHosts = is_array($approvedHosts)
            ? array_map(static fn (mixed $host): string => strtolower(rtrim(trim((string) $host), '.')), $approvedHosts)
            : [];
        $hostAllowlistIsSafe = $trustedHosts !== []
            && $approvedHosts !== []
            && array_diff($trustedHosts, $approvedHosts) === [];

        $isSecure = is_array($components)
            && strtolower((string) ($components['scheme'] ?? '')) === 'https'
            && is_string($components['host'] ?? null)
            && in_array(strtolower(rtrim($components['host'], '.')), $trustedHosts, true)
            && ! isset($components['user'])
            && ! isset($components['pass'])
            && ! isset($components['query'])
            && ! isset($components['fragment'])
            && $hostAllowlistIsSafe;

        return $isSecure
            ? $this->result('security_app_url', 'URL pública', 'ok', 'APP_URL usa HTTPS y un host autorizado.')
            : $this->result('security_app_url', 'URL pública', 'error', 'APP_URL debe usar HTTPS y TRUSTED_HOSTS sólo puede contener hosts públicos autorizados.');
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: null}
     */
    private function encryptedBackups(): array
    {
        $password = config('backup.backup.password');
        $applicationKey = config('app.key');
        $isConfigured = is_string($password)
            && trim($password) !== ''
            && strlen($password) >= 32
            && (! is_string($applicationKey) || ! hash_equals($applicationKey, $password))
            && defined(ZipArchive::class.'::EM_AES_256')
            && method_exists(ZipArchive::class, 'isEncryptionMethodSupported')
            && ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256, true);

        return $isConfigured
            ? $this->result('security_backup_encryption', 'Cifrado de respaldos', 'ok', 'Los archivos de respaldo tienen una contraseña de cifrado configurada.')
            : $this->result('security_backup_encryption', 'Cifrado de respaldos', 'error', 'El cifrado AES-256 de respaldos no está disponible o BACKUP_ARCHIVE_PASSWORD no cumple los requisitos.');
    }

    private function decodeApplicationKey(mixed $configuredKey): string
    {
        $key = is_string($configuredKey) ? trim($configuredKey) : '';

        if (! str_starts_with($key, 'base64:')) {
            return $key;
        }

        $decoded = base64_decode(substr($key, 7), true);

        return is_string($decoded) ? $decoded : '';
    }

    /**
     * @return array{id: string, label: string, status: string, summary: string, details: null}
     */
    private function result(string $id, string $label, string $status, string $summary): array
    {
        return compact('id', 'label', 'status', 'summary') + ['details' => null];
    }
}
