#!/usr/bin/env bash
# PHP snippets deliberately use single quotes to prevent Bash interpolation.
# shellcheck disable=SC2016

# Real Git, PHP JSON snippets, flock and signals; only application commands and
# Composer are simulated. Each scenario uses an isolated temporary repository.
# Retain fixtures for inspection; the CI platform discards its runner afterward.
set -Eeuo pipefail
TEST_DIRECTORY="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPOSITORY_ROOT="$(cd -- "${TEST_DIRECTORY}/../.." && pwd)"
TEST_ROOT="$(mktemp -d)"
REAL_PHP="$(command -v php || command -v php83)"
REAL_GIT="$(command -v git)"
export REAL_PHP REAL_GIT

fail() { echo "ERROR: $1" >&2; [[ -f "${OUTPUT:-}" ]] && cat "${OUTPUT}" >&2; exit 1; }
contains() { grep -F -- "$1" "$2" >/dev/null || fail "No se encontró: $1"; }
printf 'Smoke fixtures retained: %s\n' "${TEST_ROOT}"
for dependency in flock timeout git; do command -v "${dependency}" >/dev/null || fail "Falta ${dependency}."; done

mkdir -p "${TEST_ROOT}/bin"
cat > "${TEST_ROOT}/bin/php" <<'PHP_WRAPPER'
#!/usr/bin/env bash
set -Eeuo pipefail
if [[ "${1:-}" == '-r' ]]; then exec "${REAL_PHP}" "$@"; fi
if [[ "${1:-}" == */composer ]]; then
    shift
    printf 'composer:%s\n' "$*" >> "${CASE_ROOT}/commands.log"
    if [[ "${1:-}" == 'install' && "$(cat "${CASE_ROOT}/release")" == 'new' ]]; then
        case "${SCENARIO}" in
            composer_failure|rollback_failure) exit 42 ;;
            temporary_failure) exit 75 ;;
            interrupt)
                # SIGTERM reaches the worker while this child exits promptly.
                kill -TERM "${PPID}"; exit 143 ;;
            killed)
                # Simulate an uncatchable process death; inherited flock is
                # released when this final child exits, leaving a real journal.
                kill -KILL "${PPID}"; exit 137 ;;
        esac
    fi
    exit 0
fi
if [[ "${1:-}" == 'artisan' ]]; then
    shift
    printf 'artisan:%s\n' "$*" >> "${CASE_ROOT}/commands.log"
    case "${1:-}" in
        down) printf '{"status":503}\n' > "${CASE_ROOT}/backend/storage/framework/down" ;;
        up) rm -f -- "${CASE_ROOT}/backend/storage/framework/down" ;;
    esac
    exit 0
fi
echo 'Unexpected PHP invocation' >&2
exit 2
PHP_WRAPPER
cat > "${TEST_ROOT}/bin/composer" <<'COMPOSER_WRAPPER'
#!/usr/bin/env bash
exit 0
COMPOSER_WRAPPER
cat > "${TEST_ROOT}/bin/git" <<'GIT_WRAPPER'
#!/usr/bin/env bash
set -Eeuo pipefail
if [[ "${1:-}" == 'reset' && "${SCENARIO:-}" == 'rollback_failure' ]]; then exit 43; fi
exec "${REAL_GIT}" "$@"
GIT_WRAPPER
chmod +x "${TEST_ROOT}/bin/"*

fixture() {
    SCENARIO="$1"
    CASE_ROOT="${TEST_ROOT}/${SCENARIO}"
    OUTPUT="${CASE_ROOT}/output.log"
    if [[ -e "${CASE_ROOT}" || -L "${CASE_ROOT}" || -e "${CASE_ROOT}-origin" || -L "${CASE_ROOT}-origin" ]]; then
        fail 'El destino del escenario ya existe; se conserva sin modificar.'
    fi
    export CASE_ROOT SCENARIO
    mkdir -p "${CASE_ROOT}/scripts" "${CASE_ROOT}/backend/storage/app/deployment" \
        "${CASE_ROOT}/backend/storage/framework" "${CASE_ROOT}/backend/bootstrap/cache"
    cp -- "${REPOSITORY_ROOT}/scripts/deploy-scheduled.sh" "${CASE_ROOT}/scripts/deploy-scheduled.sh"
    touch "${CASE_ROOT}/backend/artisan" "${CASE_ROOT}/commands.log"
    printf 'old\n' > "${CASE_ROOT}/release"
    printf 'backend/storage/\nbackend/bootstrap/\n.deploy*\n*.log\n' > "${CASE_ROOT}/.gitignore"
    "${REAL_GIT}" -C "${CASE_ROOT}" init -q -b main
    "${REAL_GIT}" -C "${CASE_ROOT}" config user.email 'deploy-test@example.invalid'
    "${REAL_GIT}" -C "${CASE_ROOT}" config user.name 'Deployment Test'
    "${REAL_GIT}" -C "${CASE_ROOT}" add .
    "${REAL_GIT}" -C "${CASE_ROOT}" commit -qm original
    ORIGINAL_SHA="$("${REAL_GIT}" -C "${CASE_ROOT}" rev-parse HEAD)"
    printf 'new\n' > "${CASE_ROOT}/release"
    # This moves the parser offsets when Git replaces the executing file.
    sed -i '2i# A new deployment script can be installed while the old one runs.\n# Its current program must remain an immutable in-memory function.' "${CASE_ROOT}/scripts/deploy-scheduled.sh"
    "${REAL_GIT}" -C "${CASE_ROOT}" add .
    "${REAL_GIT}" -C "${CASE_ROOT}" commit -qm release
    TARGET_SHA="$("${REAL_GIT}" -C "${CASE_ROOT}" rev-parse HEAD)"
    "${REAL_GIT}" clone -q --bare "${CASE_ROOT}" "${CASE_ROOT}-origin"
    "${REAL_GIT}" -C "${CASE_ROOT}" remote add origin "${CASE_ROOT}-origin"
    "${REAL_GIT}" -C "${CASE_ROOT}" reset -q --hard "${ORIGINAL_SHA}"
    "${REAL_PHP}" -r 'file_put_contents($argv[1],json_encode(["sha"=>$argv[2],"request_id"=>"smoke-".$argv[3],"expires_at"=>time()+600]));' \
        "${CASE_ROOT}/backend/storage/app/deployment/expected.json" "${TARGET_SHA}" "${SCENARIO}"
}

run_script() {
    local status=0
    PATH="${TEST_ROOT}/bin:${PATH}" CAOPE_REQUIRE_CLEAN_CHECKOUT=1 CAOPE_RUNTIME_GROUP="$(id -gn)" \
        CAOPE_PHP_BIN="${TEST_ROOT}/bin/php" \
        bash "${CASE_ROOT}/scripts/deploy-scheduled.sh" "$@" > "${OUTPUT}" 2>&1 || status=$?
    return "${status}"
}
assert_result() {
    "${REAL_PHP}" -r '
        $r=json_decode(file_get_contents($argv[1]),true);
        if (($r["schema"]??null)!==1 || ($r["status"]??null)!==$argv[2]
            || ($r["restored"]??null)!==($argv[3]==="1") || !strtotime($r["finished_at"]??"")) {exit(1);}
    ' "${CASE_ROOT}/backend/storage/app/deployment/result.json" "$1" "$2" || fail 'Resultado persistido incorrecto.'
}
assert_restored() {
    [[ "$("${REAL_GIT}" -C "${CASE_ROOT}" rev-parse HEAD)" == "${ORIGINAL_SHA}" ]] || fail 'No se restauró la revisión anterior.'
    [[ ! -e "${CASE_ROOT}/backend/storage/framework/down" ]] || fail 'La aplicación sigue en mantenimiento después de recuperarse.'
    [[ ! -e "${CASE_ROOT}/backend/storage/app/deployment/recovery.json" ]] || fail 'No se retiró el diario recuperado.'
    [[ ! -d "${CASE_ROOT}/.deploy-lock" ]] || fail 'El bloqueo quedó abandonado.'
    contains 'CAOPE_RESULT=failed' "${OUTPUT}"
    assert_result failed 1
}

fixture success
run_script || fail 'Falló un despliegue válido.'
[[ "$("${REAL_GIT}" -C "${CASE_ROOT}" rev-parse HEAD)" == "${TARGET_SHA}" ]] || fail 'No se instaló el SHA autorizado.'
[[ ! -e "${CASE_ROOT}/backend/storage/framework/down" ]] || fail 'Mantenimiento no retirado.'
[[ ! -e "${CASE_ROOT}/backend/storage/app/deployment/recovery.json" ]] || fail 'Diario no retirado.'
for stage in preflight backup maintenance checkout dependencies migrations cache verify completed; do contains "CAOPE_STAGE=${stage}" "${OUTPUT}"; done
contains 'CAOPE_RESULT=succeeded' "${OUTPUT}"
assert_result succeeded 0

fixture composer_failure
if run_script; then fail 'Se ocultó un fallo de Composer.'; fi
assert_restored
contains 'composer:install --no-dev' "${CASE_ROOT}/commands.log"

fixture rollback_failure
if run_script; then fail 'Se ocultó un fallo de rollback.'; fi
[[ -f "${CASE_ROOT}/backend/storage/framework/down" ]] || fail 'Se reabrió una aplicación sin recuperar.'
[[ -f "${CASE_ROOT}/backend/storage/app/deployment/recovery.json" ]] || fail 'Se perdió el diario de recuperación.'
if grep -F 'artisan:up' "${CASE_ROOT}/commands.log" >/dev/null; then fail 'Se ejecutó up después de fallar el rollback.'; fi
contains 'CAOPE_RESULT=recovery_required' "${OUTPUT}"
assert_result recovery_required 0
SCENARIO='recovery'
run_script --recover-only || fail 'No se pudo reintentar el rollback.'
assert_restored

fixture interrupt
if run_script; then fail 'Se ocultó una interrupción.'; fi
assert_restored
"${REAL_PHP}" -r '$r=json_decode(file_get_contents($argv[1]),true);exit(($r["interrupted"]??false)?0:1);' \
    "${CASE_ROOT}/backend/storage/app/deployment/result.json" || fail 'No se registró la interrupción.'

fixture killed
if run_script; then fail 'No se simuló la terminación abrupta.'; fi
[[ -f "${CASE_ROOT}/backend/storage/app/deployment/recovery.json" ]] || fail 'Se perdió el diario tras SIGKILL.'
[[ -f "${CASE_ROOT}/.deploy-lock/agent-owner" ]] || fail 'No quedó el bloqueo abandonado esperado.'
# No valid authorization is needed to finish a previously authorized recovery.
printf 'invalid and expired\n' > "${CASE_ROOT}/backend/storage/app/deployment/expected.json"
SCENARIO='recovery'
run_script --recover-only || fail 'El bloqueo/journal no se recuperó tras SIGKILL.'
assert_restored
run_script --recover-only || fail 'Falló una comprobación de recuperación vacía.'
contains 'CAOPE_RECOVERY=not_needed' "${OUTPUT}"
assert_result failed 1

fixture busy
exec 8>"${CASE_ROOT}/.deploy-agent.lock"
flock -n 8
status=0
run_script || status=$?
[[ "${status}" -eq 75 ]] || fail 'Un despliegue paralelo no devolvió código 75.'
[[ -f "${CASE_ROOT}/backend/storage/app/deployment/expected.json" ]] || fail 'Un proceso bloqueado borró la autorización.'
[[ ! -e "${CASE_ROOT}/backend/storage/app/deployment/result.json" ]] || fail 'Un proceso bloqueado alteró el resultado.'
flock -u 8
exec 8>&-

fixture dirty
printf 'local changes\n' >> "${CASE_ROOT}/release"
if run_script; then fail 'Se aceptó un checkout con cambios locales.'; fi
contains 'local changes' "${CASE_ROOT}/release"
[[ ! -e "${CASE_ROOT}/backend/storage/framework/down" ]] || fail 'Se activó mantenimiento antes de validar el checkout.'

fixture wrong_sha
"${REAL_PHP}" -r '$p=$argv[1];$j=json_decode(file_get_contents($p),true);$j["sha"]=$argv[2];file_put_contents($p,json_encode($j));' \
    "${CASE_ROOT}/backend/storage/app/deployment/expected.json" "${ORIGINAL_SHA}"
if run_script; then fail 'Se aceptó una revisión distinta de origin/main.'; fi
[[ "$("${REAL_GIT}" -C "${CASE_ROOT}" rev-parse HEAD)" == "${ORIGINAL_SHA}" ]] || fail 'Se cambió el checkout sin autorización exacta.'

fixture legacy_lock
mkdir "${CASE_ROOT}/.deploy-lock"
status=0
run_script --recover-only || status=$?
[[ "${status}" -eq 75 && -d "${CASE_ROOT}/.deploy-lock" ]] || fail 'Se retiró un bloqueo heredado sin prueba de propiedad.'

fixture existing_maintenance
printf '{"status":503}\n' > "${CASE_ROOT}/backend/storage/framework/down"
if run_script; then fail 'Se aceptó mantenimiento previo ajeno a la solicitud.'; fi
[[ -f "${CASE_ROOT}/backend/storage/framework/down" ]] || fail 'Se retiró mantenimiento ajeno.'
[[ ! -e "${CASE_ROOT}/backend/storage/app/deployment/recovery.json" ]] || fail 'Se creó un diario sobre mantenimiento ajeno.'

fixture damaged_journal
printf '{"original_sha":"invalid"}\n' > "${CASE_ROOT}/backend/storage/app/deployment/recovery.json"
if run_script --recover-only; then fail 'Se aceptó un diario malformado.'; fi
contains 'CAOPE_RESULT=recovery_required' "${OUTPUT}"
[[ -f "${CASE_ROOT}/backend/storage/app/deployment/recovery.json" ]] || fail 'Se borró evidencia de recuperación.'
[[ "$("${REAL_GIT}" -C "${CASE_ROOT}" rev-parse HEAD)" == "${ORIGINAL_SHA}" ]] || fail 'Se alteró el checkout con un diario inválido.'

fixture local_edits_after_interruption
SCENARIO='killed'
if run_script; then fail 'No se simuló la interrupción.'; fi
printf 'retain manual repair\n' >> "${CASE_ROOT}/release"
SCENARIO='recovery'
if run_script --recover-only; then fail 'La recuperación descartó modificaciones manuales.'; fi
contains 'retain manual repair' "${CASE_ROOT}/release"
[[ -f "${CASE_ROOT}/backend/storage/framework/down" ]] || fail 'Se reabrió una aplicación sin recuperación verificada.'
assert_result recovery_required 0

fixture temporary_failure
status=0
run_script || status=$?
[[ "${status}" -ne 0 && "${status}" -ne 75 ]] || fail 'Un fallo de dependencias se confundió con un bloqueo concurrente.'
assert_restored

fixture request_binding
status=0
CAOPE_AGENT_REQUEST_ID='another-request' run_script || status=$?
[[ "${status}" -eq 75 ]] || fail 'El agente ejecutó una solicitud distinta de la que había observado.'
contains 'CAOPE_AUTHORIZATION=changed' "${OUTPUT}"
[[ -f "${CASE_ROOT}/backend/storage/app/deployment/expected.json" ]] || fail 'Se eliminó la autorización nueva.'
[[ ! -e "${CASE_ROOT}/backend/storage/app/deployment/result.json" ]] || fail 'Se publicó un resultado para una solicitud distinta.'
if grep -F 'CAOPE_STAGE=' "${OUTPUT}" >/dev/null; then fail 'Se publicó progreso de una solicitud distinta.'; fi
status=0
CAOPE_AGENT_REQUEST_ID='smoke-request_binding' CAOPE_AGENT_EXPECTED_SHA="${ORIGINAL_SHA}" run_script || status=$?
[[ "${status}" -eq 75 ]] || fail 'Se ignoró el cambio de SHA autorizado.'
[[ "$("${REAL_GIT}" -C "${CASE_ROOT}" rev-parse HEAD)" == "${ORIGINAL_SHA}" ]] || fail 'Se modificó el checkout antes de vincular la solicitud.'

fixture recovery_binding
SCENARIO='killed'
if run_script; then fail 'No se simuló la interrupción.'; fi
SCENARIO='recovery'
status=0
CAOPE_AGENT_REQUEST_ID='another-request' run_script --recover-only || status=$?
[[ "${status}" -eq 75 ]] || fail 'La recuperación tomó el diario de otra solicitud.'
contains 'CAOPE_AUTHORIZATION=changed' "${OUTPUT}"
[[ -f "${CASE_ROOT}/backend/storage/app/deployment/recovery.json" ]] || fail 'Se retiró el diario ajeno.'
[[ ! -e "${CASE_ROOT}/backend/storage/app/deployment/result.json" ]] || fail 'Se publicó un resultado de recuperación ajeno.'
if grep -F 'CAOPE_STAGE=' "${OUTPUT}" >/dev/null; then fail 'Se publicó progreso de recuperación ajeno.'; fi
CAOPE_AGENT_REQUEST_ID='smoke-recovery_binding' CAOPE_AGENT_EXPECTED_SHA="${TARGET_SHA}" run_script --recover-only || fail 'Se rechazó recuperar la solicitud correcta.'
assert_restored

echo 'Scheduled deployment smoke tests: 15 scenarios OK'
bash "${TEST_DIRECTORY}/repair-runtime-smoke.sh"
