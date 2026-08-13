#!/usr/bin/env bash

set -Eeuo pipefail

REPOSITORY_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
APPLICATION_ROOT="${REPOSITORY_ROOT}/backend"
PHP_BIN="/usr/local/bin/ea-php83"
LOCK_DIRECTORY="${REPOSITORY_ROOT}/.deploy-lock"
EXPECTED_MARKER_PATH="${APPLICATION_ROOT}/storage/app/deployment/expected.json"
COMPOSER_TOOLS_DIRECTORY="${APPLICATION_ROOT}/storage/app/tools"
COMPOSER_INSTALLER_PATH="${COMPOSER_TOOLS_DIRECTORY}/composer-setup.php"
LOCAL_COMPOSER_PATH="${COMPOSER_TOOLS_DIRECTORY}/composer.phar"
APP_WAS_DOWN=0
LOCK_ACQUIRED=0

cleanup() {
    rm -f "${COMPOSER_INSTALLER_PATH}"

    if [[ "${APP_WAS_DOWN}" -eq 1 ]]; then
        cd "${APPLICATION_ROOT}"
        "${PHP_BIN}" artisan up || true
    fi

    if [[ "${LOCK_ACQUIRED}" -eq 1 ]]; then
        rm -f "${EXPECTED_MARKER_PATH}"
        rmdir "${LOCK_DIRECTORY}" 2>/dev/null || true
    fi
}

trap cleanup EXIT INT TERM

if [[ ! -x "${PHP_BIN}" ]]; then
    echo "PHP 8.3 de cPanel no esta disponible en ${PHP_BIN}."
    exit 1
fi

if ! mkdir "${LOCK_DIRECTORY}" 2>/dev/null; then
    echo "Ya existe otro despliegue de CAOPE en curso."
    exit 1
fi
LOCK_ACQUIRED=1

if [[ ! -f "${APPLICATION_ROOT}/artisan" ]]; then
    echo "La ruta desplegada no contiene backend/artisan."
    exit 1
fi

if [[ ! -r "${EXPECTED_MARKER_PATH}" ]]; then
    echo "No existe una autorización vigente de GitHub para este despliegue."
    exit 1
fi

# The PHP snippet is intentionally single-quoted so Bash cannot expand it.
# shellcheck disable=SC2016
EXPECTED_SHA="$("${PHP_BIN}" -r '
    $marker = json_decode((string) @file_get_contents($argv[1]), true);
    $sha = is_array($marker) ? (string) ($marker["sha"] ?? "") : "";
    $expiresAt = is_array($marker) ? (int) ($marker["expires_at"] ?? 0) : 0;

    if (preg_match("/^[a-f0-9]{40}$/i", $sha) !== 1 || $expiresAt < time()) {
        fwrite(STDERR, "La autorización de despliegue no es válida o ya expiró.\n");
        exit(1);
    }

    echo strtolower($sha);
' "${EXPECTED_MARKER_PATH}")"
CURRENT_BRANCH="$(git -C "${REPOSITORY_ROOT}" symbolic-ref --quiet --short HEAD || true)"
CURRENT_SHA="$(git -C "${REPOSITORY_ROOT}" rev-parse HEAD)"

if [[ "${CURRENT_BRANCH}" != "main" ]]; then
    echo "El checkout de cPanel debe permanecer en la rama main."
    exit 1
fi

GIT_TERMINAL_PROMPT=0 git -C "${REPOSITORY_ROOT}" fetch --no-tags origin main
REMOTE_SHA="$(git -C "${REPOSITORY_ROOT}" rev-parse refs/remotes/origin/main)"

if [[ "${REMOTE_SHA}" != "${EXPECTED_SHA}" ]]; then
    echo "origin/main no coincide con la revisión validada por GitHub."
    exit 1
fi

if ! git -C "${REPOSITORY_ROOT}" merge-base --is-ancestor "${CURRENT_SHA}" "${EXPECTED_SHA}"; then
    echo "El checkout de cPanel no puede avanzar de forma lineal hasta la revisión validada."
    exit 1
fi

COMPOSER_BIN=""
for candidate in /usr/local/bin/composer /opt/cpanel/composer/bin/composer "${LOCAL_COMPOSER_PATH}"; do
    if [[ -f "${candidate}" ]]; then
        COMPOSER_BIN="${candidate}"
        break
    fi
done

if [[ -z "${COMPOSER_BIN}" ]]; then
    echo "Composer no esta instalado globalmente; se preparara una copia privada verificada."
    mkdir -p "${COMPOSER_TOOLS_DIRECTORY}"

    # The PHP snippets are intentionally single-quoted so Bash cannot expand them.
    # shellcheck disable=SC2016
    EXPECTED_COMPOSER_CHECKSUM="$("${PHP_BIN}" -r '
        $checksum = @file_get_contents("https://composer.github.io/installer.sig");

        if ($checksum === false) {
            fwrite(STDERR, "No fue posible descargar la firma oficial de Composer.\n");
            exit(1);
        }

        echo trim($checksum);
    ')"

    # shellcheck disable=SC2016
    "${PHP_BIN}" -r '
        $installer = @file_get_contents("https://getcomposer.org/installer");

        if ($installer === false || file_put_contents($argv[1], $installer) === false) {
            fwrite(STDERR, "No fue posible descargar el instalador oficial de Composer.\n");
            exit(1);
        }
    ' "${COMPOSER_INSTALLER_PATH}"

    # shellcheck disable=SC2016
    ACTUAL_COMPOSER_CHECKSUM="$("${PHP_BIN}" -r '
        echo hash_file("sha384", $argv[1]);
    ' "${COMPOSER_INSTALLER_PATH}")"

    if [[ "${EXPECTED_COMPOSER_CHECKSUM}" != "${ACTUAL_COMPOSER_CHECKSUM}" ]]; then
        echo "La firma del instalador de Composer no coincide."
        exit 1
    fi

    "${PHP_BIN}" "${COMPOSER_INSTALLER_PATH}" \
        --install-dir="${COMPOSER_TOOLS_DIRECTORY}" \
        --filename=composer.phar \
        --quiet \
        --2
    rm -f "${COMPOSER_INSTALLER_PATH}"

    if [[ ! -f "${LOCAL_COMPOSER_PATH}" ]]; then
        echo "Composer no pudo instalarse en el almacenamiento privado."
        exit 1
    fi

    COMPOSER_BIN="${LOCAL_COMPOSER_PATH}"
fi

cd "${APPLICATION_ROOT}"
"${PHP_BIN}" artisan backup:run --only-db --no-interaction
"${PHP_BIN}" artisan down --retry=60
APP_WAS_DOWN=1

cd "${REPOSITORY_ROOT}"
GIT_MERGE_AUTOEDIT=no git merge --ff-only refs/remotes/origin/main
CURRENT_SHA="$(git rev-parse HEAD)"

if [[ "${CURRENT_SHA}" != "${EXPECTED_SHA}" ]]; then
    echo "El checkout no terminó en la revisión validada por GitHub."
    exit 1
fi

cd "${APPLICATION_ROOT}"
"${PHP_BIN}" "${COMPOSER_BIN}" install \
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

DEPLOYED_AT="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"

mkdir -p storage/app/deployment
MARKER_PATH="storage/app/deployment/version.json"
TEMPORARY_MARKER_PATH="${MARKER_PATH}.tmp"
printf '{"sha":"%s","deployed_at":"%s","request_id":"cpanel-staging"}\n' \
    "${CURRENT_SHA}" \
    "${DEPLOYED_AT}" \
    > "${TEMPORARY_MARKER_PATH}"
mv "${TEMPORARY_MARKER_PATH}" "${MARKER_PATH}"

"${PHP_BIN}" artisan up
APP_WAS_DOWN=0

cleanup
trap - EXIT INT TERM
