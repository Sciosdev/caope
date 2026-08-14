#!/usr/bin/env bash

set -euo pipefail

REPOSITORY_ROOT="$(git rev-parse --show-toplevel)"
cd -- "${REPOSITORY_ROOT}"

INVALID=0

while IFS= read -r -d '' FILE; do
    case "${FILE}" in
        backend/.env.example|backend/.env.testing)
            continue
            ;;
        .env|.env.*|*.env|*.env.*)
            echo "ERROR: ${FILE} is a tracked runtime environment file." >&2
            INVALID=1
            ;;
        *.log|error_log|*/error_log|*.phar|*.sql|*.sql.gz|*.dump|*.pem|*.key|*.p12|*.pfx|auth.json|*/auth.json|.netrc|*/.netrc|.npmrc|*/.npmrc|id_rsa|*/id_rsa|id_ed25519|*/id_ed25519)
            echo "ERROR: ${FILE} is a tracked sensitive runtime artifact." >&2
            INVALID=1
            ;;
    esac

    case "${FILE}" in
        *.sqlite|*.sqlite3|*.sqlite-*|*.sqlite3-*|*.db|*.db-*)
            SIZE="$(git cat-file -s ":${FILE}")"
            if (( SIZE > 0 )); then
                echo "ERROR: ${FILE} is a tracked populated database (${SIZE} bytes)." >&2
                INVALID=1
            fi
            ;;
    esac
done < <(git ls-files -z)

if (( INVALID != 0 )); then
    exit 1
fi

echo 'Repository hygiene check passed.'
