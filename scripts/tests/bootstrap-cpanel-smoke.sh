#!/usr/bin/env bash

set -Eeuo pipefail

TEST_DIRECTORY="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPOSITORY_ROOT="$(cd -- "${TEST_DIRECTORY}/../.." && pwd)"
FIXTURE_ROOT="$(mktemp -d)"
TEST_LOG="${FIXTURE_ROOT}/commands.log"

cleanup() {
    if [[ -n "${FIXTURE_ROOT:-}" \
        && -d "${FIXTURE_ROOT}" \
        && "${FIXTURE_ROOT}" == "${TMPDIR:-/tmp}/"* ]]; then
        rm -rf -- "${FIXTURE_ROOT}"
    fi
}

fail() {
    echo "ERROR: $1" >&2
    exit 1
}

assert_contains() {
    local expected="$1"
    local file="$2"

    grep -F -- "${expected}" "${file}" >/dev/null || \
        fail "No se encontró '${expected}' en ${file}."
}

trap cleanup EXIT INT TERM

mkdir -p \
    "${FIXTURE_ROOT}/scripts" \
    "${FIXTURE_ROOT}/backend/storage/app" \
    "${FIXTURE_ROOT}/backend/bootstrap/cache" \
    "${FIXTURE_ROOT}/bin"
cp -- "${REPOSITORY_ROOT}/scripts/bootstrap-cpanel.sh" "${FIXTURE_ROOT}/scripts/bootstrap-cpanel.sh"
touch "${FIXTURE_ROOT}/backend/artisan" "${FIXTURE_ROOT}/backend/.env"
printf '{}\n' > "${FIXTURE_ROOT}/backend/composer.json"

cat > "${FIXTURE_ROOT}/bin/ea-php83" <<'FAKE_PHP'
#!/usr/bin/env bash

set -Eeuo pipefail

if [[ "${1:-}" == '-r' ]]; then
    if [[ "${2:-}" == *'echo PHP_VERSION'* ]]; then
        echo '8.3.99'
    elif [[ "${2:-}" == *'gmdate'* ]]; then
        echo '2026-08-13T21:50:00Z'
    elif [[ "${2:-}" == *'cpanel-bootstrap'* ]]; then
        mkdir -p -- "$(dirname -- "${3}")"
        printf '{"sha":"%s","deployed_at":"%s","request_id":"cpanel-bootstrap"}\n' \
            "${4}" "${5}" > "${3}"
    fi
    exit 0
fi

if [[ "${1:-}" == "${CAOPE_TEST_ROOT}/bin/composer" ]]; then
    shift
    printf 'composer:%s\n' "$*" >> "${CAOPE_TEST_LOG}"
    exit 0
fi

if [[ "${1:-}" == 'artisan' ]]; then
    shift
    printf 'artisan:%s\n' "$*" >> "${CAOPE_TEST_LOG}"
    exit 0
fi

exit 1
FAKE_PHP

cat > "${FIXTURE_ROOT}/bin/composer" <<'FAKE_COMPOSER'
#!/usr/bin/env bash
exit 0
FAKE_COMPOSER

cat > "${FIXTURE_ROOT}/bin/git" <<'FAKE_GIT'
#!/usr/bin/env bash
echo 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
FAKE_GIT

chmod +x \
    "${FIXTURE_ROOT}/bin/ea-php83" \
    "${FIXTURE_ROOT}/bin/composer" \
    "${FIXTURE_ROOT}/bin/git"

run_bootstrap() {
    : > "${TEST_LOG}"

    PATH="${FIXTURE_ROOT}/bin:/usr/bin:/bin" \
    CAOPE_TEST_ROOT="${FIXTURE_ROOT}" \
    CAOPE_TEST_LOG="${TEST_LOG}" \
        /bin/bash "${FIXTURE_ROOT}/scripts/bootstrap-cpanel.sh" \
        > "${FIXTURE_ROOT}/output.log"
}

run_bootstrap
assert_contains 'PHP 8.3.99 detectado' "${FIXTURE_ROOT}/output.log"
assert_contains 'Primera instalación detectada' "${FIXTURE_ROOT}/output.log"
assert_contains 'composer:install --no-dev' "${TEST_LOG}"
assert_contains 'composer:check-platform-reqs --no-dev' "${TEST_LOG}"
assert_contains 'artisan:migrate --force' "${TEST_LOG}"
assert_contains 'artisan:up' "${TEST_LOG}"
assert_contains '"sha":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"' \
    "${FIXTURE_ROOT}/backend/storage/app/deployment/version.json"
assert_contains '"request_id":"cpanel-bootstrap"' \
    "${FIXTURE_ROOT}/backend/storage/app/deployment/version.json"

if grep -F -- 'backup:run' "${TEST_LOG}" >/dev/null; then
    fail 'La primera instalación no debe intentar un respaldo con Laravel aún incompleto.'
fi

[[ ! -e "${FIXTURE_ROOT}/.deploy-lock" ]] || fail 'El bloqueo no se liberó.'

mkdir -p "${FIXTURE_ROOT}/backend/vendor"
touch "${FIXTURE_ROOT}/backend/vendor/autoload.php"
run_bootstrap
assert_contains 'Instalación existente detectada' "${FIXTURE_ROOT}/output.log"
assert_contains 'artisan:backup:run --only-db --no-interaction' "${TEST_LOG}"

backup_line="$(grep -n -F 'artisan:backup:run' "${TEST_LOG}" | cut -d: -f1)"
down_line="$(grep -n -F 'artisan:down' "${TEST_LOG}" | cut -d: -f1)"
install_line="$(grep -n -F 'composer:install' "${TEST_LOG}" | cut -d: -f1)"

if (( backup_line >= down_line || down_line >= install_line )); then
    fail 'El respaldo y el modo mantenimiento deben preceder la instalación de dependencias.'
fi

[[ ! -e "${FIXTURE_ROOT}/.deploy-lock" ]] || fail 'El bloqueo no se liberó.'

echo 'Bootstrap cPanel smoke test: OK'
