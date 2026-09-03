# Commands

Every command for the Africa CDC DHIS Performance Monitor, in one place.

All paths are relative to this folder, so start with:

```bash
cd "/Users/theodorekoulolias/Desktop/MIS dahsboard"
```

Homebrew tools are called by full path (`/opt/homebrew/bin/...`) because they are
not on the default `PATH` in every shell.

---

## Run it

| What | Command |
|---|---|
| Start (also starts MariaDB) | `./tools/dev/start.sh` |
| Stop the web server | `./tools/dev/start.sh stop` |
| Open it | http://127.0.0.1:8791/login |
| Server log | `tail -f /tmp/phpserver.log` |
| Database log | `tail -f /tmp/mariadb.log` |

Sign in with `kouloliast@africacdc.org` once you have set a password (below), or
the throwaway `afcdc.local` / `afcdc-local`.

---

## Accounts

Set or change a password. Prompts twice with the typing hidden; the value never
becomes a command-line argument, so it cannot leak through `ps` or shell history:

```bash
./tools/dev/set-password.sh kouloliast@africacdc.org
```

Add another account (locked until a password is set):

```bash
/opt/homebrew/bin/mariadb afcdc_dhis -e "INSERT INTO core_users_tbl (username,password,givenname,sn,active,\`group\`) VALUES ('someone@africacdc.org','LOCKED-await-password-choice','First','Last',1,1);"
```

List accounts and whether each has a usable password:

```bash
/opt/homebrew/bin/mariadb afcdc_dhis -e "SELECT id, username, givenname, sn, \`group\`, active, CASE WHEN password LIKE '\$argon2id\$%' THEN 'set' WHEN password REGEXP '^[0-9a-f]{32}$' THEN 'set (legacy hash, upgrades at next sign-in)' ELSE 'LOCKED' END AS password FROM core_users_tbl ORDER BY id;"
```

Groups: `1` System Administrators · `2` Executive · `3` Power · `4` Custom ·
`5` Member State.

Remove the throwaway test account when you no longer need it:

```bash
/opt/homebrew/bin/mariadb afcdc_dhis -e "DELETE FROM core_users_tbl WHERE username='afcdc.local';"
```

---

## The theme

`public/css/skin_africacdc.css` is **generated — never hand-edit it.** Change the
palette in `tools/make_skin_africacdc.py` (the `SLOTS` table) and regenerate:

```bash
/usr/bin/python3 tools/make_skin_africacdc.py
```

Hand-written overrides go in `public/css/custom.css`, which loads last.

Check the generated file still matches the source structure — this should print
`True`, meaning only colours differ:

```bash
/usr/bin/python3 -c "import re;s=lambda L:[re.sub(r'#[0-9a-fA-F]{3,6}\b','#X',re.sub(r'rgba?\([^)]*\)','rgb(X)',l)) for l in L];a=open('tools/skin-source.css').read().splitlines();b=open('public/css/skin_africacdc.css').read().splitlines()[10:2530];print(s(a)==s(b))"
```

---

## The data

Back up before anything (do this first, every time):

```bash
/opt/homebrew/bin/mariadb-dump afcdc_dhis > "backup_$(date +%Y%m%d_%H%M).sql"
```

Load the Africa CDC content. **Replaces** the legacy hierarchy. Two files, in
this order — the first creates the 10 deliverables and 55 activities, the second
makes those activities tickable:

```bash
/opt/homebrew/bin/mariadb --default-character-set=utf8mb4 afcdc_dhis < db/for_upload/africacdc_dhis_seed.sql
```

```bash
/opt/homebrew/bin/mariadb --default-character-set=utf8mb4 afcdc_dhis < db/for_upload/africacdc_dhis_tasks.sql
```

Restore a backup:

```bash
/opt/homebrew/bin/mariadb afcdc_dhis < backup_YYYYMMDD_HHMM.sql
```

Rebuild the **local development** database from scratch. Three files, in order —
schema, content, tickable tasks. None of them contains any legacy tenant data.
This runs against the Mac's own MariaDB; **never run it, or these files, against
the server** — the content files delete every recorded delivery first:

```bash
/opt/homebrew/bin/mariadb -e "DROP DATABASE IF EXISTS afcdc_dhis; CREATE DATABASE afcdc_dhis CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;" && for f in schema seed tasks; do /opt/homebrew/bin/mariadb --default-character-set=utf8mb4 afcdc_dhis < "db/for_upload/africacdc_dhis_$f.sql" >/dev/null; done && echo rebuilt
```

### Checks worth running after any data change

What is loaded:

```bash
/opt/homebrew/bin/mariadb afcdc_dhis -e "SELECT (SELECT COUNT(*) FROM pm_pillars_tbl) AS lenses, (SELECT COUNT(*) FROM pm_objectives_tbl) AS deliverables, (SELECT COUNT(*) FROM pm_programmes_tbl) AS workstreams, (SELECT COUNT(*) FROM pm_projects_tbl) AS activities, (SELECT COUNT(*) FROM pm_projects_tasks_tbl) AS tickable_tasks, (SELECT COUNT(*) FROM pm_progress_tasks_tbl) AS progress_records;"
```

The ten deliverables as the dashboard orders them:

```bash
/opt/homebrew/bin/mariadb afcdc_dhis -e "SELECT l.abbr AS lens, o.abbr AS wbs, o.name AS deliverable, (SELECT COUNT(*) FROM pm_projects_tbl p WHERE p.objective_id=o.id AND p.pillar_id=l.id) AS activities FROM pm_pillars_tbl l JOIN pm_objectives_tbl o ON o.pillar_id=l.id ORDER BY l.position, o.position, o.id;"
```

**The parentage invariant — run this after any hierarchy edit.** `pm_projects_tbl`
stores `pillar_id`, `objective_id` and `programme_id` redundantly and the overview
filters on two of them at once, so a mismatch makes rows vanish with no error and
the gauge silently reads 0.00%. Every number below must be `0`:

```bash
/opt/homebrew/bin/mariadb afcdc_dhis -e "SELECT 'pillar_id disagrees with objective' AS problem, COUNT(*) AS rows_affected FROM pm_projects_tbl p JOIN pm_objectives_tbl o ON o.id=p.objective_id WHERE p.pillar_id<>o.pillar_id UNION ALL SELECT 'objective_id disagrees with programme', COUNT(*) FROM pm_projects_tbl p JOIN pm_programmes_tbl g ON g.id=p.programme_id WHERE p.objective_id<>g.objective_id UNION ALL SELECT 'activity with a dangling objective', COUNT(*) FROM pm_projects_tbl p LEFT JOIN pm_objectives_tbl o ON o.id=p.objective_id WHERE o.id IS NULL;"
```

Open a SQL prompt:

```bash
/opt/homebrew/bin/mariadb afcdc_dhis
```

---

## Recording delivery

In the app: sidebar → **Progress** → open an activity → click the KPI →
set **Result** to `Finished` → **Update**. Percentages recompute immediately.

Who is allowed to record it — `account` is a comma-separated list of user ids:

```bash
/opt/homebrew/bin/mariadb afcdc_dhis -e "SELECT id, name, account FROM pm_members_tbl;"
```

Let every active administrator record delivery:

```bash
/opt/homebrew/bin/mariadb afcdc_dhis -e "UPDATE pm_members_tbl SET account = (SELECT GROUP_CONCAT(id ORDER BY id) FROM core_users_tbl WHERE \`group\`=1 AND active=1) WHERE id=1;"
```

What has been recorded so far:

```bash
/opt/homebrew/bin/mariadb afcdc_dhis -e "SELECT p.abbr AS awp_code, p.name AS activity, g.result, g.progress_date, g.actual_budget FROM pm_progress_tasks_tbl g JOIN pm_projects_tbl p ON p.id=g.project_id ORDER BY g.progress_date DESC;"
```

Clear all recorded progress and start again — **local development database only**:

```bash
/opt/homebrew/bin/mariadb afcdc_dhis -e "DELETE FROM pm_progress_tasks_tbl;"
```

---

## Before you commit or deploy

Lint every PHP file — must report `0 failing`:

```bash
n=0; b=0; while IFS= read -r f; do n=$((n+1)); /opt/homebrew/bin/php -l "$f" >/dev/null 2>&1 || { b=$((b+1)); echo "FAIL $f"; }; done < <(find app -name '*.php'); echo "$n files, $b failing"
```

Check the JS, the JSON and the CSS brace balance:

```bash
node --check public/js/members_graphs.js && node --check public/js/page_projects_graphs_projects.js && /usr/bin/python3 -c "import json;[json.load(open(f)) for f in ['db/menus/ce_menu.json']];print('json ok')" && /usr/bin/python3 -c "t=open('public/css/custom.css').read();print('css braces ok' if t.count('{')==t.count('}') else 'CSS BRACE MISMATCH')"
```

Confirm no local database credentials are about to ship:

```bash
grep -rn "afcdc_local_dev\|afcdc_dhis" app/ public/css public/js db/menus db/models_settings 2>/dev/null | grep -v "settings.local.php" || echo "clean"
```

`app/configuration/settings.local.php` holds the local credentials and is
git-ignored. **Never put them in `settings.php`.**

---

## Deploying to the server

The server runs the Docker stack (see **Docker (production)** below). A deploy
is `git pull` followed by `./setup-production.sh deploy` — nothing else.

- **Never run anything from `db/for_upload/` on the server.** Both content
  files begin by deleting every row of every `pm_*` table: every recorded
  delivery and every activity anyone added would go. They are for a brand-new,
  empty database only, and the container loads them itself in that one case.
- Do not copy files across by hand (`rsync`, `scp`). The Mac's `.env` would
  overwrite the server's and take the site down; the code is built into the
  image anyway.
- The `backups/` folder on this Mac holds local-development snapshots. It is
  not a rollback for production — the server keeps its own under its `backups/`.

On the server, in order:

```bash
git status --short
```

```bash
git rev-parse --short HEAD
```

Write that commit id down: it is what you roll back to (see **Rolling back**).

```bash
docker compose ps
```

Exactly `db` and `app`, both `(healthy)`. A stray `web` container from the very
first compose file would keep port 8081 busy — the deploy removes orphans, but
look before, not after.

```bash
docker compose exec -T db sh -c 'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "SELECT 1"'
```

Must print `1`. The container that starts after the deploy changes the schema
as root (it widens the password column and adds the budget columns); if root
cannot sign in, the site stays down with a clear message. `.env` must still hold
the `DB_ROOT_PASSWORD` the database was first started with.

```bash
docker diff "$(docker compose ps -q app)" | grep -E ' /var/www/html/(db|app)/' | grep -v '/app/configuration'
```

The production host's reverse proxy is on another machine, so its `.env` must
carry `HTTP_BIND=0.0.0.0` (the app listens on all interfaces; the repository
default is loopback). Check it is there — without it the deploy binds to
127.0.0.1 and the proxy can no longer reach the site:

```bash
grep -q '^HTTP_BIND=0.0.0.0' .env && echo present || echo "ADD HTTP_BIND=0.0.0.0 to .env first"
```

Ignore `app/configuration/` — the container writes `settings.local.php` there on
every start. Anything else listed is a file the live app wrote (or a hand edit
on the server) and must be dealt with before pulling — copy it out with
`docker compose cp app:/var/www/html/db ./db-live`, or commit it.

```bash
./setup-production.sh backup
```

```bash
git pull && ./setup-production.sh deploy
```

```bash
docker compose logs app | grep -i "schema\|content\|DDL\|widening\|adding"
```

The line must say `leaving schema and content alone`; on this release it is
followed by `widening core_users_tbl.password` and three `adding
pm_projects_tbl.…` lines (once, never again). Then confirm no number fell:

```bash
docker compose exec -T db sh -c 'exec mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" -e "SELECT (SELECT COUNT(*) FROM pm_pillars_tbl) AS lenses, (SELECT COUNT(*) FROM pm_objectives_tbl) AS deliverables, (SELECT COUNT(*) FROM pm_programmes_tbl) AS workstreams, (SELECT COUNT(*) FROM pm_projects_tbl) AS activities, (SELECT COUNT(*) FROM pm_projects_tasks_tbl) AS tickable_tasks, (SELECT COUNT(*) FROM pm_progress_tasks_tbl) AS progress_records;"'
```

(The `/opt/homebrew/bin/mariadb` commands elsewhere in this file talk to the
Mac's own database — they do not work on the server.)

Open the site: the first request shows the sign-in page (old sessions are
gone), then the Overview. Per-user list preferences (rows per page and the
like) start from the defaults once, because this release moves them into a
volume; the old files are inside `backups/appdata_<stamp>.tar.gz` under
`users_settings/` if anyone wants theirs back:

```bash
tar -xzf backups/appdata_<stamp>.tar.gz && docker compose cp appdata_<stamp>/users_settings/. app:/var/www/html/db/users_settings/ && docker compose exec -T app chown -R www-data:www-data /var/www/html/db/users_settings && rm -r appdata_<stamp>
```

### Rolling back

Code: check out the previous commit and deploy it again — the widened password
column and the three added columns do not bother the old code.

```bash
git checkout <previous commit> && ./setup-production.sh deploy
```

Data, only if something was written that has to go: restore the dump the deploy
took first (it runs as root, so it works after the privilege step below too).

```bash
./setup-production.sh restore backups/afcdc_dhis_<stamp>.sql.gz
```

### One-time steps for the 2 September 2026 release

**1. Give the app container the root password for migrations.** The
entrypoint now widens `core_users_tbl.password` (for `password_hash`) and
loads the schema on a fresh install as root, so the app's own user can be
limited. Add to the server's `.env` if it is not already there (it is, if the
stack was installed with `setup-production.sh`):

```bash
grep -q '^DB_ROOT_PASSWORD=' .env && echo "present" || echo "ADD DB_ROOT_PASSWORD to .env first"
```

**2. After the deploy, reduce the application user's privileges** — it held
`ALL PRIVILEGES`, which turns any SQL injection into schema destruction. Run
once, on the server (`afcdc` is the default `DB_USER`; check `.env`):

```bash
docker compose exec -T db sh -c 'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "REVOKE ALL PRIVILEGES, GRANT OPTION FROM \"$MARIADB_USER\"@\"%\"; GRANT SELECT, INSERT, UPDATE, DELETE ON \`$MARIADB_DATABASE\`.* TO \"$MARIADB_USER\"@\"%\"; FLUSH PRIVILEGES; SHOW GRANTS FOR \"$MARIADB_USER\"@\"%\";"'
```

The last line printed must read `GRANT SELECT, INSERT, UPDATE, DELETE ON
\`afcdc_dhis\`.* TO \`afcdc\`@\`%\``. (The form `REVOKE … ON afcdc_dhis.*`
fails with "no such grant" on this MariaDB image, because the image created
the original grant with the underscore escaped; revoking everything from the
user first sidesteps that.) Then sign in once and open a list page to confirm
the site still works — reads and writes need nothing more than these four.

To reverse it:

```bash
docker compose exec -T db sh -c 'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "GRANT ALL PRIVILEGES ON \`$MARIADB_DATABASE\`.* TO \"$MARIADB_USER\"@\"%\"; FLUSH PRIVILEGES; SHOW GRANTS FOR \"$MARIADB_USER\"@\"%\";"'
```

Nothing in the application issues DDL at runtime; only the container start
does, as root — and `backup` / `restore` run as root too.

**3. Everyone signs in again once.** Sessions from before the release do not
carry the new CSRF token; the first request after the deploy shows the login
page. Passwords are unchanged — each account's hash is upgraded silently the
next time it signs in.

**4. Removed on purpose:** the Configuration and Configure JSON editors, the
Deploy / git pull item, the Tools menu, the file manager, and the image
helpers (`ngine_*.php`). Configuration changes go through git and a deploy.

---

## Checking the rebrand held

Nothing in any rendered page should name the previous tenant or the underlying
vendor. One deliberate exception: an attribution line in the Credits card on
`/system/about`.

```bash
grep -rniI "sadc\|crystalengine\|crystalweb\|crwb\|starfan\|brainregain" app/ public/ db/ tools/ *.md --exclude-dir=vendor 2>/dev/null || echo clean
```

---

## Known gaps

- **`pm_members_tbl` now holds one entity, "Africa CDC — DHIS (HQ)".** The 16
  legacy member states were removed and the "Per RCC / Member State" page dropped
  from the menu, because under HQ-only reporting it has nothing to rank. The
  controller and view still exist if per-RCC reporting is wanted later.
- Deliverable dates, budgets and the $872k have nowhere to live — the schema has
  no columns for them. Actual spend per activity now does
  (`pm_progress_tasks_tbl.actual_budget`).
- AWP line `4.2.4.06.08` (Zoom, $60,000) is not mapped to any deliverable in the
  source workbook, so it is not seeded.
- Passwords: `password_hash()` (Argon2id) since the 2 September 2026 release;
  an account still carrying the old unsalted `MD5(MD5())` hash is upgraded
  the first time it signs in.
- Four external-lens deliverables (2.1, 2.3, 2.4, 2.5) have no AWP activity
  behind them, so they have nothing to tick and will read 0% indefinitely. That
  is the source data, not a bug.

---

## Docker (production)

One file drives everything — see `setup-production.sh --help`:

```bash
./setup-production.sh            # first install: checks, .env, build, start, health
```

```bash
./setup-production.sh admin kouloliast@africacdc.org   # create/re-password an account
```

```bash
./setup-production.sh deploy     # after a code change: backup, rebuild, restart
```

```bash
./setup-production.sh backup     # timestamped dump into ./backups/
```

The app listens on **127.0.0.1:8081** (HTTP_PORT in `.env`). TLS is the reverse
proxy's job; forward `X-Forwarded-Proto` and `X-Forwarded-For`. On machines with
less than 16 GB set `DB_BUFFER_POOL=512M` in `.env` (tier-M default is 4G).
