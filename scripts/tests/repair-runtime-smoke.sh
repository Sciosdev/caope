#!/usr/bin/env bash

set -Eeuo pipefail
TEST_DIRECTORY="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPOSITORY_ROOT="$(cd -- "${TEST_DIRECTORY}/../.." && pwd)"
TEST_ROOT="$(mktemp -d)"
REAL_GREP="$(command -v grep)"
REAL_ID="$(command -v id)"
export TEST_ROOT REAL_GREP REAL_ID
cleanup() {
    [[ "${TEST_ROOT}" == "${TMPDIR:-/tmp}/"* && -d "${TEST_ROOT}" ]] && rm -rf -- "${TEST_ROOT}"
}
fail() { echo "ERROR: $1" >&2; cat "${TEST_ROOT}/output.log" >&2; exit 1; }
trap cleanup EXIT
mkdir -p "${TEST_ROOT}/scripts" "${TEST_ROOT}/bin" "${TEST_ROOT}/backend/storage/app/deployment"
cp -- "${REPOSITORY_ROOT}/scripts/repair-runtime.sh" "${TEST_ROOT}/scripts/repair-runtime.sh"
cat > "${TEST_ROOT}/bin/id" <<'FAKE_ID'
#!/usr/bin/env bash
if [[ "${1:-}" == '-u' ]]; then echo 1001; else exec "${REAL_ID}" "$@"; fi
FAKE_ID
cat > "${TEST_ROOT}/bin/grep" <<'FAKE_GREP'
#!/usr/bin/env bash
args=()
for argument in "$@"; do
    if [[ "${argument}" == '/proc/self/cgroup' ]]; then
        args+=("${TEST_ROOT}/cgroup")
    else
        args+=("${argument}")
    fi
done
exec "${REAL_GREP}" "${args[@]}"
FAKE_GREP
cat > "${TEST_ROOT}/scripts/deploy-scheduled.sh" <<'FAKE_RECOVERY'
#!/usr/bin/env bash
[[ "$#" -eq 1 && "$1" == '--recover-only' ]] || exit 2
touch "${TEST_ROOT}/recovery-called"
rm -f -- "${TEST_ROOT}/backend/storage/app/deployment/recovery.json"
FAKE_RECOVERY
chmod +x "${TEST_ROOT}/bin/"*

check_hook() {
    local cgroup="$1" expected="$2" status=0
    printf '%s\n' "${cgroup}" > "${TEST_ROOT}/cgroup"
    printf '{}\n' > "${TEST_ROOT}/backend/storage/app/deployment/recovery.json"
    rm -f -- "${TEST_ROOT}/recovery-called"
    PATH="${TEST_ROOT}/bin:${PATH}" bash "${TEST_ROOT}/scripts/repair-runtime.sh" > "${TEST_ROOT}/output.log" 2>&1 || status=$?
    if [[ "${expected}" == 'wait' ]]; then
        [[ "${status}" -eq 75 ]] || fail 'La cola no esperó al scheduler.'
        [[ ! -e "${TEST_ROOT}/recovery-called" ]] || fail 'La cola tomó una recuperación que excede su tiempo de arranque.'
        [[ -f "${TEST_ROOT}/backend/storage/app/deployment/recovery.json" ]] || fail 'La cola alteró el diario.'
        "${REAL_GREP}" -F 'CAOPE_RECOVERY=waiting_for_scheduler' "${TEST_ROOT}/output.log" >/dev/null || fail 'Falta el diagnóstico de espera.'
    else
        [[ "${status}" -eq 0 ]] || fail 'El scheduler no pudo ejecutar la recuperación.'
        [[ -e "${TEST_ROOT}/recovery-called" ]] || fail 'Se omitió una recuperación permitida.'
        [[ ! -e "${TEST_ROOT}/backend/storage/app/deployment/recovery.json" ]] || fail 'La recuperación no terminó.'
    fi
}

check_hook '0::/system.slice/caope-queue.service' wait
check_hook $'11:memory:/system.slice/caope-queue.service\n1:name=systemd:/system.slice/caope-queue.service' wait
check_hook '0::/system.slice/caope-queue.service/delegated' wait
check_hook '0::/system.slice/caope-scheduler.service' recover
check_hook $'11:memory:/system.slice/caope-scheduler.service\n1:name=systemd:/system.slice/caope-scheduler.service' recover
check_hook '0::/user.slice/session-1.scope' recover
check_hook '0::/system.slice/caope-queue.service-other' recover

# A queue with no journal starts normally and never invokes the recovery runner.
printf '%s\n' '0::/system.slice/caope-queue.service' > "${TEST_ROOT}/cgroup"
rm -f -- "${TEST_ROOT}/recovery-called"
PATH="${TEST_ROOT}/bin:${PATH}" bash "${TEST_ROOT}/scripts/repair-runtime.sh" > "${TEST_ROOT}/output.log" 2>&1 || fail 'La cola quedó bloqueada sin diario pendiente.'
[[ ! -e "${TEST_ROOT}/recovery-called" ]] || fail 'Se inició una recuperación sin diario.'
echo 'Runtime recovery hook smoke tests: 8 scenarios OK'
