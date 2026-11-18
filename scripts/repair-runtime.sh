#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIRECTORY="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPOSITORY_ROOT="$(cd -- "${SCRIPT_DIRECTORY}/.." && pwd)"
APPLICATION_ROOT="${REPOSITORY_ROOT}/backend"
umask 0007

[[ "$(id -u)" -ne 0 ]] || {
    echo 'ERROR: La reparación ordinaria no debe ejecutarse como root.' >&2
    exit 1
}

# Existing queue units have a short systemd start timeout. Let their normal
# Restart=always retry while the scheduler (40-minute budget) recovers vendor.
# Match complete cgroup components, including v1, v2 and delegated subgroups.
defer_queue_recovery() {
    if grep -Eq '/caope-queue[.]service(/|$)' /proc/self/cgroup 2>/dev/null; then
        echo 'CAOPE_RECOVERY=waiting_for_scheduler' >&2
        echo 'Recuperación pendiente: el trabajador esperará al scheduler antes de iniciar.' >&2
        exit 75
    fi
}
if [[ -e "${APPLICATION_ROOT}/storage/app/deployment/recovery.json" ]]; then
    defer_queue_recovery
fi

RUNTIME_PATHS=(
    "${APPLICATION_ROOT}/bootstrap/cache"
    "${APPLICATION_ROOT}/storage/app/deployment"
    "${APPLICATION_ROOT}/storage/framework/cache"
    "${APPLICATION_ROOT}/storage/framework/sessions"
    "${APPLICATION_ROOT}/storage/framework/views"
    "${APPLICATION_ROOT}/storage/logs"
)

for runtime_path in "${RUNTIME_PATHS[@]}"; do
    # mkdir -p does not try to chmod an existing directory owned by Apache.
    # The root-only installer establishes the initial owner/mode; afterwards
    # each process may create files under the shared setgid group.
    mkdir -p -- "${runtime_path}"
    find "${runtime_path}" -type f -user "$(id -u)" -exec chmod 0660 {} +
done

for runtime_path in "${RUNTIME_PATHS[@]}"; do
    [[ -r "${runtime_path}" && -w "${runtime_path}" && -x "${runtime_path}" ]] || {
        echo "ERROR: La ruta de ejecución no es utilizable: ${runtime_path}" >&2
        exit 1
    }
done

# This pre-start hook must also work when an interrupted Composer install left
# Laravel unable to boot. Recovery reads the shell journal, never expected.json.
if [[ -e "${APPLICATION_ROOT}/storage/app/deployment/recovery.json" ]]; then
    # A journal may have appeared during the permission scan above.
    defer_queue_recovery
    recovery_status=0
    /bin/bash "${SCRIPT_DIRECTORY}/deploy-scheduled.sh" --recover-only || recovery_status=$?
    # A live deployment owns the lock; its process handles recovery if needed.
    if [[ "${recovery_status}" -ne 0 && "${recovery_status}" -ne 75 ]]; then
        echo 'ERROR: Recuperación pendiente; consulta el resultado del despliegue.' >&2
        exit "${recovery_status}"
    fi
fi

echo 'RUNTIME_CAOPE=LISTO'
