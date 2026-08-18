#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIRECTORY="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPOSITORY_ROOT="$(cd -- "${SCRIPT_DIRECTORY}/.." && pwd)"
APPLICATION_ROOT="${REPOSITORY_ROOT}/backend"

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
    install -d -m 2770 -- "${runtime_path}"
    find "${runtime_path}" -type d -user "$(id -u)" -exec chmod 2770 {} +
    find "${runtime_path}" -type f -user "$(id -u)" -exec chmod 0660 {} +
done

for runtime_path in "${RUNTIME_PATHS[@]}"; do
    [[ -r "${runtime_path}" && -w "${runtime_path}" && -x "${runtime_path}" ]] || {
        echo "ERROR: La ruta de ejecución no es utilizable: ${runtime_path}" >&2
        exit 1
    }
done

echo 'RUNTIME_CAOPE=LISTO'
