#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIRECTORY="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPOSITORY_ROOT="$(cd -- "${SCRIPT_DIRECTORY}/.." && pwd)"
APPLICATION_ROOT="${REPOSITORY_ROOT}/backend"
LOCK_DIRECTORY="${REPOSITORY_ROOT}/.deploy-lock"
EXPECTED_MARKER_PATH="${APPLICATION_ROOT}/storage/app/deployment/expected.json"
VERSION_MARKER_PATH="${APPLICATION_ROOT}/storage/app/deployment/version.json"
COMPOSER_TOOLS_DIRECTORY="${APPLICATION_ROOT}/storage/app/tools"
COMPOSER_INSTALLER_PATH="${COMPOSER_TOOLS_DIRECTORY}/composer-setup.php"
LOCAL_COMPOSER_PATH="${COMPOSER_TOOLS_DIRECTORY}/composer.phar"
UNCACHED_CONFIG_PATH="${APPLICATION_ROOT}/bootstrap/cache/.caope-uncached-config-$$-${RANDOM}.php"
APP_WAS_DOWN=0
LOCK_ACQUIRED=0
CHECKOUT_UPDATED=0
ORIGINAL_SHA=''
COMPOSER_COMMAND=()

fail() {
    echo "ERROR: $1" >&2
    exit 1
}

report_unexpected_error() {
    local exit_code="$1"
    local line_number="$2"

    echo "ERROR: El despliegue falló en la etapa interna ${line_number} (código ${exit_code})." >&2
    exit "${exit_code}"
}

run_without_config_cache() {
    APP_CONFIG_CACHE="${UNCACHED_CONFIG_PATH}" "$@"
}

normalize_runtime_permissions() {
    [[ "$(id -u)" -eq 0 ]] || return 0

    local runtime_group="${CAOPE_RUNTIME_GROUP:-}"

    if [[ -z "${runtime_group}" ]]; then
        runtime_group="$(stat -c '%G' "${APPLICATION_ROOT}/storage/logs" 2>/dev/null || true)"
    fi

    if [[ -z "${runtime_group}" || "${runtime_group}" == 'root' ]]; then
        if getent group www-data >/dev/null 2>&1; then
            runtime_group='www-data'
        else
            echo 'ERROR: Define CAOPE_RUNTIME_GROUP con el grupo del servidor web.' >&2
            return 1
        fi
    fi

    local runtime_paths=(
        "${APPLICATION_ROOT}/bootstrap/cache"
        "${APPLICATION_ROOT}/storage/app/deployment"
        "${APPLICATION_ROOT}/storage/framework/cache"
        "${APPLICATION_ROOT}/storage/framework/sessions"
        "${APPLICATION_ROOT}/storage/framework/views"
        "${APPLICATION_ROOT}/storage/logs"
    )
    local runtime_path

    for runtime_path in "${runtime_paths[@]}"; do
        mkdir -p -- "${runtime_path}" || return 1
        chgrp -R -- "${runtime_group}" "${runtime_path}" || return 1
        find "${runtime_path}" -type d -exec chmod 2770 {} + || return 1
        find "${runtime_path}" -type f -exec chmod 0660 {} + || return 1
    done
}

resolve_executable() {
    local candidate="$1"

    if [[ "${candidate}" == */* ]]; then
        [[ -x "${candidate}" ]] || return 1
        printf '%s\n' "${candidate}"
        return 0
    fi

    command -v -- "${candidate}"
}

rollback_failed_deployment() {
    [[ "${CHECKOUT_UPDATED}" -eq 1 ]] || return 0
    [[ "${CAOPE_REQUIRE_CLEAN_CHECKOUT:-0}" == '1' ]] || return 0
    [[ "${ORIGINAL_SHA}" =~ ^[a-f0-9]{40}$ ]] || return 1

    echo "Revirtiendo automáticamente el checkout a ${ORIGINAL_SHA}." >&2
    cd -- "${REPOSITORY_ROOT}" || return 1
    git reset --hard "${ORIGINAL_SHA}" || return 1
    cd -- "${APPLICATION_ROOT}" || return 1

    if (( ${#COMPOSER_COMMAND[@]} > 0 )); then
        "${COMPOSER_COMMAND[@]}" install \
            --no-dev \
            --prefer-dist \
            --no-interaction \
            --no-progress \
            --optimize-autoloader || return 1
    fi

    "${PHP_BIN}" artisan optimize:clear || return 1
    "${PHP_BIN}" artisan config:cache || return 1
    "${PHP_BIN}" artisan view:cache || return 1
    "${PHP_BIN}" artisan queue:restart || return 1

    local rolled_back_at
    rolled_back_at="$("${PHP_BIN}" -r 'echo gmdate("Y-m-d\\TH:i:s\\Z");')" || return 1

    "${PHP_BIN}" -r '
        $payload = json_encode([
            "sha" => strtolower($argv[2]),
            "deployed_at" => $argv[3],
            "request_id" => "automatic-rollback",
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
        $temporaryPath = $argv[1].".tmp.".getmypid();

        if (file_put_contents($temporaryPath, $payload, LOCK_EX) === false
            || ! rename($temporaryPath, $argv[1])) {
            @unlink($temporaryPath);
            exit(1);
        }

        @chmod($argv[1], 0660);
    ' "${VERSION_MARKER_PATH}" "${ORIGINAL_SHA}" "${rolled_back_at}" || return 1

    echo "Rollback automático completado en ${ORIGINAL_SHA}." >&2
}

cleanup() {
    local exit_code="$1"

    trap - EXIT INT TERM
    set +e
    rm -f -- "${COMPOSER_INSTALLER_PATH}" "${UNCACHED_CONFIG_PATH}"

    if [[ "${exit_code}" -ne 0 ]]; then
        rollback_failed_deployment || \
            echo 'ERROR: El rollback automático no pudo completarse.' >&2
    fi

    normalize_runtime_permissions || \
        echo 'ADVERTENCIA: No fue posible normalizar los permisos de ejecución.' >&2

    if [[ "${APP_WAS_DOWN}" -eq 1 ]]; then
        cd -- "${APPLICATION_ROOT}" && "${PHP_BIN}" artisan up
    fi

    if [[ "${LOCK_ACQUIRED}" -eq 1 ]]; then
        rm -f -- "${EXPECTED_MARKER_PATH}"
        rmdir -- "${LOCK_DIRECTORY}" 2>/dev/null
    fi

    exit "${exit_code}"
}

trap 'cleanup $?' EXIT
trap 'report_unexpected_error $? $LINENO' ERR
trap 'exit 130' INT
trap 'exit 143' TERM

PHP_BIN="$(resolve_executable "${CAOPE_PHP_BIN:-php}")" || \
    fail 'No se encontró un ejecutable de PHP disponible para el despliegue.'
SECURITY_PROFILE="${CAOPE_SECURITY_PROFILE:-auto}"

[[ "${SECURITY_PROFILE}" == 'production' \
    || "${SECURITY_PROFILE}" == 'staging' \
    || "${SECURITY_PROFILE}" == 'auto' ]] || \
    fail 'El perfil de seguridad del despliegue no es válido.'

command -v git >/dev/null 2>&1 || fail 'Git no está disponible para el despliegue.'

[[ -f "${APPLICATION_ROOT}/artisan" ]] || \
    fail 'La instalación no contiene backend/artisan.'

normalize_runtime_permissions || \
    fail 'No fue posible preparar los permisos de ejecución para el servidor web.'

if ! mkdir -- "${LOCK_DIRECTORY}" 2>/dev/null; then
    fail 'Ya existe otro despliegue de CAOPE en curso.'
fi
LOCK_ACQUIRED=1

[[ ! -e "${UNCACHED_CONFIG_PATH}" ]] || \
    fail 'La ruta temporal para omitir la caché de configuración ya existe.'

[[ -r "${EXPECTED_MARKER_PATH}" ]] || \
    fail 'No existe una autorización vigente de GitHub para este despliegue.'

# The PHP snippet is intentionally single-quoted so Bash cannot expand it.
# shellcheck disable=SC2016
EXPECTED_SHA="$("${PHP_BIN}" -r '
    $marker = json_decode((string) @file_get_contents($argv[1]), true);
    $sha = is_array($marker) ? (string) ($marker["sha"] ?? "") : "";
    $requestId = is_array($marker) ? ($marker["request_id"] ?? null) : null;
    $expiresAt = is_array($marker) ? ($marker["expires_at"] ?? null) : null;

    $valid = preg_match("/^[a-f0-9]{40}$/i", $sha) === 1
        && is_string($requestId)
        && trim($requestId) !== ""
        && strlen($requestId) <= 100
        && is_int($expiresAt)
        && $expiresAt > time();

    if (! $valid) {
        fwrite(STDERR, "La autorización de despliegue no es válida o ya expiró.\n");
        exit(1);
    }

    echo strtolower($sha);
' "${EXPECTED_MARKER_PATH}")"

CURRENT_BRANCH="$(git -C "${REPOSITORY_ROOT}" symbolic-ref --quiet --short HEAD || true)"
CURRENT_SHA="$(git -C "${REPOSITORY_ROOT}" rev-parse HEAD)"
ORIGINAL_SHA="${CURRENT_SHA}"

[[ "${CURRENT_BRANCH}" == 'main' ]] || \
    fail 'El checkout de CAOPE debe permanecer en la rama main.'

if [[ "${CAOPE_REQUIRE_CLEAN_CHECKOUT:-0}" == '1' ]] \
    && [[ -n "$(git -C "${REPOSITORY_ROOT}" status --porcelain --untracked-files=no)" ]]; then
    fail 'Producción contiene cambios locales en archivos versionados.'
fi

cd -- "${APPLICATION_ROOT}"
run_without_config_cache "${PHP_BIN}" artisan caope:security-audit \
    --profile="${SECURITY_PROFILE}" \
    --no-interaction || fail 'La auditoría de seguridad previa no fue aprobada.'

cd -- "${REPOSITORY_ROOT}"

GIT_TERMINAL_PROMPT=0 git -C "${REPOSITORY_ROOT}" fetch --no-tags origin main
REMOTE_SHA="$(git -C "${REPOSITORY_ROOT}" rev-parse refs/remotes/origin/main)"

[[ "${REMOTE_SHA}" == "${EXPECTED_SHA}" ]] || \
    fail 'origin/main no coincide con la revisión exacta validada por GitHub.'

if ! git -C "${REPOSITORY_ROOT}" merge-base --is-ancestor "${CURRENT_SHA}" "${EXPECTED_SHA}"; then
    fail 'El checkout no puede avanzar de forma lineal hasta la revisión validada.'
fi

GLOBAL_COMPOSER_BIN="$(command -v composer 2>/dev/null || true)"

if [[ -n "${GLOBAL_COMPOSER_BIN}" ]] \
    && "${PHP_BIN}" "${GLOBAL_COMPOSER_BIN}" --version >/dev/null 2>&1; then
    COMPOSER_COMMAND=("${PHP_BIN}" "${GLOBAL_COMPOSER_BIN}")
elif [[ -f "${LOCAL_COMPOSER_PATH}" ]] \
    && "${PHP_BIN}" "${LOCAL_COMPOSER_PATH}" --version >/dev/null 2>&1; then
    COMPOSER_COMMAND=("${PHP_BIN}" "${LOCAL_COMPOSER_PATH}")
else
    echo 'Composer no está disponible; se preparará una copia privada verificada.'
    mkdir -p -- "${COMPOSER_TOOLS_DIRECTORY}"
    rm -f -- "${LOCAL_COMPOSER_PATH}"

    # The PHP snippets are intentionally single-quoted so Bash cannot expand them.
    # shellcheck disable=SC2016
    EXPECTED_COMPOSER_CHECKSUM="$("${PHP_BIN}" -r '
        $checksum = @file_get_contents("https://composer.github.io/installer.sig");

        if ($checksum === false) {
            fwrite(STDERR, "No fue posible descargar la firma oficial de Composer.\n");
            exit(1);
        }

        $checksum = strtolower(trim($checksum));

        if (preg_match("/^[a-f0-9]{96}$/", $checksum) !== 1) {
            fwrite(STDERR, "La firma oficial de Composer no tiene un formato válido.\n");
            exit(1);
        }

        echo $checksum;
    ')"

    # shellcheck disable=SC2016
    "${PHP_BIN}" -r '
        $installer = @file_get_contents("https://getcomposer.org/installer");

        if ($installer === false || file_put_contents($argv[1], $installer, LOCK_EX) === false) {
            fwrite(STDERR, "No fue posible descargar el instalador oficial de Composer.\n");
            exit(1);
        }
    ' "${COMPOSER_INSTALLER_PATH}"

    # shellcheck disable=SC2016
    ACTUAL_COMPOSER_CHECKSUM="$("${PHP_BIN}" -r '
        echo strtolower(hash_file("sha384", $argv[1]));
    ' "${COMPOSER_INSTALLER_PATH}")"

    [[ "${EXPECTED_COMPOSER_CHECKSUM}" == "${ACTUAL_COMPOSER_CHECKSUM}" ]] || \
        fail 'La firma del instalador de Composer no coincide.'

    "${PHP_BIN}" "${COMPOSER_INSTALLER_PATH}" \
        --install-dir="${COMPOSER_TOOLS_DIRECTORY}" \
        --filename=composer.phar \
        --quiet \
        --2
    rm -f -- "${COMPOSER_INSTALLER_PATH}"

    [[ -f "${LOCAL_COMPOSER_PATH}" ]] || \
        fail 'Composer no pudo instalarse en el almacenamiento privado.'
    "${PHP_BIN}" "${LOCAL_COMPOSER_PATH}" --version >/dev/null || \
        fail 'La copia privada de Composer no es ejecutable.'

    COMPOSER_COMMAND=("${PHP_BIN}" "${LOCAL_COMPOSER_PATH}")
fi

cd -- "${APPLICATION_ROOT}"
run_without_config_cache "${PHP_BIN}" artisan backup:run --only-db --no-interaction || \
    fail 'No fue posible crear el respaldo previo al despliegue.'
"${PHP_BIN}" artisan down --retry=60
APP_WAS_DOWN=1

cd -- "${REPOSITORY_ROOT}"
GIT_MERGE_AUTOEDIT=no git merge --ff-only refs/remotes/origin/main
CURRENT_SHA="$(git rev-parse HEAD)"

[[ "${CURRENT_SHA}" == "${EXPECTED_SHA}" ]] || \
    fail 'El checkout no terminó en la revisión exacta validada por GitHub.'
CHECKOUT_UPDATED=1

cd -- "${APPLICATION_ROOT}"
"${COMPOSER_COMMAND[@]}" install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

"${PHP_BIN}" artisan migrate --force
"${PHP_BIN}" artisan optimize:clear
"${PHP_BIN}" artisan config:cache
"${PHP_BIN}" artisan view:cache
"${PHP_BIN}" artisan storage:link --force
"${PHP_BIN}" artisan queue:restart

DEPLOYED_AT="$("${PHP_BIN}" -r 'echo gmdate("Y-m-d\\TH:i:s\\Z");')"
mkdir -p -- "$(dirname -- "${VERSION_MARKER_PATH}")"

# Writing the marker with PHP preserves the exact audited request identifier
# without exposing it to shell interpolation.
# shellcheck disable=SC2016
"${PHP_BIN}" -r '
    $authorization = json_decode((string) @file_get_contents($argv[1]), true);
    $requestId = is_array($authorization) ? ($authorization["request_id"] ?? null) : null;

    if (! is_string($requestId) || trim($requestId) === "" || strlen($requestId) > 100) {
        fwrite(STDERR, "La autorización no contiene un identificador de solicitud válido.\n");
        exit(1);
    }

    $payload = json_encode([
        "sha" => strtolower($argv[3]),
        "deployed_at" => $argv[4],
        "request_id" => $requestId,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
    $temporaryPath = $argv[2].".tmp.".getmypid();

    if (file_put_contents($temporaryPath, $payload, LOCK_EX) === false
        || ! rename($temporaryPath, $argv[2])) {
        @unlink($temporaryPath);
        fwrite(STDERR, "No fue posible publicar el marcador de versión.\n");
        exit(1);
    }
' "${EXPECTED_MARKER_PATH}" "${VERSION_MARKER_PATH}" "${CURRENT_SHA}" "${DEPLOYED_AT}"

"${PHP_BIN}" artisan up
APP_WAS_DOWN=0

normalize_runtime_permissions || \
    fail 'No fue posible restaurar los permisos de ejecución para el servidor web.'

echo "Despliegue completado en ${CURRENT_SHA}."
