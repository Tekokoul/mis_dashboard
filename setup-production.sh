#!/usr/bin/env bash
#
#  Africa CDC DHIS Performance Monitor — production setup
#  ======================================================
#
#  One file, one command. Run it on the production host:
#
#      ./setup-production.sh
#
#  It checks the host, creates .env on first run, builds the image, brings the
#  stack up, waits for the app to become healthy, and offers to create the first
#  administrator. It is safe to re-run: an existing database is never re-seeded
#  and an existing .env is never overwritten.
#
#  Other subcommands:
#      ./setup-production.sh deploy    rebuild and restart after a code change
#      ./setup-production.sh backup    dump the database to ./backups
#      ./setup-production.sh restore <file>
#      ./setup-production.sh admin <email>
#      ./setup-production.sh status | logs | stop | destroy
#      ./setup-production.sh check     preflight only, change nothing
#
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"

# ---------------------------------------------------------------------------
# output helpers
# ---------------------------------------------------------------------------
if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
    G=$'\033[0;32m'; Y=$'\033[0;33m'; R=$'\033[0;31m'; B=$'\033[1m'; N=$'\033[0m'
else
    G=""; Y=""; R=""; B=""; N=""
fi
say()  { printf '%s\n' "$*"; }
ok()   { printf '  %s✓%s %s\n' "$G" "$N" "$*"; }
warn() { printf '  %s!%s %s\n' "$Y" "$N" "$*"; }
bad()  { printf '  %s✗%s %s\n' "$R" "$N" "$*"; }
head_() { printf '\n%s%s%s\n' "$B" "$*" "$N"; }
die()  { printf '\n%sERROR:%s %s\n' "$R" "$N" "$*" >&2; exit 1; }

COMPOSE_FILE=docker-compose.yml
ENV_FILE=.env

# ---------------------------------------------------------------------------
# .env loading
#
# Do NOT `source` the .env file. Compose parses it with its own KEY=VALUE reader
# where an unquoted value may contain spaces - APP_NAME is exactly that - but
# shell sourcing would try to execute the second word as a command. Parse it the
# way compose does instead, so this script and compose always agree.
# ---------------------------------------------------------------------------
load_env() {
    [ -f "$ENV_FILE" ] || return 0
    local line key value
    while IFS= read -r line || [ -n "$line" ]; do
        case "$line" in ''|'#'*) continue ;; esac
        case "$line" in *=*) ;; *) continue ;; esac
        key=${line%%=*}
        value=${line#*=}
        key=${key# }; key=${key%% }
        case "$key" in *[!A-Za-z0-9_]*|'') continue ;; esac
        # strip one layer of matching quotes, as compose does
        case "$value" in
            \"*\") value=${value#\"}; value=${value%\"} ;;
            \'*\') value=${value#\'}; value=${value%\'} ;;
        esac
        export "$key=$value"
    done < "$ENV_FILE"
}

# ---------------------------------------------------------------------------
# docker / compose discovery
# ---------------------------------------------------------------------------
find_docker() {
    DOCKER=$(command -v docker || true)
    [ -n "$DOCKER" ] || die "docker is not installed. Install Docker Engine, then re-run.
  Debian/Ubuntu:  curl -fsSL https://get.docker.com | sh
  RHEL/Alma:      sudo dnf install -y docker-ce docker-compose-plugin"
    "$DOCKER" info >/dev/null 2>&1 || die "the docker daemon is not reachable.
  Start it:       sudo systemctl start docker
  Or add yourself to the docker group:  sudo usermod -aG docker \$USER  (then log out and in)"
    if "$DOCKER" compose version >/dev/null 2>&1; then
        COMPOSE=("$DOCKER" compose)
    elif command -v docker-compose >/dev/null 2>&1; then
        COMPOSE=(docker-compose)
    else
        die "neither 'docker compose' nor 'docker-compose' is available. Install the compose plugin."
    fi
}
dc() { "${COMPOSE[@]}" -f "$COMPOSE_FILE" "$@"; }
# True when the named compose service has a running container.
svc_running() { dc ps --status running --services 2>/dev/null | grep -qx "$1"; }

# ---------------------------------------------------------------------------
# preflight
# ---------------------------------------------------------------------------
preflight() {
    head_ "Checking the host"
    find_docker
    ok "docker $("$DOCKER" version --format '{{.Server.Version}}' 2>/dev/null || echo present)"
    ok "compose $("${COMPOSE[@]}" version --short 2>/dev/null || echo present)"

    for f in Dockerfile docker-compose.yml docker/entrypoint-app.sh docker/entrypoint-web.sh \
             docker/nginx.conf.tpl docker/nginx-site.conf.tpl docker/php.ini.tpl \
             docker/php-fpm-pool.conf docker/opcache.ini docker/mysqld-tuning.cnf \
             db/for_upload/africacdc_dhis_schema.sql \
             db/for_upload/africacdc_dhis_seed.sql \
             db/for_upload/africacdc_dhis_tasks.sql; do
        [ -f "$f" ] || die "missing required file: $f"
    done
    ok "all required files present"

    # Refuse to build if the quarantined legacy material would be included.
    if [ -f .dockerignore ] && grep -q 'removed-sadc-crystalengine' .dockerignore; then
        ok "quarantined legacy material is excluded from the image"
    else
        bad ".dockerignore does not exclude .removed-sadc-crystalengine/"
        die "refusing to build: the previous tenant's data and the old vendor logos would ship in the image"
    fi

    local free
    free=$(df -Pk . | awk 'NR==2 {print int($4/1024/1024)}')
    if [ "${free:-0}" -lt 3 ]; then
        warn "only ${free}GB free here; the image plus database needs roughly 3GB"
    else
        ok "${free}GB disk free"
    fi
}

# ---------------------------------------------------------------------------
# .env
# ---------------------------------------------------------------------------
rand() { LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32; }

ensure_env() {
    head_ "Configuration"
    if [ -f "$ENV_FILE" ]; then
        ok "$ENV_FILE exists (left untouched)"
    else
        [ -f .env.example ] || die "missing .env.example"
        cp .env.example "$ENV_FILE"
        # Generate the two database passwords so nobody ships the placeholder.
        local dbp rootp
        dbp=$(rand); rootp=$(rand)
        sed -i.bak -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${dbp}|" \
                   -e "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=${rootp}|" "$ENV_FILE"
        rm -f "${ENV_FILE}.bak"
        chmod 600 "$ENV_FILE"
        ok "created $ENV_FILE with generated database passwords"
        warn "APP_URL still says CHANGE-ME — set it to the real public address"
    fi
    chmod 600 "$ENV_FILE"

    load_env

    local problems=0
    if [ -z "${APP_URL:-}" ] || case "${APP_URL}" in *CHANGE-ME*) true;; *) false;; esac; then
        bad "APP_URL is not set to a real address (currently: ${APP_URL:-unset})"; problems=1
    else
        ok "APP_URL = ${APP_URL}"
        case "$APP_URL" in
            https://*) ;;
            http://*)  warn "APP_URL is http. The session cookie will not be marked Secure. Use https in production." ;;
            *) bad "APP_URL must start with http:// or https://"; problems=1 ;;
        esac
        # The app emits root-relative URLs (/login, /css/...), so it cannot live
        # under a path prefix: behind e.g. https://host/dashboard every asset
        # and link would escape the prefix and 404. Only the host is used.
        _path="${APP_URL#*://}"; _path="${_path#*/}"
        if [ "$_path" != "${APP_URL#*://}" ] && [ -n "$_path" ]; then
            warn "APP_URL carries a path (/${_path}) which will be IGNORED - the app must be"
            warn "served at the root of its host. Point the proxy's server block for"
            warn "$(printf '%s' "${APP_URL#*://}" | cut -d/ -f1) straight at this container."
        fi
    fi
    for v in DB_PASSWORD DB_ROOT_PASSWORD; do
        local val="${!v:-}"
        if [ -z "$val" ] || case "$val" in *CHANGE-ME*) true;; *) false;; esac; then
            bad "$v is not set"; problems=1
        elif [ "${#val}" -lt 16 ]; then
            warn "$v is shorter than 16 characters"
        else
            ok "$v is set (${#val} chars)"
        fi
    done
    if [ "${DB_PASSWORD:-a}" = "${DB_ROOT_PASSWORD:-b}" ]; then
        bad "DB_PASSWORD and DB_ROOT_PASSWORD must differ"; problems=1
    fi

    [ "$problems" -eq 0 ] || die "fix the values above in $ENV_FILE, then re-run."
}

# ---------------------------------------------------------------------------
# build / up / wait
# ---------------------------------------------------------------------------
build_and_start() {
    head_ "Building"
    BUILD_ID="$(date -u +%Y%m%d%H%M%S)"
    export BUILD_ID
    dc build --build-arg "BUILD_ID=${BUILD_ID}" app
    ok "image built (build ${BUILD_ID})"

    head_ "Starting"
    dc up -d
    ok "containers started"
}

wait_healthy() {
    head_ "Waiting for the application"
    local port="${HTTP_PORT:-8081}" i body
    for i in $(seq 1 90); do
        body=$(curl -fsS "http://127.0.0.1:${port}/health.php" 2>/dev/null || true)
        case "$body" in
            *'"database":"ok"'*)
                ok "healthy after ${i}s — $body"
                return 0 ;;
        esac
        if [ $((i % 15)) -eq 0 ]; then say "    still waiting (${i}s)…"; fi
        sleep 1
    done
    bad "the app did not become healthy within 90s"
    say ""; say "Last 40 lines of the app log:"; dc logs --tail 40 app
    die "startup failed — see the log above"
}

# ---------------------------------------------------------------------------
# first administrator
# ---------------------------------------------------------------------------
maybe_create_admin() {
    local count
    count=$(dc exec -T db sh -c \
        'mysql -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" -N -B \
             -e "SELECT COUNT(*) FROM core_users_tbl" "$MARIADB_DATABASE" 2>/dev/null' \
        | tr -d '\r' || echo 0)

    if [ "${count:-0}" -gt 0 ]; then
        head_ "Accounts"
        ok "${count} account(s) already exist — not creating another"
        return 0
    fi

    head_ "First administrator"
    if [ ! -t 0 ]; then
        warn "no terminal attached, so no account was created. Create one with:"
        say  "    ./setup-production.sh admin you@africacdc.org"
        return 0
    fi
    local email
    read -r -p "  Administrator email (blank to skip): " email
    [ -n "$email" ] || { warn "skipped — create one later with: ./setup-production.sh admin <email>"; return 0; }
    dc exec app php /var/www/html/tools/create-admin.php "$email"
}

summary() {
    local port="${HTTP_PORT:-8081}"
    head_ "Ready"
    say "  The app is listening on 127.0.0.1:${port} — loopback only, by design."
    say "  Point your TLS-terminating reverse proxy at it and forward these headers:"
    say ""
    say "      proxy_set_header Host              \$host;"
    say "      proxy_set_header X-Forwarded-For   \$proxy_add_x_forwarded_for;"
    say "      proxy_set_header X-Forwarded-Proto \$scheme;"
    say "      proxy_pass       http://127.0.0.1:${port};"
    say ""
    say "  X-Forwarded-Proto matters: public/.htaccess redirects http to https, and"
    say "  without that header the container never sees https and will redirect to"
    say "  itself forever."
    say ""
    say "  Then open  ${APP_URL:-https://your-host}/login"
    say ""
    say "  Day to day:"
    say "      ./setup-production.sh deploy    rebuild and restart after a code change"
    say "      ./setup-production.sh backup    dump the database to ./backups"
    say "      ./setup-production.sh logs      follow the application log"
    say "      ./setup-production.sh status    container and health state"
}

# ---------------------------------------------------------------------------
# subcommands
# ---------------------------------------------------------------------------
cmd_install() {
    say "${B}Africa CDC DHIS Performance Monitor — production setup${N}"
    preflight
    ensure_env
    build_and_start
    wait_healthy
    maybe_create_admin
    summary
}

cmd_deploy() {
    preflight; ensure_env
    if svc_running db; then
        head_ "Backing up before deploying"
        cmd_backup quiet
    else
        head_ "Deploying"
        warn "no running database - first deploy on this host, so there is nothing to back up"
    fi
    build_and_start
    wait_healthy
    maybe_create_admin
    summary
}

cmd_backup() {
    find_docker
    load_env
    svc_running db || die "the database container is not running - start the stack first: ./setup-production.sh"
    mkdir -p backups
    local out="backups/afcdc_dhis_$(date -u +%Y%m%d_%H%M%S).sql"
    dc exec -T db sh -c \
        'exec mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" \
              --single-transaction --default-character-set=utf8mb4 "$MARIADB_DATABASE"' \
        > "$out"
    [ -s "$out" ] || { rm -f "$out"; die "the dump came back empty — nothing was written"; }
    gzip -f "$out"
    [ "${1:-}" = "quiet" ] || head_ "Backup"
    ok "wrote ${out}.gz ($(du -h "${out}.gz" | cut -f1))"
}

cmd_restore() {
    local file="${1:-}"
    [ -n "$file" ] || die "usage: ./setup-production.sh restore <file.sql|file.sql.gz>"
    [ -f "$file" ] || die "no such file: $file"
    find_docker
    load_env
    say ""
    warn "This REPLACES the current contents of ${DB_NAME:-afcdc_dhis}."
    read -r -p "  Type the database name to confirm: " confirm
    [ "$confirm" = "${DB_NAME:-afcdc_dhis}" ] || die "aborted — nothing changed"
    cmd_backup quiet
    if [ "${file##*.}" = "gz" ]; then
        gunzip -c "$file" | dc exec -T db sh -c \
            'exec mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" --default-character-set=utf8mb4 "$MARIADB_DATABASE"'
    else
        dc exec -T db sh -c \
            'exec mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" --default-character-set=utf8mb4 "$MARIADB_DATABASE"' \
            < "$file"
    fi
    ok "restored from $file"
}

cmd_admin() {
    local email="${1:-}"
    [ -n "$email" ] || die "usage: ./setup-production.sh admin <email> [group-id]"
    find_docker
    svc_running app || die "the app container is not running - start the stack first: ./setup-production.sh"
    dc exec app php /var/www/html/tools/create-admin.php "$email" "${2:-1}"
}

cmd_status() {
    find_docker
    head_ "Containers"; dc ps
    load_env
    head_ "Health"
    curl -fsS "http://127.0.0.1:${HTTP_PORT:-8081}/health.php" 2>/dev/null || bad "health endpoint not answering"
    say ""
    head_ "Content"
    dc exec -T db sh -c \
      'mysql -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" -t \
        -e "SELECT (SELECT COUNT(*) FROM pm_pillars_tbl) lenses,
                   (SELECT COUNT(*) FROM pm_objectives_tbl) deliverables,
                   (SELECT COUNT(*) FROM pm_projects_tbl) activities,
                   (SELECT COUNT(*) FROM pm_progress_tasks_tbl) recorded,
                   (SELECT COUNT(*) FROM core_users_tbl) accounts" \
        "$MARIADB_DATABASE"' 2>/dev/null || bad "cannot query the database"
}

cmd_logs()    { find_docker; dc logs -f --tail 100 "${1:-app}"; }
cmd_stop()    { find_docker; dc down; ok "stopped — data kept in the named volumes"; }

cmd_destroy() {
    find_docker
    say ""
    warn "This deletes the containers AND the database volume. All recorded progress is lost."
    read -r -p "  Type DESTROY to confirm: " confirm
    [ "$confirm" = "DESTROY" ] || die "aborted — nothing changed"
    dc down -v
    ok "removed"
}

case "${1:-install}" in
    install|"")  cmd_install ;;
    check)       preflight; ensure_env; head_ "Preflight only"; ok "nothing was changed" ;;
    deploy)      cmd_deploy ;;
    backup)      cmd_backup ;;
    restore)     shift; cmd_restore "$@" ;;
    admin)       shift; cmd_admin "$@" ;;
    status)      cmd_status ;;
    logs)        shift; cmd_logs "$@" ;;
    stop)        cmd_stop ;;
    destroy)     cmd_destroy ;;
    -h|--help|help)
        sed -n '2,25p' "${BASH_SOURCE[0]}" | sed 's/^#//;s/^ //' ;;
    *)  die "unknown command '$1'. Try: ./setup-production.sh --help" ;;
esac
