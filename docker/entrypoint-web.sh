#!/bin/sh
# nginx (web tier) container entrypoint. POSIX sh on purpose: the nginx alpine
# image has no bash, and pulling one in just for this script would reintroduce
# a package install this stage otherwise avoids entirely.
#
# Renders nginx config from templates and waits for PHP-FPM before starting, so
# nginx never comes up proxying to an upstream that is not there.
set -eu

log() { printf '[web] %s\n' "$*"; }
die() { printf '[web] ERROR: %s\n' "$*" >&2; exit 1; }

: "${APP_URL:?APP_URL is required, e.g. https://dhis.africacdc.org}"
: "${FPM_HOST:=app}"
: "${FPM_PORT:=9000}"
: "${TRUSTED_PROXY_CIDR:=172.16.0.0/12}"
: "${REQUIREMENTS_ALLOW_FROM:=127.0.0.1}"

APP_HOST="${APP_URL#*://}"; APP_HOST="${APP_HOST%%/*}"
APP_HOST="${APP_HOST%%:*}"           # nginx server_name takes no port
[ -n "$APP_HOST" ] || die "could not derive a hostname from APP_URL='$APP_URL'"

render() {
    local src="$1" dst="$2"
    [ -f "$src" ] || die "missing template $src"
    sed -e "s|__SERVER_NAME__|${APP_HOST}|g" \
        -e "s|__TRUSTED_PROXY_CIDR__|${TRUSTED_PROXY_CIDR}|g" \
        -e "s|__REQUIREMENTS_ALLOW_FROM__|${REQUIREMENTS_ALLOW_FROM}|g" \
        -e "s|__FPM_HOST__|${FPM_HOST}|g" \
        -e "s|__FPM_PORT__|${FPM_PORT}|g" \
        "$src" > "$dst"
    if grep -q '__[A-Z_]\+__' "$dst"; then
        die "unrendered placeholder in $dst: $(grep -o '__[A-Z_]\+__' "$dst" | sort -u | tr '\n' ' ')"
    fi
}
render /etc/nginx/templates/nginx.conf.tpl      /etc/nginx/nginx.conf
render /etc/nginx/templates/nginx-site.conf.tpl /etc/nginx/conf.d/default.conf
log "config rendered for ${APP_HOST}, PHP upstream ${FPM_HOST}:${FPM_PORT}"

nginx -t || die "nginx configuration is invalid"

# /dev/tcp is a bashism; busybox nc is always present on alpine.
log "waiting for PHP-FPM at ${FPM_HOST}:${FPM_PORT} ..."
i=1
while [ "$i" -le 60 ]; do
    if nc -z -w 2 "$FPM_HOST" "$FPM_PORT" 2>/dev/null; then
        log "PHP-FPM reachable after ${i}s"
        break
    fi
    [ "$i" -eq 60 ] && die "PHP-FPM not reachable after 60s"
    i=$((i + 1))
    sleep 1
done

log "starting nginx"
exec "$@"
