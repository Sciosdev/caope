#!/usr/bin/env bash
set -euo pipefail

if ! command -v rg >/dev/null 2>&1; then
  echo "[check-locale] ripgrep (rg) no está instalado en el sistema." >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "$PROJECT_ROOT"

PATTERNS=(
  'the'
  'and'
  'with'
  'without'
  'login'
  'password'
  'user'
  'settings'
  'update'
  'save'
)

JOINED_PATTERNS="$(IFS='|'; echo "${PATTERNS[*]}")"
REGEX="=>[^\\n]*\\b(?:${JOINED_PATTERNS})\\b"

EXCLUDES=(
  '--glob' '!vendor/**'
  '--glob' '!node_modules/**'
  '--glob' '!storage/**'
  '--glob' '!public/assets/**'
  '--glob' '!.git/**'
)

TARGET_DIRS=(
  'resources/lang/es'
)

FOUND=false
for dir in "${TARGET_DIRS[@]}"; do
  if [[ -d "$dir" ]]; then
    if rg --color=never --ignore-case --line-number -P "${EXCLUDES[@]}" -e "$REGEX" "$dir"; then
      FOUND=true
    fi
  fi
done

if [[ "$FOUND" == true ]]; then
  echo "[check-locale] Se encontraron posibles palabras en inglés. Revisa los resultados anteriores." >&2
  exit 1
fi

echo "[check-locale] No se detectaron coincidencias en inglés."
