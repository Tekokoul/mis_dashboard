Africa CDC DHIS Performance Monitor
===================================

A delivery dashboard for the ten MIS key deliverables of the Africa CDC Division of Digital
Health and Information Systems (DHIS), covering the reporting window **August 2026 – January
2027**.

The deliverables sit under two goals:

* **Internal Lens** — *One Organisation, One Platform*: WBS 1.1 – 1.5, how Africa CDC runs
  itself as one organisation on one digital platform.
* **External Lens** — *Member States & the Continent*: WBS 2.1 – 2.5, what Africa CDC offers
  Member States and the continent.

They are delivered through **55 activities of the FY2026 Annual Workplan (AWP)** — 52 under the
internal goal, 3 under the external. The dashboard rolls a gauge up from activity to
deliverable to goal. No percentage is ever typed in: each one is computed as

    rows in pm_progress_tasks_tbl with result = 1
    ÷ sum over tasks of the number of entities each task applies to

so a deliverable with nothing tickable underneath it reads 0.00% by construction.

---

## The data model

The table names are the platform's generic ones. What they mean *in this deployment* is:

| Table | Rows | Holds |
|---|---|---|
| `pm_pillars_tbl` | 2 | The two goals — Internal, External |
| `pm_objectives_tbl` | 10 | The ten MIS key deliverables (`abbr` = WBS code, `name` = deliverable title) |
| `pm_programmes_tbl` | 6 | Workstreams — one per deliverable that has AWP activity behind it |
| `pm_projects_tbl` | 55 | The FY2026 AWP activities (`abbr` = AWP code, `kpi` = indicator) |
| `pm_projects_tasks_tbl` | 55 | The tickable item per activity — one "Delivered" task each |
| `pm_progress_tasks_tbl` | 0+ | Recorded results — one row per tick, with date and actual spend |

Definitive source for the content: `db/for_upload/africacdc_dhis_seed.sql` (the hierarchy) and
`db/for_upload/africacdc_dhis_tasks.sql` (the tickable layer).

Things that surprise people:

* **Only six programmes, not ten.** Deliverables 2.1, 2.3, 2.4 and 2.5 carry no AWP activity in
  the source workbook, so they get no programme and no activities. They render with a 0.00%
  gauge indefinitely. That is the source data, not a bug.
* **Ordering is explicit.** Both `pm_pillars_tbl` and `pm_objectives_tbl` have a `position`
  column; the deliverables are 1–5 within each goal. Without it the WBS would render in
  whatever order the storage engine felt like.
* **`pm_projects_tasks_tbl.applies_to` is a JSON array of `pm_members_tbl` ids as *strings***
  (`["1"]`). `JSON_CONTAINS` is called with a quoted value, so bare numbers silently match
  nothing.
* **`pm_members_tbl` is the reporting-entity list**, and currently holds exactly one row:
  Africa CDC — DHIS (HQ). Its `account` column is a comma-separated list of the user ids
  permitted to record delivery.
* **No progress is seeded.** Every deliverable starts *Not finished*, which is what the source
  workbook says.

---

## The trap: three redundant parent keys

`pm_projects_tbl` stores `pillar_id`, `objective_id` **and** `programme_id` on every row, and
the views filter on two of them at once — see `app/controllers/projects_graphs.php`:

```php
// overview()               line 36
WHERE objective_id = <objective> AND pillar_id = <pillar>
// objective(), programme() lines 263, 357
WHERE programme_id = <programme> AND objective_id = <objective>
```

So if you re-parent an activity and update only one of the three keys, **the row matches
nothing**. There is no error and no warning: the activity vanishes from the count, and its
deliverable's gauge reads 0.00% as though the work did not exist.

**Run this after any hierarchy edit. Every number must be `0`:**

```sql
SELECT 'pillar_id disagrees with objective' AS problem, COUNT(*) AS rows_affected
  FROM pm_projects_tbl p
  JOIN pm_objectives_tbl o ON o.id = p.objective_id
 WHERE p.pillar_id <> o.pillar_id
UNION ALL
SELECT 'objective_id disagrees with programme', COUNT(*)
  FROM pm_projects_tbl p
  JOIN pm_programmes_tbl g ON g.id = p.programme_id
 WHERE p.objective_id <> g.objective_id
UNION ALL
SELECT 'activity with a dangling objective', COUNT(*)
  FROM pm_projects_tbl p
  LEFT JOIN pm_objectives_tbl o ON o.id = p.objective_id
 WHERE o.id IS NULL;
```

`COMMANDS.md` carries the same query as a one-line shell command you can paste directly.

---

## Requirements

* PHP 8.2+
* PDO
* MySQL / MariaDB, `utf8mb4` — the deliverable titles contain `—`, `↔` and curly quotes, so a
  latin1 client connection will mojibake them
* A web server with a single entry point at `public/index.php`

## Configuration

* `app/configuration/settings.php` — the live settings: project constants, the `_WHITELABEL_*`
  branding block, and the database credentials.
* `app/configuration/settings.template` — what a fresh deployment is provisioned from. Copy it
  to `settings.php` and fill in the placeholders.
* `app/configuration/settings.local.php` — optional local overrides, loaded at the end of
  `settings.php` when present and excluded from version control. **Local database credentials
  go here, never in `settings.php`.**

## The theme

`public/css/skin_africacdc.css` is **generated. Never hand-edit it.** Change the palette in the
`SLOTS` table of `tools/make_skin_africacdc.py` and regenerate:

```bash
/usr/bin/python3 tools/make_skin_africacdc.py
```

Hand-written overrides go in `public/css/custom.css`, which loads last and is safe to edit
directly.

## Where to go next

* **`COMMANDS.md`** — every command: running it, accounts and passwords, loading and backing up
  the data, the verification queries, the pre-commit lint pass, and deployment.
* **`tools/dev/README.md`** — from-scratch local setup, and the things that look wrong locally
  but are fine in production.

## Credits

Built on the CrystalEngine PHP MVC framework.
