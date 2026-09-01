#!/usr/bin/env bash
# Start the Africa CDC DHIS Performance Monitor locally. Idempotent.
#
#   ./tools/dev/start.sh          start (or restart) the server
#   ./tools/dev/start.sh stop     stop the web server
#
# Development only. Nothing in app/ or public/ depends on this script.
set -euo pipefail
cd "$(dirname "$0")/../.."

BREW=/opt/homebrew/bin
PORT=8791

if [ "${1:-start}" = "stop" ]; then
  lsof -ti :$PORT | xargs kill -9 2>/dev/null || true
  echo "stopped the web server on :$PORT (MariaDB left running)"
  exit 0
fi

if [ ! -f app/configuration/settings.local.php ]; then
  echo "missing app/configuration/settings.local.php - see tools/dev/README.md" >&2
  exit 1
fi

# 1. database
if ! "$BREW/mariadb" -e "SELECT 1" >/dev/null 2>&1; then
  echo "starting MariaDB..."
  "$BREW/mysqld_safe" --datadir="$BREW/../var/mysql" >/tmp/mariadb.log 2>&1 &
  for _ in $(seq 1 30); do
    "$BREW/mariadb" -e "SELECT 1" >/dev/null 2>&1 && break
    sleep 1
  done
fi
"$BREW/mariadb" -e "SELECT 1" >/dev/null 2>&1 || {
  echo "MariaDB did not come up; see /tmp/mariadb.log" >&2; exit 1; }

# 2. web server
lsof -ti :$PORT | xargs kill -9 2>/dev/null || true
# E_DEPRECATED is suppressed only because settings.php still uses E_STRICT, which
# PHP 8.4+ removed. Production runs PHP 8.2, where it does not warn.
"$BREW/php" -d error_reporting="E_ALL & ~E_DEPRECATED" \
            -S 127.0.0.1:$PORT -t . tools/dev/router.php >/tmp/phpserver.log 2>&1 &
sleep 2

if curl -sf -o /dev/null "http://127.0.0.1:$PORT/login"; then
  cat <<EOF

  Africa CDC DHIS Performance Monitor is running.

    http://127.0.0.1:$PORT/login

  stop it with:  ./tools/dev/start.sh stop

EOF
else
  echo "the server did not answer; see /tmp/phpserver.log" >&2
  tail -5 /tmp/phpserver.log >&2
  exit 1
fi
