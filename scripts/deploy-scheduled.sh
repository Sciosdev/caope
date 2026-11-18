#!/usr/bin/env bash
# PHP snippets deliberately use single quotes to prevent Bash interpolation.
# shellcheck disable=SC2016

# Parse everything before executing: checkout may replace this very script.
deployment_main() {
set -Eeuo pipefail
umask 0007
SCRIPT_DIRECTORY="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPOSITORY_ROOT="$(cd -- "${SCRIPT_DIRECTORY}/.." && pwd)"
APPLICATION_ROOT="${REPOSITORY_ROOT}/backend"
LOCK_DIRECTORY="${REPOSITORY_ROOT}/.deploy-lock"
JOURNAL_PATH="${APPLICATION_ROOT}/storage/app/deployment/recovery.json"
RESULT_PATH="${APPLICATION_ROOT}/storage/app/deployment/result.json"
EXPECTED_MARKER_PATH="${APPLICATION_ROOT}/storage/app/deployment/expected.json"
VERSION_MARKER_PATH="${APPLICATION_ROOT}/storage/app/deployment/version.json"
COMPOSER_TOOLS_DIRECTORY="${APPLICATION_ROOT}/storage/app/tools"
COMPOSER_INSTALLER_PATH="${COMPOSER_TOOLS_DIRECTORY}/composer-setup.php"
LOCAL_COMPOSER_PATH="${COMPOSER_TOOLS_DIRECTORY}/composer.phar"
UNCACHED_CONFIG_PATH="${APPLICATION_ROOT}/bootstrap/cache/.caope-uncached-config-$$-${RANDOM}.php"
LOCK_ACQUIRED=0
JOURNAL_OWNED=0
RECOVER_ONLY=0
RECOVERY_ATTEMPTED=0
RESTORED=0
INTERRUPTED=0
DEFERRED=0
ORIGINAL_SHA=''
EXPECTED_SHA=''
REQUEST_ID=''
STAGE='preflight'
RESULT='failed'
COMPOSER_COMMAND=()

# TERM allows six minutes for cleanup before killing the entire process group.
# An unresponsive command leaves a journal for the next scheduler invocation.
if [[ "${1:-}" != '--bounded-worker' ]]; then
    command -v timeout >/dev/null 2>&1 || { echo 'ERROR: GNU timeout no está disponible.' >&2; exit 1; }
    exec timeout --signal=TERM --kill-after=360 1800 /bin/bash "${SCRIPT_DIRECTORY}/deploy-scheduled.sh" --bounded-worker "$@"
fi
shift
if [[ "${1:-}" == '--recover-only' && "$#" -eq 1 ]]; then
    RECOVER_ONLY=1
elif [[ "$#" -ne 0 ]]; then
    echo 'ERROR: Argumentos de despliegue no válidos.' >&2; exit 1
fi

fail() { echo "ERROR: $1" >&2; exit 1; }
defer_deployment() { DEFERRED=1; exit 75; }
stage() { STAGE="$1"; printf 'CAOPE_STAGE=%s\n' "${STAGE}"; }
check_agent_binding() {
    if [[ ( -n "${CAOPE_AGENT_REQUEST_ID:-}" && "${CAOPE_AGENT_REQUEST_ID}" != "${REQUEST_ID}" ) \
        || ( -n "${CAOPE_AGENT_EXPECTED_SHA:-}" && "${CAOPE_AGENT_EXPECTED_SHA}" != "${EXPECTED_SHA}" ) ]]; then
        echo 'CAOPE_AUTHORIZATION=changed'
        defer_deployment
    fi
}
# Invoked indirectly by the ERR trap.
# shellcheck disable=SC2317
report_unexpected_error() {
    echo "ERROR: El despliegue falló en la etapa ${STAGE} (código $1, línea $2)." >&2
    exit "$1"
}
run_without_config_cache() { APP_CONFIG_CACHE="${UNCACHED_CONFIG_PATH}" "$@"; }
bounded() { timeout --signal=TERM --kill-after=5 240 "$@"; }

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

write_journal() {
    "${PHP_BIN}" -r '
        $payload = json_encode([
            "schema" => 1, "original_sha" => $argv[2], "target_sha" => $argv[3],
            "request_id" => $argv[4], "phase" => $argv[5],
            "clean_checkout" => $argv[6] === "1", "maintenance_owned" => true,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
        $temporary = $argv[1].".tmp.".getmypid();
        if (file_put_contents($temporary, $payload, LOCK_EX) === false
            || ! chmod($temporary, 0660) || ! rename($temporary, $argv[1])) {
            @unlink($temporary); exit(1);
        }
    ' "${JOURNAL_PATH}" "${ORIGINAL_SHA}" "${EXPECTED_SHA}" "${REQUEST_ID}" "$1" "${CLEAN_ROLLBACK}"
}

load_journal() {
    [[ -f "${JOURNAL_PATH}" && ! -L "${JOURNAL_PATH}" ]] || return 1
    local values
    values="$("${PHP_BIN}" -r '
        $j = json_decode((string) @file_get_contents($argv[1]), true);
        if (! is_array($j) || ($j["schema"] ?? null) !== 1
            || ! preg_match("/^[a-f0-9]{40}$/D", (string) ($j["original_sha"] ?? ""))
            || ! preg_match("/^[a-f0-9]{40}$/D", (string) ($j["target_sha"] ?? ""))
            || ! is_bool($j["clean_checkout"] ?? null)
            || ($j["maintenance_owned"] ?? null) !== true
            || ! is_string($j["request_id"] ?? null)
            || trim($j["request_id"]) === "" || strlen($j["request_id"]) > 100
            || preg_match("/[\\r\\n]/", $j["request_id"])
            || ! in_array($j["phase"] ?? null, ["maintenance", "checkout", "dependencies", "migrations", "cache", "verify", "rollback", "completed"], true)) {
            fwrite(STDERR, "El diario de recuperación no es válido; se conserva para revisión.\n"); exit(1);
        }
        echo $j["original_sha"]."\n".$j["target_sha"]."\n".$j["request_id"]."\n".
            ($j["clean_checkout"] ? "1" : "0")."\n".$j["phase"];
    ' "${JOURNAL_PATH}")" || return 1
    local fields=()
    mapfile -t fields <<< "${values}"
    ORIGINAL_SHA="${fields[0]}"
    EXPECTED_SHA="${fields[1]}"
    REQUEST_ID="${fields[2]}"
    CLEAN_ROLLBACK="${fields[3]}"
}

write_result() {
    [[ -n "${REQUEST_ID}" ]] || return 0
    bounded "${PHP_BIN}" -r '
        $payload = json_encode([
            "schema" => 1, "request_id" => $argv[2], "status" => $argv[3],
            "stage" => $argv[4], "finished_at" => gmdate("Y-m-d\\TH:i:s\\Z"),
            "sha" => $argv[5], "restored" => $argv[6] === "1", "interrupted" => $argv[7] === "1",
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
        $temporary = $argv[1].".tmp.".getmypid();
        if (file_put_contents($temporary, $payload, LOCK_EX) === false
            || ! chmod($temporary, 0660) || ! rename($temporary, $argv[1])) {
            @unlink($temporary); exit(1);
        }
    ' "${RESULT_PATH}" "${REQUEST_ID}" "${RESULT}" "${STAGE}" "$(git -C "${REPOSITORY_ROOT}" rev-parse HEAD 2>/dev/null || true)" "${RESTORED}" "${INTERRUPTED}"
}

resolve_existing_composer() {
    local GLOBAL_COMPOSER_BIN
    GLOBAL_COMPOSER_BIN="$(command -v composer 2>/dev/null || true)"
    if [[ -n "${GLOBAL_COMPOSER_BIN}" ]] && bounded "${PHP_BIN}" "${GLOBAL_COMPOSER_BIN}" --version >/dev/null 2>&1; then
        COMPOSER_COMMAND=("${PHP_BIN}" "${GLOBAL_COMPOSER_BIN}")
    elif [[ -f "${LOCAL_COMPOSER_PATH}" ]] && bounded "${PHP_BIN}" "${LOCAL_COMPOSER_PATH}" --version >/dev/null 2>&1; then
        COMPOSER_COMMAND=("${PHP_BIN}" "${LOCAL_COMPOSER_PATH}")
    else
        echo 'ERROR: La recuperación necesita una copia ejecutable de Composer.' >&2; return 1
    fi
}

rollback_failed_deployment() {
    RECOVERY_ATTEMPTED=1
    load_journal || return 1
    check_agent_binding
    normalize_runtime_permissions || return 1
    stage rollback
    # Composer may have left vendor unusable. Maintenance must not require
    # booting Laravel until the old dependencies have been restored.
    bounded "${PHP_BIN}" -r '
        if (is_file($argv[1])) { exit(0); }
        $payload = json_encode(["retry" => 60, "refresh" => null, "redirect" => null,
            "status" => 503, "secret" => null, "template" => null], JSON_THROW_ON_ERROR);
        $temporary = $argv[1].".tmp.".getmypid();
        if (file_put_contents($temporary, $payload, LOCK_EX) === false
            || ! chmod($temporary, 0660) || ! rename($temporary, $argv[1])) {
            @unlink($temporary); exit(1);
        }
    ' "${APPLICATION_ROOT}/storage/framework/down" || return 1
    # Never discard edits or an unrelated checkout made after interruption.
    [[ "${CLEAN_ROLLBACK}" == '1' ]] || { echo 'ERROR: No se autorizó restaurar este checkout.' >&2; return 1; }
    local current_sha branch
    current_sha="$(git -C "${REPOSITORY_ROOT}" rev-parse HEAD)" || return 1
    branch="$(git -C "${REPOSITORY_ROOT}" symbolic-ref --quiet --short HEAD)" || return 1
    [[ "${branch}" == 'main' && ( "${current_sha}" == "${ORIGINAL_SHA}" || "${current_sha}" == "${EXPECTED_SHA}" ) ]] || {
        echo 'ERROR: El checkout cambió fuera del despliegue; no se restaurará automáticamente.' >&2; return 1;
    }
    [[ -z "$(git -C "${REPOSITORY_ROOT}" status --porcelain --untracked-files=no)" ]] || {
        echo 'ERROR: Hay modificaciones locales; se conservan y la recuperación queda pendiente.' >&2; return 1;
    }
    git -C "${REPOSITORY_ROOT}" cat-file -e "${ORIGINAL_SHA}^{commit}" || return 1
    git -C "${REPOSITORY_ROOT}" merge-base --is-ancestor "${ORIGINAL_SHA}" "${EXPECTED_SHA}" || return 1
    resolve_existing_composer || return 1
    write_journal rollback || return 1
    cd -- "${APPLICATION_ROOT}" || return 1
    cd -- "${REPOSITORY_ROOT}" || return 1
    echo "Restaurando código y dependencias de ${ORIGINAL_SHA}; la base de datos se conserva." >&2
    git reset --hard "${ORIGINAL_SHA}" || return 1
    cd -- "${APPLICATION_ROOT}" || return 1
    bounded "${COMPOSER_COMMAND[@]}" install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader || return 1
    bounded "${PHP_BIN}" artisan optimize:clear || return 1
    bounded "${PHP_BIN}" artisan config:cache || return 1
    bounded "${PHP_BIN}" artisan view:cache || return 1
    bounded "${PHP_BIN}" artisan queue:restart || return 1
    APP_CONFIG_CACHE="${UNCACHED_CONFIG_PATH}" bounded "${PHP_BIN}" artisan 'caope:security-audit' --profile="${SECURITY_PROFILE}" --no-interaction || return 1
    bounded "${PHP_BIN}" -r '
        $payload = json_encode([
            "sha" => strtolower($argv[2]), "deployed_at" => gmdate("Y-m-d\\TH:i:s\\Z"),
            "request_id" => "automatic-rollback",
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
        $temporary = $argv[1].".tmp.".getmypid();
        if (file_put_contents($temporary, $payload, LOCK_EX) === false
            || ! chmod($temporary, 0660) || ! rename($temporary, $argv[1])) {
            @unlink($temporary); exit(1);
        }
    ' "${VERSION_MARKER_PATH}" "${ORIGINAL_SHA}" || return 1
    normalize_runtime_permissions || return 1
    bounded "${PHP_BIN}" artisan up || return 1
    RESULT='failed'
    RESTORED=1
    write_result || return 1
    rm -f -- "${JOURNAL_PATH}" || return 1
    JOURNAL_OWNED=0
    echo "Rollback automático verificado en ${ORIGINAL_SHA}." >&2
}

# Invoked indirectly by the EXIT trap.
# shellcheck disable=SC2317
cleanup() {
    local exit_code="$1"
    trap - EXIT ERR
    trap '' INT TERM
    set +e
    # Code 75 belongs only to our explicit concurrency/binding deferrals, not
    # to a dependency that happens to fail with EX_TEMPFAIL during deployment.
    if [[ "${exit_code}" -eq 75 && "${DEFERRED}" -ne 1 ]]; then exit_code=1; fi
    if [[ "${LOCK_ACQUIRED}" -eq 1 ]]; then
        if [[ "${exit_code}" -ne 75 ]]; then
            rm -f -- "${COMPOSER_INSTALLER_PATH}" "${UNCACHED_CONFIG_PATH}"
        fi
        if [[ "${exit_code}" -ne 75 && "${JOURNAL_OWNED}" -eq 1 && "${RESULT}" != 'succeeded' ]]; then
            if [[ "${RECOVERY_ATTEMPTED}" -eq 1 ]] || ! rollback_failed_deployment; then
                RESULT='recovery_required'
                echo 'ERROR: Recuperación pendiente; no se ejecutará artisan up.' >&2
                exit_code=1
            fi
        fi
        if [[ "${exit_code}" -ne 75 && -n "${REQUEST_ID}" ]]; then
            write_result || { echo 'ERROR: No se pudo guardar el resultado.' >&2; exit_code=1; }
            # Remove our authorization only, never a newer queued request.
            "${PHP_BIN}" -r '
                $j = json_decode((string) @file_get_contents($argv[1]), true);
                if (is_array($j) && ($j["request_id"] ?? null) === $argv[2]) { @unlink($argv[1]); }
            ' "${EXPECTED_MARKER_PATH}" "${REQUEST_ID}"
        fi
        rm -f -- "${LOCK_DIRECTORY}/agent-owner"
        rmdir -- "${LOCK_DIRECTORY}" 2>/dev/null
    fi
    if [[ "${exit_code}" -ne 75 && ( "${RECOVER_ONLY}" -eq 0 || -n "${REQUEST_ID}" || "${RESULT}" == 'recovery_required' ) ]]; then
        printf 'CAOPE_RESULT=%s\n' "${RESULT}"
    fi
    exit "${exit_code}"
}

trap 'cleanup $?' EXIT
trap 'report_unexpected_error $? $LINENO' ERR
trap 'INTERRUPTED=1; exit 130' INT
trap 'INTERRUPTED=1; exit 143' TERM

PHP_BIN="$(resolve_executable "${CAOPE_PHP_BIN:-php}")" || fail 'No se encontró PHP para el despliegue.'
SECURITY_PROFILE="${CAOPE_SECURITY_PROFILE:-auto}"
[[ "${SECURITY_PROFILE}" == 'production' || "${SECURITY_PROFILE}" == 'staging' || "${SECURITY_PROFILE}" == 'auto' ]] || fail 'Perfil de seguridad no válido.'
command -v git >/dev/null 2>&1 || fail 'Git no está disponible.'
command -v flock >/dev/null 2>&1 || fail 'flock no está disponible para proteger el despliegue.'
[[ -f "${APPLICATION_ROOT}/artisan" ]] || fail 'Falta backend/artisan.'

# Never unlink this inode: every competing invocation must lock the same file.
exec 9>"${REPOSITORY_ROOT}/.deploy-agent.lock"
flock -n 9 || { echo 'CAOPE_LOCK=busy'; defer_deployment; }
# Bootstrap still uses the directory. Reclaim only our own provably dead lock.
if [[ -d "${LOCK_DIRECTORY}" ]]; then
    owner=()
    if [[ -f "${LOCK_DIRECTORY}/agent-owner" && ! -L "${LOCK_DIRECTORY}/agent-owner" ]]; then
        mapfile -t owner < "${LOCK_DIRECTORY}/agent-owner"
    fi
    if [[ "${#owner[@]}" -ne 3 || ! "${owner[0]:-}" =~ ^[0-9]+$ || ! "${owner[2]:-}" =~ ^[0-9]+$ ]]; then
        echo 'ERROR: Bloqueo heredado sin propietario verificable; se conserva por seguridad.' >&2; defer_deployment
    fi
    boot_id="$(cat /proc/sys/kernel/random/boot_id)"
    current_start="$(awk '{print $22}' "/proc/${owner[0]}/stat" 2>/dev/null || true)"
    if [[ "${owner[1]}" == "${boot_id}" && "${owner[2]}" == "${current_start}" ]]; then
        echo 'CAOPE_LOCK=busy'; defer_deployment
    fi
    rm -f -- "${LOCK_DIRECTORY}/agent-owner"
    rmdir -- "${LOCK_DIRECTORY}" 2>/dev/null || { echo 'ERROR: El bloqueo contiene archivos desconocidos.' >&2; defer_deployment; }
fi
mkdir -- "${LOCK_DIRECTORY}" 2>/dev/null || { echo 'CAOPE_LOCK=busy'; defer_deployment; }
LOCK_ACQUIRED=1
printf '%s\n%s\n%s\n' "$$" "$(cat /proc/sys/kernel/random/boot_id)" "$(awk '{print $22}' "/proc/$$/stat")" > "${LOCK_DIRECTORY}/agent-owner"

if [[ -e "${JOURNAL_PATH}" || -L "${JOURNAL_PATH}" ]]; then
    JOURNAL_OWNED=1
    if rollback_failed_deployment; then
        if [[ "${RECOVER_ONLY}" -eq 1 ]]; then exit 0; else exit 1; fi
    fi
    RESULT='recovery_required'
    fail 'No fue posible recuperar el despliegue interrumpido; revisa el diagnóstico.'
fi
if [[ "${RECOVER_ONLY}" -eq 1 ]]; then
    echo 'CAOPE_RECOVERY=not_needed'; exit 0
fi

[[ ! -e "${UNCACHED_CONFIG_PATH}" ]] || fail 'La ruta temporal de configuración ya existe.'
[[ -r "${EXPECTED_MARKER_PATH}" ]] || fail 'No existe una autorización vigente de GitHub.'
AUTHORIZATION="$("${PHP_BIN}" -r '
    $m = json_decode((string) @file_get_contents($argv[1]), true);
    $sha = is_array($m) ? ($m["sha"] ?? null) : null;
    $requestId = is_array($m) ? ($m["request_id"] ?? null) : null;
    $expiresAt = is_array($m) ? ($m["expires_at"] ?? null) : null;
    if (! is_string($sha) || ! preg_match("/^[a-f0-9]{40}$/iD", $sha)
        || ! is_string($requestId) || trim($requestId) === "" || strlen($requestId) > 100
        || preg_match("/[\\r\\n]/", $requestId) || ! is_int($expiresAt) || $expiresAt <= time()) {
        fwrite(STDERR, "La autorización no es válida o ya expiró.\n"); exit(1);
    }
    echo strtolower($sha)."\n".$requestId;
' "${EXPECTED_MARKER_PATH}")"
mapfile -t authorization_fields <<< "${AUTHORIZATION}"
EXPECTED_SHA="${authorization_fields[0]}"
REQUEST_ID="${authorization_fields[1]}"
check_agent_binding
normalize_runtime_permissions || fail 'No fue posible preparar los permisos para el servidor web.'
mkdir -p -- "$(dirname -- "${JOURNAL_PATH}")"
stage preflight
CURRENT_BRANCH="$(git -C "${REPOSITORY_ROOT}" symbolic-ref --quiet --short HEAD || true)"
CURRENT_SHA="$(git -C "${REPOSITORY_ROOT}" rev-parse HEAD)"
ORIGINAL_SHA="${CURRENT_SHA}"
[[ "${CURRENT_BRANCH}" == 'main' ]] || fail 'El checkout debe permanecer en main.'
CLEAN_ROLLBACK=0
if [[ "${CAOPE_REQUIRE_CLEAN_CHECKOUT:-0}" == '1' ]]; then
    [[ -z "$(git -C "${REPOSITORY_ROOT}" status --porcelain --untracked-files=no)" ]] || fail 'Producción contiene cambios locales en archivos versionados.'
    CLEAN_ROLLBACK=1
fi
[[ ! -f "${APPLICATION_ROOT}/storage/framework/down" ]] || fail 'CAOPE ya estaba en mantenimiento antes de esta solicitud.'
cd -- "${APPLICATION_ROOT}"
# Both the current cached runtime and the next uncached configuration must use
# the supported file maintenance driver and pass the security checks.
"${PHP_BIN}" artisan caope:security-audit --profile="${SECURITY_PROFILE}" --no-interaction || fail 'La configuración activa no aprobó la auditoría de seguridad.'
run_without_config_cache "${PHP_BIN}" artisan caope:security-audit --profile="${SECURITY_PROFILE}" --no-interaction || fail 'La auditoría de seguridad previa no fue aprobada.'
GIT_TERMINAL_PROMPT=0 git -C "${REPOSITORY_ROOT}" fetch --no-tags origin main
REMOTE_SHA="$(git -C "${REPOSITORY_ROOT}" rev-parse refs/remotes/origin/main)"
[[ "${REMOTE_SHA}" == "${EXPECTED_SHA}" ]] || fail 'origin/main no coincide con la revisión exacta validada por GitHub.'
git -C "${REPOSITORY_ROOT}" merge-base --is-ancestor "${CURRENT_SHA}" "${EXPECTED_SHA}" || fail 'El checkout no puede avanzar de forma lineal.'

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
stage backup
run_without_config_cache "${PHP_BIN}" artisan backup:run --only-db --no-interaction || fail 'No fue posible crear el respaldo previo al despliegue.'
stage maintenance
write_journal maintenance
JOURNAL_OWNED=1
"${PHP_BIN}" artisan down --retry=60
stage checkout
write_journal checkout
cd -- "${REPOSITORY_ROOT}"
GIT_MERGE_AUTOEDIT=no git merge --ff-only refs/remotes/origin/main
CURRENT_SHA="$(git rev-parse HEAD)"
[[ "${CURRENT_SHA}" == "${EXPECTED_SHA}" ]] || fail 'El checkout no terminó en la revisión validada.'
cd -- "${APPLICATION_ROOT}"
stage dependencies
write_journal dependencies
"${COMPOSER_COMMAND[@]}" install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
stage migrations
write_journal migrations
"${PHP_BIN}" artisan migrate --force
stage cache
write_journal cache
"${PHP_BIN}" artisan optimize:clear
"${PHP_BIN}" artisan config:cache
"${PHP_BIN}" artisan view:cache
"${PHP_BIN}" artisan storage:link --force
"${PHP_BIN}" artisan queue:restart
stage verify
write_journal verify
"${COMPOSER_COMMAND[@]}" check-platform-reqs --no-dev
run_without_config_cache "${PHP_BIN}" artisan caope:security-audit --profile="${SECURITY_PROFILE}" --no-interaction
normalize_runtime_permissions || fail 'No fue posible restaurar los permisos de ejecución.'
"${PHP_BIN}" -r '
    $payload = json_encode([
        "sha" => $argv[2], "deployed_at" => gmdate("Y-m-d\\TH:i:s\\Z"), "request_id" => $argv[3],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
    $temporary = $argv[1].".tmp.".getmypid();
    if (file_put_contents($temporary, $payload, LOCK_EX) === false
        || ! chmod($temporary, 0660) || ! rename($temporary, $argv[1])) {
        @unlink($temporary); exit(1);
    }
' "${VERSION_MARKER_PATH}" "${CURRENT_SHA}" "${REQUEST_ID}"
"${PHP_BIN}" artisan up
stage completed
RESULT='succeeded'
if ! write_result; then
    RESULT='failed'
    fail 'No fue posible registrar el despliegue completado.'
fi
if ! rm -f -- "${JOURNAL_PATH}"; then
    RESULT='failed'
    fail 'No fue posible cerrar el diario del despliegue.'
fi
JOURNAL_OWNED=0
echo "Despliegue completado en ${CURRENT_SHA}."
}

deployment_main "$@"; exit $?
