#!/bin/bash
# Apache (web tier) container entrypoint.
#
# Renders the vhost from its template and waits for PHP-FPM to accept
# connections, so Apache never starts proxying to a socket that is not there.
set -euo pipefail

log() { printf '[web] %s\n' "$*"; }
die() { printf '[web] ERROR: %s\n' "$*" >&2; exit 1; }

: "${APP_URL:?APP_URL is required, e.g. https://dhis.africacdc.org}"
: "${FPM_HOST:=app}"
: "${FPM_PORT:=9000}"
: "${TRUSTED_PROXY_CIDR:=172.16.0.0/12}"
: "${REQUIREMENTS_ALLOW_FROM:=127.0.0.1}"

APP_SCHEME="${APP_URL%%://*}"
APP_HOST="${APP_URL#*://}"; APP_HOST="${APP_HOST%%/*}"
[ "$APP_SCHEME" = "http" ] || [ "$APP_SCHEME" = "https" ] \
    || die "APP_URL must start with http:// or https:// (got '$APP_URL')"

TPL=/etc/apache2/sites-available/000-default.conf.tpl
DST=/etc/apache2/sites-available/000-default.conf
[ -f "$TPL" ] || die "missing $TPL"
sed -e "s|__SERVER_NAME__|${APP_HOST}|g" \
    -e "s|__TRUSTED_PROXY_CIDR__|${TRUSTED_PROXY_CIDR}|g" \
    -e "s|__REQUIREMENTS_ALLOW_FROM__|${REQUIREMENTS_ALLOW_FROM}|g" \
    -e "s|__FPM_HOST__|${FPM_HOST}|g" \
    -e "s|__FPM_PORT__|${FPM_PORT}|g" \
    "$TPL" > "$DST"
if grep -q '__[A-Z_]\+__' "$DST"; then
    die "unrendered placeholder: $(grep -o '__[A-Z_]\+__' "$DST" | sort -u | tr '\n' ' ')"
fi

# TLS is terminated by the reverse proxy in front of this container, so the
# container itself only ever speaks http and must never issue an https redirect
# of its own. public/.htaccess redirects whenever %{HTTPS} is not "on", which
# would either loop forever or send visitors to a port nothing is listening on.
# mod_rewrite reads %{HTTPS} from the environment, so declaring it here disables
# that rule for good. The .htaccess file itself is left untouched, so a direct
# (non-containerised) Apache deployment still behaves as before.
#
# Redirecting http to https is the proxy's job, at the edge, where the
# certificate lives.
printf '\n# TLS terminates at the reverse proxy; never redirect from inside the container.\nSetEnv HTTPS on\n' >> "$DST"
log "https redirect disabled inside the container - TLS is the reverse proxy's job"

if [ "$APP_SCHEME" = "http" ]; then
    log "NOTE: APP_URL is http, so the session cookie will not be marked Secure."
fi
log "vhost rendered for ${APP_HOST}, proxying PHP to ${FPM_HOST}:${FPM_PORT}"

apache2ctl configtest || die "Apache configuration is invalid"

log "waiting for PHP-FPM at ${FPM_HOST}:${FPM_PORT} ..."
for i in $(seq 1 60); do
    if (exec 3<>"/dev/tcp/${FPM_HOST}/${FPM_PORT}") 2>/dev/null; then
        exec 3>&- 2>/dev/null || true
        log "PHP-FPM reachable after ${i}s"
        break
    fi
    [ "$i" -eq 60 ] && die "PHP-FPM not reachable after 60s"
    sleep 1
done

# apache2ctl needs these; the Debian package sets them in envvars.
export APACHE_RUN_USER=www-data APACHE_RUN_GROUP=www-data \
       APACHE_PID_FILE=/var/run/apache2/apache2.pid \
       APACHE_RUN_DIR=/var/run/apache2 APACHE_LOCK_DIR=/var/lock/apache2 \
       APACHE_LOG_DIR=/var/log/apache2
mkdir -p "$APACHE_RUN_DIR" "$APACHE_LOCK_DIR"
rm -f "$APACHE_PID_FILE"

log "starting Apache"
exec "$@"
