#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIRECTORY="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"

# Compatibility entrypoint for staging installations whose private .env still
# references the former filename. New installations use deploy-scheduled.sh.
exec /bin/bash "${SCRIPT_DIRECTORY}/deploy-scheduled.sh"
