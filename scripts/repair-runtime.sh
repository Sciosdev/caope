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

echo 'RUNTIME_CAOPE=LISTO'
