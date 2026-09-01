#!/usr/bin/env bash
# Set a user's password without it appearing in shell history, in the process
# list, or on screen.
#
#   ./tools/dev/set-password.sh kouloliast@africacdc.org
#   ./tools/dev/set-password.sh <username> <database>     # default db: afcdc_dhis
#
# The password is read with echo off, escaped, and piped to the client on stdin,
# so it never becomes a command-line argument (argv is world-readable via `ps`).
set -euo pipefail

MARIADB=${MARIADB:-/opt/homebrew/bin/mariadb}
USERNAME=${1:-}
DATABASE=${2:-afcdc_dhis}

if [ -z "$USERNAME" ]; then
  echo "usage: $0 <username> [database]" >&2
  exit 1
fi

if ! "$MARIADB" "$DATABASE" -e "SELECT 1" >/dev/null 2>&1; then
  echo "cannot reach database '$DATABASE' - is MariaDB running? (./tools/dev/start.sh)" >&2
  exit 1
fi

found=$("$MARIADB" "$DATABASE" -N -B -e \
  "SELECT COUNT(*) FROM core_users_tbl WHERE username='${USERNAME//\'/\'\'}';")
if [ "$found" -eq 0 ]; then
  echo "no user '$USERNAME' in $DATABASE" >&2
  exit 1
fi

# echo is only suppressed when stdin is a terminal, so the script stays testable
# by piping input while still hiding a real typed password.
hide() { [ -t 0 ] && stty -echo 2>/dev/null || true; }
show() { [ -t 0 ] && stty echo  2>/dev/null || true; }

printf 'New password for %s: ' "$USERNAME"
hide; trap show EXIT
IFS= read -r pw
show; trap - EXIT; printf '\n'

printf 'Confirm: '
hide; trap show EXIT
IFS= read -r pw2
show; trap - EXIT; printf '\n'

[ "$pw" = "$pw2" ] || { echo "passwords did not match - nothing changed" >&2; exit 1; }
[ -n "$pw" ]       || { echo "empty password refused - nothing changed" >&2; exit 1; }

# Escape backslashes first, then single quotes, for the SQL string literal.
esc=${pw//\\/\\\\}
esc=${esc//\'/\\\'}
user_esc=${USERNAME//\'/\\\'}

# NOTE: this application hashes as MD5(MD5(password)) - app/models/core.php.
# That is the app's existing scheme and this script matches it so the login
# works. It is not a safe way to store passwords; see the warning printed below.
printf "UPDATE core_users_tbl SET password=MD5(MD5('%s')) WHERE username='%s';\n" \
  "$esc" "$user_esc" | "$MARIADB" "$DATABASE"

unset pw pw2 esc

ok=$("$MARIADB" "$DATABASE" -N -B -e \
  "SELECT password REGEXP '^[0-9a-f]{32}\$' FROM core_users_tbl WHERE username='${user_esc}';")
if [ "$ok" = "1" ]; then
  echo "password set for $USERNAME in $DATABASE"
else
  echo "something went wrong - the stored value is not a hash" >&2
  exit 1
fi

cat <<'WARN'

  Note: this app stores passwords as MD5(MD5(password)) with no salt.
  That is fast to brute-force and identical passwords produce identical
  hashes. Do not reuse a password you use anywhere else.
WARN
