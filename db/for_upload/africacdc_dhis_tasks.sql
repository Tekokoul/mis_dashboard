-- ===========================================================================
--  FIRST INSTALL ONLY. NEVER RUN THIS AGAINST A DATABASE THAT IS IN USE.
--  It deletes every recorded delivery (pm_progress_tasks_tbl), every tickable
--  task and the reporting entity before re-creating them. In production the
--  container loads it by itself, once, right after the schema.
-- ===========================================================================
--  Africa CDC DHIS Performance Monitor
--  Makes the 55 AWP activities tickable, under the "DHIS HQ reports" model.
--
--  Run this AFTER africacdc_dhis_seed.sql.
--
--  BACK UP FIRST:
--    mariadb-dump <database> > backup_before_tasks.sql
--
--  Load with:
--    mariadb --default-character-set=utf8mb4 <database> < africacdc_dhis_tasks.sql
--
--  WHAT THIS DOES
--  The dashboard computes every percentage as
--      (rows in pm_progress_tasks_tbl with result=1)
--        / (sum over tasks of how many entities each task applies to)
--  so nothing is tickable, and no gauge has a denominator, until
--  pm_projects_tasks_tbl has rows. This creates exactly one task per activity,
--  assigned to a single reporting entity: Africa CDC DHIS HQ. Ticking that task
--  marks the activity delivered.
--
--  WHAT THIS DOES NOT DO
--  It records no progress. Every activity starts Not finished, which is what
--  the source workbook says. Nothing here invents a completion.
-- ===========================================================================

-- ---------------------------------------------------------------------------
-- STEP 1  Let more than one account report for the same entity.
--
-- pm_members_tbl.account is int(11) and projects.php looks up the reporting
-- entity with "where account = <user id>", so exactly one user could ever tick
-- anything. Under an HQ-reports model that is the whole DHIS team, not one
-- person. Widening the column to a comma-separated list, together with the
-- FIND_IN_SET lookup in app/controllers/projects.php, lets several accounts
-- report for HQ. A single bare number still works unchanged.
--
-- DDL commits implicitly, so it runs before the transaction below.
-- ---------------------------------------------------------------------------
SET @needs_widening := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name   = 'pm_members_tbl'
      AND column_name  = 'account'
      AND data_type    <> 'varchar'
);
SET @ddl := IF(@needs_widening > 0,
    'ALTER TABLE pm_members_tbl MODIFY COLUMN `account` VARCHAR(255) DEFAULT NULL',
    'SELECT ''account is already varchar - skipping'' AS note');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- STEP 1b  Add the column the progress form has always tried to write.
--
-- app/controllers/projects.php::task_progress_update() writes `actual_budget`
-- into pm_progress_tasks_tbl, but no version of the schema has ever had that
-- column - so recording a KPI result failed with a database error every time.
-- It was latent in the legacy build only because the 32 progress rows there were
-- loaded directly rather than entered through the form.
--
-- Keeping the column (rather than stripping the field) is deliberate: the AWP
-- carries a budget per activity, so actual spend per activity is worth having.
-- ---------------------------------------------------------------------------
SET @needs_budget := (
    SELECT COUNT(*) = 0 FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name   = 'pm_progress_tasks_tbl'
      AND column_name  = 'actual_budget'
);
SET @ddl2 := IF(@needs_budget,
    'ALTER TABLE pm_progress_tasks_tbl ADD COLUMN `actual_budget` DOUBLE DEFAULT NULL',
    'SELECT ''actual_budget already present - skipping'' AS note');
PREPARE stmt2 FROM @ddl2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;


START TRANSACTION;

-- ---------------------------------------------------------------------------
-- STEP 2  One reporting entity.
--
-- The legacy member states are removed rather than deactivated: none of the
-- overview queries filter on `active`, so a deactivated row still counts
-- towards denominators and still renders.
--
-- `account` is left NULL on purpose. Fill it in with the user ids that should
-- be able to record progress - see the note printed at the end.
-- ---------------------------------------------------------------------------
DELETE FROM pm_progress_tasks_tbl;
DELETE FROM pm_projects_tasks_tbl;
DELETE FROM pm_members_tbl;

INSERT INTO pm_members_tbl (`id`, `name`, `description`, `account`, `active`) VALUES
    (1,
     'Africa CDC — DHIS (HQ)',
     'Digital Health and Information Systems Division. Under the current model DHIS HQ records delivery centrally for every MIS activity; there is no per-Member-State reporting.',
     NULL,
     1);

-- ---------------------------------------------------------------------------
-- STEP 3  One task per activity, assigned to HQ.
--
-- applies_to is a JSON array of pm_members_tbl ids and is what the rollup
-- counts, so ["1"] gives every activity a denominator of exactly 1: delivered
-- or not. The ids are member ids as STRINGS - JSON_CONTAINS is called with a
-- quoted value in projects.php ('"1"'), so numbers would not match.
--
-- Generated from the activities themselves, so this stays correct if the
-- activity set changes.
-- ---------------------------------------------------------------------------
INSERT INTO pm_projects_tasks_tbl (`project_id`, `tasks`, `name`, `description`, `applies_to`)
SELECT p.id,
       NULL,
       'Delivered',
       CONCAT(COALESCE(p.abbr, ''), ' ', COALESCE(p.name, '')),
       '["1"]'
FROM pm_projects_tbl p
ORDER BY p.id;

COMMIT;


-- ===========================================================================
--  VERIFICATION
-- ===========================================================================

SELECT 'reporting entities' AS check_name, COUNT(*) AS actual, 1  AS expected FROM pm_members_tbl
UNION ALL
SELECT 'tickable tasks',                   COUNT(*),          55     FROM pm_projects_tasks_tbl
UNION ALL
SELECT 'activities',                       COUNT(*),          55     FROM pm_projects_tbl
UNION ALL
SELECT 'progress records (must be 0)',     COUNT(*),          0      FROM pm_progress_tasks_tbl
UNION ALL
SELECT 'activities with no task (must be 0)', COUNT(*),       0
FROM pm_projects_tbl p
LEFT JOIN pm_projects_tasks_tbl t ON t.project_id = p.id
WHERE t.id IS NULL
UNION ALL
SELECT 'tasks HQ cannot see (must be 0)',  COUNT(*),          0
FROM pm_projects_tasks_tbl
WHERE NOT JSON_CONTAINS(applies_to, '"1"');

-- What each lens will now measure against.
SELECT l.abbr                        AS lens,
       o.abbr                        AS wbs,
       o.name                        AS deliverable,
       COUNT(DISTINCT p.id)          AS activities,
       COUNT(DISTINCT t.id)          AS tickable,
       SUM(CASE WHEN g.result = '1' THEN 1 ELSE 0 END) AS delivered
FROM pm_pillars_tbl l
JOIN pm_objectives_tbl     o ON o.pillar_id  = l.id
LEFT JOIN pm_projects_tbl  p ON p.objective_id = o.id AND p.pillar_id = l.id
LEFT JOIN pm_projects_tasks_tbl t ON t.project_id = p.id
LEFT JOIN pm_progress_tasks_tbl g ON g.task_id = t.id AND g.result = '1'
GROUP BY l.position, o.position, o.id, l.abbr, o.abbr, o.name
ORDER BY l.position, o.position, o.id;

SELECT CONCAT(
  'NEXT STEP: link the accounts that may record progress, e.g.  ',
  'UPDATE pm_members_tbl SET account = ''',
  COALESCE((SELECT GROUP_CONCAT(id ORDER BY id) FROM core_users_tbl WHERE `group` = 1 AND active = 1), '1'),
  ''' WHERE id = 1;'
) AS todo;
