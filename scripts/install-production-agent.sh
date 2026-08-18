#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIRECTORY="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPOSITORY_ROOT="$(cd -- "${SCRIPT_DIRECTORY}/.." && pwd)"
APPLICATION_ROOT="${REPOSITORY_ROOT}/backend"
AGENT_USER="${CAOPE_AGENT_USER:-caope-deploy}"
WEB_GROUP="${CAOPE_WEB_GROUP:-www-data}"
AGENT_HOME="${CAOPE_AGENT_HOME:-/var/lib/caope}"
SCHEDULER_SERVICE='/etc/systemd/system/caope-scheduler.service'
SCHEDULER_TIMER='/etc/systemd/system/caope-scheduler.timer'
QUEUE_SERVICE='/etc/systemd/system/caope-queue.service'
CRONTAB_BACKUP="/root/caope-crontab-before-agent-$(date -u +%Y%m%dT%H%M%SZ).txt"
PHP_BIN=''
GIT_COMMAND=(git -c "safe.directory=${REPOSITORY_ROOT}" -C "${REPOSITORY_ROOT}")

fail() {
    echo "ERROR: $1" >&2
    exit 1
}

run_as_agent() {
    runuser -u "${AGENT_USER}" -- env \
        HOME="${AGENT_HOME}" \
        CAOPE_RUNTIME_GROUP="${WEB_GROUP}" \
        "$@"
}

run_bootstrap_with_retries() {
    local attempt

    for attempt in 1 2 3; do
        echo "Ejecutando bootstrap sin privilegios (intento ${attempt}/3)."

        if run_as_agent env \
            CAOPE_SECURITY_PROFILE=production \
            COMPOSER_MAX_PARALLEL_HTTP=4 \
            /bin/bash "${REPOSITORY_ROOT}/scripts/bootstrap-cpanel.sh"; then
            return 0
        fi

        if [[ "${attempt}" -lt 3 ]]; then
            echo 'El bootstrap no terminó; se reintentará sin dejar la aplicación en mantenimiento.' >&2
            sleep "$((attempt * 10))"
        fi
    done

    return 1
}

normalize_repository_permissions() {
    chown -R "${AGENT_USER}:${WEB_GROUP}" "${REPOSITORY_ROOT}"
    chmod -R u+rwX,g+rX,o-rwx "${REPOSITORY_ROOT}"

    chown -R "${AGENT_USER}:${WEB_GROUP}" \
        "${APPLICATION_ROOT}/storage" \
        "${APPLICATION_ROOT}/bootstrap/cache"
    find "${APPLICATION_ROOT}/storage" "${APPLICATION_ROOT}/bootstrap/cache" \
        -type d -exec chmod 2770 {} +
    find "${APPLICATION_ROOT}/storage" "${APPLICATION_ROOT}/bootstrap/cache" \
        -type f -exec chmod 0660 {} +
    chmod 0640 "${APPLICATION_ROOT}/.env"
}

write_environment() {
    "${PHP_BIN}" -r '
        $path = $argv[1];
        $updates = [
            "CACHE_STORE" => "database",
            "QUEUE_CONNECTION" => "database",
            "SESSION_DRIVER" => "database",
            "SESSION_ENCRYPT" => "true",
        ];
        $contents = @file_get_contents($path);

        if (! is_string($contents)) {
            fwrite(STDERR, "No fue posible leer backend/.env.\n");
            exit(1);
        }

        foreach ($updates as $key => $value) {
            $pattern = "/^".preg_quote($key, "/")."=.*$/m";
            $replacement = $key."=".$value;

            if (preg_match($pattern, $contents) === 1) {
                $contents = preg_replace($pattern, $replacement, $contents, 1);
            } else {
                $contents = rtrim($contents).PHP_EOL.$replacement.PHP_EOL;
            }
        }

        $temporary = $path.".agent-".getmypid();

        if (file_put_contents($temporary, $contents, LOCK_EX) === false
            || ! chmod($temporary, 0640)
            || ! rename($temporary, $path)) {
            @unlink($temporary);
            fwrite(STDERR, "No fue posible actualizar la configuración operativa.\n");
            exit(1);
        }
    ' "${APPLICATION_ROOT}/.env"
}

write_systemd_units() {
    cat > "${SCHEDULER_SERVICE}" <<EOF
[Unit]
Description=CAOPE scheduler and deployment agent
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
User=${AGENT_USER}
Group=${WEB_GROUP}
WorkingDirectory=${APPLICATION_ROOT}
Environment=HOME=${AGENT_HOME}
Environment=CAOPE_RUNTIME_GROUP=${WEB_GROUP}
UMask=0007
ExecStartPre=/bin/bash ${REPOSITORY_ROOT}/scripts/repair-runtime.sh
ExecStart=${PHP_BIN} artisan schedule:run --no-interaction
TimeoutStartSec=40min
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=full
ProtectHome=true
ReadWritePaths=${REPOSITORY_ROOT} ${AGENT_HOME}
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectControlGroups=true
RestrictSUIDSGID=true
LockPersonality=true
EOF

    cat > "${SCHEDULER_TIMER}" <<EOF
[Unit]
Description=Run the CAOPE scheduler every minute

[Timer]
OnBootSec=30s
OnUnitActiveSec=60s
AccuracySec=1s
Persistent=true
Unit=caope-scheduler.service

[Install]
WantedBy=timers.target
EOF

    cat > "${QUEUE_SERVICE}" <<EOF
[Unit]
Description=CAOPE database queue worker
After=network-online.target mariadb.service mysql.service
Wants=network-online.target

[Service]
Type=simple
User=${AGENT_USER}
Group=${WEB_GROUP}
WorkingDirectory=${APPLICATION_ROOT}
Environment=HOME=${AGENT_HOME}
Environment=CAOPE_RUNTIME_GROUP=${WEB_GROUP}
UMask=0007
ExecStartPre=/bin/bash ${REPOSITORY_ROOT}/scripts/repair-runtime.sh
ExecStart=${PHP_BIN} artisan queue:work database --sleep=3 --tries=3 --timeout=120 --max-time=3600 --no-interaction
Restart=always
RestartSec=5
TimeoutStopSec=135
KillSignal=SIGTERM
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=full
ProtectHome=true
ReadWritePaths=${REPOSITORY_ROOT} ${AGENT_HOME}
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectControlGroups=true
RestrictSUIDSGID=true
LockPersonality=true

[Install]
WantedBy=multi-user.target
EOF

    chmod 0644 "${SCHEDULER_SERVICE}" "${SCHEDULER_TIMER}" "${QUEUE_SERVICE}"
}

remove_legacy_root_cron() {
    local current_crontab
    local filtered_crontab

    current_crontab="$(mktemp)"
    filtered_crontab="$(mktemp)"

    if crontab -l > "${current_crontab}" 2>/dev/null; then
        cp -- "${current_crontab}" "${CRONTAB_BACKUP}"
        chmod 0600 "${CRONTAB_BACKUP}"

        awk -v root="${REPOSITORY_ROOT}" '
            index($0, root) > 0 && $0 ~ /artisan[[:space:]]+(schedule:run|queue:work)/ { next }
            { print }
        ' "${current_crontab}" > "${filtered_crontab}"

        crontab "${filtered_crontab}"
        echo "Respaldo del crontab anterior: ${CRONTAB_BACKUP}"
    fi

    rm -f -- "${current_crontab}" "${filtered_crontab}"
}

[[ "$(id -u)" -eq 0 ]] || fail 'Este instalador único debe ejecutarse como root.'
[[ "${AGENT_USER}" =~ ^[a-z_][a-z0-9_-]{0,31}$ ]] || \
    fail 'El nombre del usuario técnico no es válido.'
[[ "${AGENT_USER}" != 'root' && "${AGENT_USER}" != 'www-data' ]] || \
    fail 'El agente debe usar una cuenta de sistema dedicada.'
[[ "${WEB_GROUP}" =~ ^[a-z_][a-z0-9_-]{0,31}$ ]] || \
    fail 'El grupo del servidor web no es válido.'
[[ "${AGENT_HOME}" =~ ^/[A-Za-z0-9._/-]+$ && "${AGENT_HOME}" != '/' ]] || \
    fail 'La ruta HOME del agente no es válida.'
[[ "${REPOSITORY_ROOT}" =~ ^/[A-Za-z0-9._/-]+$ ]] || \
    fail 'La ruta del repositorio contiene caracteres no admitidos por la unidad systemd.'
[[ -f "${APPLICATION_ROOT}/artisan" ]] || fail 'No se encontró backend/artisan.'
[[ -f "${APPLICATION_ROOT}/.env" ]] || fail 'No se encontró backend/.env.'
[[ -d /run/systemd/system ]] || fail 'Este servidor no está administrado por systemd.'
command -v runuser >/dev/null 2>&1 || fail 'No se encontró runuser.'
command -v systemctl >/dev/null 2>&1 || fail 'No se encontró systemctl.'
command -v crontab >/dev/null 2>&1 || fail 'No se encontró crontab.'
command -v useradd >/dev/null 2>&1 || fail 'No se encontró useradd.'
command -v usermod >/dev/null 2>&1 || fail 'No se encontró usermod.'
getent group "${WEB_GROUP}" >/dev/null 2>&1 || \
    fail "No existe el grupo web ${WEB_GROUP}."

for php_candidate in "${CAOPE_PHP_BIN:-}" /usr/bin/php8.4 /usr/bin/php8.3 /usr/bin/php; do
    [[ -n "${php_candidate}" && -x "${php_candidate}" ]] || continue

    if "${php_candidate}" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
        PHP_BIN="${php_candidate}"
        break
    fi
done

[[ -n "${PHP_BIN}" ]] || fail 'No se encontró PHP CLI 8.3 o posterior.'
[[ "${PHP_BIN}" =~ ^/[A-Za-z0-9._/-]+$ ]] || \
    fail 'La ruta de PHP no puede insertarse de forma segura en systemd.'

current_branch="$("${GIT_COMMAND[@]}" symbolic-ref --quiet --short HEAD || true)"
[[ "${current_branch}" == 'main' ]] || fail 'El checkout debe estar en la rama main.'
[[ -z "$("${GIT_COMMAND[@]}" status --porcelain --untracked-files=no)" ]] || \
    fail 'El checkout contiene cambios locales versionados.'

"${GIT_COMMAND[@]}" fetch --no-tags origin main
local_sha="$("${GIT_COMMAND[@]}" rev-parse HEAD)"
remote_sha="$("${GIT_COMMAND[@]}" rev-parse refs/remotes/origin/main)"
[[ "${local_sha}" == "${remote_sha}" ]] || \
    fail 'Actualiza main mediante fast-forward antes de instalar el agente.'

if ! id "${AGENT_USER}" >/dev/null 2>&1; then
    useradd \
        --system \
        --gid "${WEB_GROUP}" \
        --home-dir "${AGENT_HOME}" \
        --create-home \
        --shell /usr/sbin/nologin \
        "${AGENT_USER}"
else
    usermod \
        --gid "${WEB_GROUP}" \
        --home "${AGENT_HOME}" \
        --shell /usr/sbin/nologin \
        --lock \
        "${AGENT_USER}"
fi

[[ "$(id -gn "${AGENT_USER}")" == "${WEB_GROUP}" ]] || \
    fail "${AGENT_USER} debe usar ${WEB_GROUP} como grupo principal."

install -d -o "${AGENT_USER}" -g "${WEB_GROUP}" -m 0750 "${AGENT_HOME}"
write_environment
normalize_repository_permissions
write_systemd_units

run_bootstrap_with_retries || \
    fail 'El bootstrap falló tres veces; el agente no se activó.'

normalize_repository_permissions
remove_legacy_root_cron
systemctl daemon-reload
systemctl enable --now caope-scheduler.timer caope-queue.service
systemctl start caope-scheduler.service

systemctl is-active --quiet caope-scheduler.timer || fail 'El timer de CAOPE no quedó activo.'
systemctl is-active --quiet caope-queue.service || fail 'El trabajador de colas no quedó activo.'

cd -- "${APPLICATION_ROOT}" || fail 'No fue posible entrar al directorio de Laravel para la verificación final.'
run_as_agent "${PHP_BIN}" artisan caope:security-audit --profile=production --no-interaction
run_as_agent "${PHP_BIN}" artisan about --only=environment

echo
echo 'AGENTE_CAOPE=LISTO'
echo "USUARIO=${AGENT_USER} (sin login y sin privilegios root)"
echo 'SCHEDULER=ACTIVO'
echo 'COLA=ACTIVA'
echo 'CACHE=DATABASE'
echo "REVISION=${remote_sha}"
