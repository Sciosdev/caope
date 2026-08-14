#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIRECTORY="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPOSITORY_ROOT="$(cd -- "${SCRIPT_DIRECTORY}/.." && pwd)"
APPLICATION_ROOT="${REPOSITORY_ROOT}/backend"
LOCK_DIRECTORY="${REPOSITORY_ROOT}/.deploy-lock"
COMPOSER_TOOLS_DIRECTORY="${APPLICATION_ROOT}/storage/app/tools"
COMPOSER_INSTALLER_PATH="${COMPOSER_TOOLS_DIRECTORY}/composer-setup.php"
COMPOSER_SIGNATURE_PATH="${COMPOSER_TOOLS_DIRECTORY}/composer-setup.sig"
LOCAL_COMPOSER_PATH="${COMPOSER_TOOLS_DIRECTORY}/composer.phar"
UNCACHED_CONFIG_PATH="${APPLICATION_ROOT}/bootstrap/cache/.caope-uncached-config-$$-${RANDOM}.php"
APP_WAS_DOWN=0
LOCK_ACQUIRED=0
PHP_BIN=''
PHP_VERSION=''
COMPOSER_COMMAND=()

fail() {
    echo "ERROR: $1" >&2
    exit 1
}

report_unexpected_error() {
    local exit_code="$1"
    local line_number="$2"

    echo "ERROR: El bootstrap falló en la etapa interna ${line_number} (código ${exit_code})." >&2
    exit "${exit_code}"
}

run_without_config_cache() {
    APP_CONFIG_CACHE="${UNCACHED_CONFIG_PATH}" "$@"
}

cleanup() {
    local exit_code="$1"

    trap - EXIT INT TERM
    set +e
    rm -f -- \
        "${COMPOSER_INSTALLER_PATH}" \
        "${COMPOSER_SIGNATURE_PATH}" \
        "${UNCACHED_CONFIG_PATH}"

    if [[ "${APP_WAS_DOWN}" -eq 1 && -n "${PHP_BIN}" ]]; then
        cd -- "${APPLICATION_ROOT}" && "${PHP_BIN}" artisan up
    fi

    if [[ "${LOCK_ACQUIRED}" -eq 1 ]]; then
        rmdir -- "${LOCK_DIRECTORY}" 2>/dev/null
    fi

    exit "${exit_code}"
}

download_file() {
    local url="$1"
    local destination="$2"

    if "${PHP_BIN}" -r '
        $contents = @file_get_contents($argv[1]);

        if ($contents === false || file_put_contents($argv[2], $contents, LOCK_EX) === false) {
            exit(1);
        }
    ' "${url}" "${destination}"; then
        return 0
    fi

    local curl_bin
    curl_bin="$(command -v curl 2>/dev/null || true)"

    [[ -n "${curl_bin}" ]] || return 1

    "${curl_bin}" \
        --fail \
        --location \
        --silent \
        --show-error \
        --max-time 60 \
        --output "${destination}" \
        "${url}"
}

trap 'cleanup $?' EXIT
trap 'report_unexpected_error $? $LINENO' ERR
trap 'exit 130' INT
trap 'exit 143' TERM

[[ "${REPOSITORY_ROOT}" != '/' ]] || fail 'La raíz detectada para CAOPE no es segura.'
[[ -f "${APPLICATION_ROOT}/artisan" ]] || fail 'La instalación no contiene backend/artisan.'
[[ -f "${APPLICATION_ROOT}/composer.json" ]] || fail 'La instalación no contiene backend/composer.json.'
[[ -f "${APPLICATION_ROOT}/.env" ]] || fail 'Falta backend/.env; conserva el archivo de entorno antes de desplegar.'

PHP_CANDIDATES=()

if [[ -n "${CAOPE_PHP_BIN:-}" ]]; then
    PHP_CANDIDATES+=("${CAOPE_PHP_BIN}")
fi

for command_name in ea-php83 php83 php8.3 php ea-php84 php84 php8.4; do
    candidate="$(command -v -- "${command_name}" 2>/dev/null || true)"

    if [[ -n "${candidate}" ]]; then
        PHP_CANDIDATES+=("${candidate}")
    fi
done

shopt -s nullglob
FILESYSTEM_PHP_CANDIDATES=(
    /opt/cpanel/ea-php*/root/usr/bin/php
    /usr/local/bin/ea-php*
    /usr/local/bin/php*
    /usr/bin/php*
)
shopt -u nullglob
PHP_CANDIDATES+=("${FILESYSTEM_PHP_CANDIDATES[@]}")

for candidate in "${PHP_CANDIDATES[@]}"; do
    [[ -x "${candidate}" ]] || continue

    if detected_version="$("${candidate}" -r '
        if (PHP_SAPI !== "cli" || version_compare(PHP_VERSION, "8.3.0", "<")) {
            exit(1);
        }

        echo PHP_VERSION;
    ' 2>/dev/null)"; then
        PHP_BIN="${candidate}"
        PHP_VERSION="${detected_version}"
        break
    fi
done

[[ -n "${PHP_BIN}" ]] || fail 'No se encontró PHP CLI 8.3 o posterior.'
echo "PHP ${PHP_VERSION} detectado en ${PHP_BIN}."

SECURITY_PROFILE="${CAOPE_SECURITY_PROFILE:-auto}"

[[ "${SECURITY_PROFILE}" == 'production' \
    || "${SECURITY_PROFILE}" == 'staging' \
    || "${SECURITY_PROFILE}" == 'auto' ]] || \
    fail 'El perfil de seguridad del despliegue no es válido.'

echo 'Validando extensiones PHP requeridas.'

if ! PHP_MODULES="$("${PHP_BIN}" -m 2>/dev/null)"; then
    fail 'PHP no pudo enumerar las extensiones disponibles.'
fi

REQUIRED_PHP_EXTENSIONS=(ctype fileinfo json mbstring openssl pdo tokenizer zip)
MISSING_PHP_EXTENSIONS=()

for required_extension in "${REQUIRED_PHP_EXTENSIONS[@]}"; do
    if ! grep -Fxiq -- "${required_extension}" <<< "${PHP_MODULES}"; then
        MISSING_PHP_EXTENSIONS+=("${required_extension}")
    fi
done

if (( ${#MISSING_PHP_EXTENSIONS[@]} > 0 )); then
    missing_extensions="$(IFS=,; echo "${MISSING_PHP_EXTENSIONS[*]}")"
    fail "PHP no tiene las extensiones requeridas: ${missing_extensions}."
fi

echo 'Extensiones PHP requeridas disponibles.'

if ! mkdir -- "${LOCK_DIRECTORY}" 2>/dev/null; then
    fail 'Ya existe otro despliegue de CAOPE en curso.'
fi
LOCK_ACQUIRED=1

if ! mkdir -p -- "${COMPOSER_TOOLS_DIRECTORY}"; then
    fail 'No fue posible preparar el almacenamiento privado de Composer.'
fi

[[ ! -e "${UNCACHED_CONFIG_PATH}" ]] || \
    fail 'La ruta temporal para omitir la caché de configuración ya existe.'

GLOBAL_COMPOSER_BIN="$(command -v composer 2>/dev/null || true)"

if [[ -n "${GLOBAL_COMPOSER_BIN}" ]] \
    && "${PHP_BIN}" "${GLOBAL_COMPOSER_BIN}" --version >/dev/null 2>&1; then
    COMPOSER_COMMAND=("${PHP_BIN}" "${GLOBAL_COMPOSER_BIN}")
elif [[ -f "${LOCAL_COMPOSER_PATH}" ]] \
    && "${PHP_BIN}" "${LOCAL_COMPOSER_PATH}" --version >/dev/null 2>&1; then
    COMPOSER_COMMAND=("${PHP_BIN}" "${LOCAL_COMPOSER_PATH}")
else
    echo 'Composer no está disponible; se preparará una copia privada verificada.'
    rm -f -- "${LOCAL_COMPOSER_PATH}"

    download_file 'https://composer.github.io/installer.sig' "${COMPOSER_SIGNATURE_PATH}" || \
        fail 'No fue posible descargar la firma oficial de Composer.'
    download_file 'https://getcomposer.org/installer' "${COMPOSER_INSTALLER_PATH}" || \
        fail 'No fue posible descargar el instalador oficial de Composer.'

    # The PHP snippet is intentionally single-quoted so Bash cannot expand it.
    # shellcheck disable=SC2016
    "${PHP_BIN}" -r '
        $expected = strtolower(trim((string) @file_get_contents($argv[1])));
        $actual = strtolower((string) @hash_file("sha384", $argv[2]));

        if (preg_match("/^[a-f0-9]{96}$/", $expected) !== 1
            || ! hash_equals($expected, $actual)) {
            fwrite(STDERR, "La firma del instalador de Composer no coincide.\n");
            exit(1);
        }
    ' "${COMPOSER_SIGNATURE_PATH}" "${COMPOSER_INSTALLER_PATH}"

    "${PHP_BIN}" "${COMPOSER_INSTALLER_PATH}" \
        --install-dir="${COMPOSER_TOOLS_DIRECTORY}" \
        --filename=composer.phar \
        --quiet \
        --2
    rm -f -- "${COMPOSER_INSTALLER_PATH}" "${COMPOSER_SIGNATURE_PATH}"

    [[ -f "${LOCAL_COMPOSER_PATH}" ]] || \
        fail 'Composer no pudo instalarse en el almacenamiento privado.'
    "${PHP_BIN}" "${LOCAL_COMPOSER_PATH}" --version >/dev/null || \
        fail 'La copia privada de Composer no es ejecutable.'

    COMPOSER_COMMAND=("${PHP_BIN}" "${LOCAL_COMPOSER_PATH}")
fi

cd -- "${APPLICATION_ROOT}"

if [[ -f vendor/autoload.php ]]; then
    echo 'Ejecutando la auditoría de seguridad previa.'
    run_without_config_cache "${PHP_BIN}" artisan caope:security-audit \
        --profile="${SECURITY_PROFILE}" \
        --no-interaction || fail 'La auditoría de seguridad previa no fue aprobada.'
    echo 'Instalación existente detectada; se creará un respaldo de la base de datos.'
    run_without_config_cache "${PHP_BIN}" artisan backup:run --only-db --no-interaction || \
        fail 'No fue posible crear el respaldo previo al despliegue.'
    "${PHP_BIN}" artisan down --retry=60
    APP_WAS_DOWN=1
else
    echo 'Primera instalación detectada; el respaldo previo no aplica.'
fi

"${COMPOSER_COMMAND[@]}" install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

"${COMPOSER_COMMAND[@]}" check-platform-reqs --no-dev

if [[ "${APP_WAS_DOWN}" -eq 0 ]]; then
    echo 'Ejecutando la auditoría de seguridad previa.'
    run_without_config_cache "${PHP_BIN}" artisan caope:security-audit \
        --profile="${SECURITY_PROFILE}" \
        --no-interaction || fail 'La auditoría de seguridad previa no fue aprobada.'
    "${PHP_BIN}" artisan down --retry=60
    APP_WAS_DOWN=1
fi

"${PHP_BIN}" artisan migrate --force
"${PHP_BIN}" artisan optimize:clear
"${PHP_BIN}" artisan config:cache
"${PHP_BIN}" artisan view:cache
"${PHP_BIN}" artisan storage:link --force
"${PHP_BIN}" artisan queue:restart

DEPLOYED_SHA="$(git -C "${REPOSITORY_ROOT}" rev-parse HEAD 2>/dev/null || true)"
[[ "${DEPLOYED_SHA}" =~ ^[a-f0-9]{40}$ ]] || \
    fail 'No fue posible identificar la revisión Git instalada por cPanel.'

VERSION_MARKER_PATH="${APPLICATION_ROOT}/storage/app/deployment/version.json"
DEPLOYED_AT="$("${PHP_BIN}" -r 'echo gmdate("Y-m-d\\TH:i:s\\Z");')"
mkdir -p -- "$(dirname -- "${VERSION_MARKER_PATH}")"

# shellcheck disable=SC2016
"${PHP_BIN}" -r '
    $payload = json_encode([
        "sha" => strtolower($argv[2]),
        "deployed_at" => $argv[3],
        "request_id" => "cpanel-bootstrap",
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
    $temporaryPath = $argv[1].".tmp.".getmypid();

    if (file_put_contents($temporaryPath, $payload, LOCK_EX) === false
        || ! rename($temporaryPath, $argv[1])) {
        @unlink($temporaryPath);
        fwrite(STDERR, "No fue posible publicar el marcador de versión.\n");
        exit(1);
    }

    @chmod($argv[1], 0600);
' "${VERSION_MARKER_PATH}" "${DEPLOYED_SHA}" "${DEPLOYED_AT}"

"${PHP_BIN}" artisan up
APP_WAS_DOWN=0

echo 'Preparación de CAOPE completada.'
