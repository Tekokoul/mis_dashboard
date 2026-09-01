# Running the dashboard locally

There is no static mock in this repo, on purpose: an earlier attempt at one drifted
from the real view within a day and showed a design the application would never
render. Run the real thing instead — it takes about a minute.

## The short version

Everything below is already done on this machine. To just run it:

    ./tools/dev/start.sh

then open http://127.0.0.1:8791/login  (user `afcdc.local`, password `afcdc-local`).
Stop it with `./tools/dev/start.sh stop`.

The rest of this file is the from-scratch setup, for a different machine.

## What you need

    brew install php mariadb

## 1. Database

    mysqld_safe --datadir=/opt/homebrew/var/mysql &

    mariadb -e "CREATE DATABASE afcdc_dhis CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
                CREATE USER 'afcdc'@'127.0.0.1' IDENTIFIED BY '<pick-one>';
                GRANT ALL ON afcdc_dhis.* TO 'afcdc'@'127.0.0.1';"

Load the schema, then the Africa CDC content:

    mariadb afcdc_dhis < db/for_upload/africacdc_dhis_schema.sql
    mariadb --default-character-set=utf8mb4 afcdc_dhis < db/for_upload/africacdc_dhis_seed.sql

The `--default-character-set=utf8mb4` is not optional — the deliverable titles
contain `—`, `↔` and curly quotes.

## 2. Point the app at it

Do **not** edit the credentials in `app/configuration/settings.php` — they are the
production placeholders and editing them is how local passwords get committed.
Create `app/configuration/settings.local.php` instead; the bottom of `settings.php`
loads it when present, and `.gitignore` excludes it:

```php
<?php
$settings['db_master']['db_host']     = '127.0.0.1';
$settings['db_master']['db_database'] = 'afcdc_dhis';
$settings['db_master']['db_user']     = 'afcdc';
$settings['db_master']['db_password'] = base64_encode('<the password you picked>');
```

## 3. Give yourself a login

Passwords are `MD5(MD5(plaintext))` (`app/models/core.php::create_password`):

    mariadb afcdc_dhis -e "UPDATE core_users_tbl
                           SET username='you', password=MD5(MD5('<pick-one>'))
                           WHERE id=1;"

## 4. Serve it

    ./tools/dev/start.sh

which is a wrapper around:

    php -S 127.0.0.1:8791 -t . tools/dev/router.php

`router.php` exists because PHP's built-in server has no `.htaccess`: it serves
real files directly and front-controllers everything else, mirroring
`public/.htaccess` lines 20-23. It also `chdir`s into `public/`, because
`public/index.php` requires `../app/bootstrap.php` relative to the working
directory. It is a development aid only — nothing in `app/` or `public/` depends
on it, and it must never be deployed.

Then open http://127.0.0.1:8791/login

## Things that will look wrong locally but are fine in production

- **Every gauge reads 0.00%.** Progress is counted from `pm_progress_tasks_tbl`,
  and the seed creates no progress rows because all ten deliverables are
  "Not started" in the source workbook. The activity counts, parentage and
  ordering are real; the percentages have nothing to measure yet.
- **`Deprecated: Constant E_STRICT`** on PHP 8.4+. Pre-existing, from
  `settings.php:133`. Production runs PHP 8.2, where it does not appear.
  Suppress locally with `-d error_reporting="E_ALL & ~E_DEPRECATED"`.
- **One reporting entity.** `pm_members_tbl` holds only "Africa CDC — DHIS (HQ)";
  delivery is recorded centrally, so there is no per-Member-State view.
