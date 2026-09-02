-- =============================================================================
--  FIRST INSTALL ONLY. NEVER RUN THIS AGAINST A DATABASE THAT IS IN USE.
--  It deletes every row of every pm_* table before inserting - every recorded
--  delivery and every activity anyone added through the dashboard is lost.
--  In production the container loads it by itself, once, on an empty database.
-- =============================================================================
--  africacdc_dhis_seed.sql
--  Africa CDC - Division of Digital Health and Information Systems (DHIS)
--  Re-points the CrystalEngine project-monitoring dashboard from the previous tenant's programme data
--  content to the DHIS MIS key deliverables for Aug 2026 - Jan 2027.
-- =============================================================================
--
--  WHAT THIS DOES
--  --------------
--  Loads the DHIS programme hierarchy into the pm_* tables:
--
--      pm_pillars_tbl      4 rows   the Internal and External Lens, plus the two
--                                   pillars added on the live site (Digital ER, AfCDC development)
--      pm_objectives_tbl  15 rows   the 10 MIS key deliverables (WBS 1.1-1.5, 2.1-2.5)
--                                   plus objectives 3.1-3.3 and 4.1-4.2
--      pm_programmes_tbl   6 rows   one per deliverable that has AWP activities
--      pm_projects_tbl    55 rows   the FY2026 Annual Workplan activities
--
--  Source of truth: tools/data/dhis_deliverables.json
--  Everything here is copied from that file. Nothing is invented.
--
--  *** THIS SCRIPT IS DESTRUCTIVE. IT REPLACES THE LEGACY TENANT'S CONTENT. ***
--
--  It DELETES every row from the ten pm_* content tables listed in step 2
--  below, including all recorded progress. There is no undo inside this
--  script -- the transaction only protects you against a mid-run failure,
--  not against a successful run you later regret.
--
--  *** BACK UP THE DATABASE BEFORE YOU RUN THIS. ***
--
--      mysqldump -u <user> -p <database> > backup_before_dhis_seed.sql
--
--  Verify the dump is non-empty and restorable before continuing.
--
--  HOW TO RUN
--  ----------
--      mysql -u <user> -p --default-character-set=utf8mb4 <database> \
--            < db/for_upload/africacdc_dhis_seed.sql
--
--  The --default-character-set=utf8mb4 flag matters: the source text contains
--  em dashes, curly quotation marks and a left-right arrow. Loading through a
--  latin1 client connection will mojibake them.
--
--  WHAT THIS DOES *NOT* DO
--  -----------------------
--  - It does not touch pm_members_tbl. That table still holds the 17 legacy
--    member states. They are the assignee list the dashboard renders on the
--    members page, and replacing them with Africa CDC RCCs or AU Member
--    States is a separate decision that needs its own source data.
--  - It seeds no rows into pm_projects_tasks_tbl. The source has no task
--    breakdown below activity level and no assignee list. The dashboard
--    derives every gauge from pm_projects_tasks_tbl.applies_to, so with no
--    tasks each gauge reads 0.00% over 0 assignments. That is the correct
--    reading of source data in which all 10 deliverables are 0% / Not
--    started -- but be aware the dashboard will look structurally empty
--    below the project level until tasks and assignees are added.
--  - It seeds no pm_progress_* rows. There is no recorded progress to seed.
--
-- =============================================================================

SET NAMES utf8mb4;
SET SESSION sql_mode = '';


-- =============================================================================
-- STEP 1  Add pm_objectives_tbl.position  (DDL - must run outside the transaction)
-- =============================================================================
--
-- WHY THIS COLUMN IS NEEDED
--
-- projects_graphs.php reads the pillars with an explicit sort:
--
--     SELECT id, name, abbr FROM pm_pillars_tbl ORDER BY position
--
-- but the objectives underneath a pillar were originally read with no sort at
-- all:
--
--     SELECT id, name, abbr FROM pm_objectives_tbl WHERE pillar_id = <n>
--
-- A SELECT without ORDER BY returns rows in whatever order the storage engine
-- finds convenient. With ids 1-10 assigned in WBS order and a primary key
-- scan they will usually come back 1.1, 1.2, 1.3, 1.4, 1.5 by luck. That luck
-- is not a guarantee: it can change after an UPDATE, after a table rebuild, or
-- if the optimiser picks a different index. The five deliverables under a lens
-- would then render in arbitrary order, which for a numbered WBS reads as a bug.
--
-- STATE OF THE CONTROLLER AT THE TIME THIS SCRIPT WAS WRITTEN
--
--   overview()  ALREADY sorts:  "... WHERE pillar_id = <n> ORDER BY position, id"
--               so the front page is correct as soon as this column exists.
--   pillar()    does NOT sort yet. Its objective query is still bare:
--               "SELECT id, name, abbr FROM pm_objectives_tbl WHERE pillar_id = <n>"
--               Appending "ORDER BY position, id" there gives the per-lens
--               drill-down the same guarantee. This script does not make that
--               edit -- it only guarantees the column exists and is populated,
--               so the one-line controller change is safe whenever you make it.
--
-- Until pillar() is updated, the WBS prefix in each objective's name (step 4)
-- keeps the intended order legible to a human reader regardless of row order.
--
-- The guard below makes re-running this script safe. Plain
-- "ALTER TABLE ... ADD COLUMN" errors out on the second run, and MySQL has no
-- "ADD COLUMN IF NOT EXISTS", so the existence check goes through
-- information_schema and a prepared statement.
--
-- This block sits BEFORE START TRANSACTION deliberately: DDL causes an
-- implicit commit in MySQL and would silently end a transaction opened
-- around it.

SET @has_position := (
    SELECT COUNT(*)
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'pm_objectives_tbl'
       AND COLUMN_NAME  = 'position'
);

SET @ddl := IF(@has_position = 0,
    'ALTER TABLE `pm_objectives_tbl` ADD COLUMN `position` INT(11) NOT NULL DEFAULT 0',
    'DO 0'   /* no-op: column already present, this script has run before */
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- =============================================================================
-- STEP 2  Clear the legacy tenant's content
-- =============================================================================
--
-- WHY DELETE RATHER THAN SET active = 0
--
-- Every pm_* content table carries an "active" flag, so deactivating looks
-- like the safer move. It is not, for this dashboard: none of the overview
-- queries filter on it. Check projects_graphs.php --
--
--     SELECT id, name, abbr FROM pm_pillars_tbl ORDER BY position
--     SELECT id, name, abbr FROM pm_objectives_tbl WHERE pillar_id = <n>
--     SELECT id, name FROM pm_projects_tbl WHERE objective_id = <n> AND pillar_id = <n>
--     SELECT id, name, abbr FROM pm_programmes_tbl WHERE objective_id = <n>
--
-- Not one of them says "AND active = 1". The same is true in pillar(),
-- objective(), programme() and projects(). So rows set to active = 0 still
-- render, still get counted into the totals, and still drag every parent
-- gauge toward zero. You would end up with the previous tenant's seven pillars and the
-- two Africa CDC lenses side by side on the front page, and a continental
-- progress figure averaged over both. Deleting is the only way to make the
-- old content actually disappear without editing the controller.
--
-- Order matters: children before parents, so no row is briefly orphaned.
-- (Note: the schema in africacdc_dhis_schema.sql declares only PRIMARY KEY and UNIQUE KEY
-- constraints -- there are no FOREIGN KEYs to cascade or block. The
-- FOREIGN_KEY_CHECKS toggle below is belt-and-braces for an environment
-- where someone has since added them.)

SET @old_fk_checks := @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

START TRANSACTION;

-- recorded progress (four tables)
DELETE FROM `pm_progress_tasks_tbl`;
DELETE FROM `pm_progress_dates_tbl`;
DELETE FROM `pm_progress_milestones_tbl`;
DELETE FROM `pm_progress_percentages_tbl`;

-- per-project detail
DELETE FROM `pm_projects_tasks_tbl`;
DELETE FROM `pm_projects_dates_tbl`;
DELETE FROM `pm_projects_milestones_tbl`;
DELETE FROM `pm_projects_percentages_tbl`;

-- the hierarchy itself, leaf to root
DELETE FROM `pm_projects_tbl`;
DELETE FROM `pm_programmes_tbl`;
DELETE FROM `pm_objectives_tbl`;
DELETE FROM `pm_pillars_tbl`;


-- =============================================================================
-- STEP 3  Pillars  ->  the two lenses
-- =============================================================================
--
-- Explicit ids so the parentage below is readable: 1 = Internal, 2 = External.
-- pm_pillars_tbl.position already exists in the schema and is already honoured
-- by the overview query, so the lenses are guaranteed to render in this order.
--
-- The description text is a structural summary written for this load -- the
-- source JSON carries no pillar-level prose. It states only counts, WBS ranges
-- and the reporting window, all of which are read directly off the source.

INSERT INTO `pm_pillars_tbl`
    (`id`, `name`, `abbr`, `description`, `position`, `active`)
VALUES
    (1, 'One Organisation, One Platform', 'Internal Lens',
        'How Africa CDC runs itself as one organisation on one digital platform. Five MIS key deliverables, WBS 1.1 to 1.5, covering the reporting window August 2026 to January 2027. Delivered through 52 activities of the Africa CDC FY2026 Annual Workplan.',
        1, 1),
    (2, 'Member States & the Continent', 'External Lens',
        'What Africa CDC offers Member States and the continent. Five MIS key deliverables, WBS 2.1 to 2.5, covering the reporting window August 2026 to January 2027. 4 of the 5 are not yet carried by any FY2026 Annual Workplan activity; only WBS 2.2 has activities (3 of them).',
        2, 1),
    -- Pillars 3 and 4 were added through the live dashboard on 2 Sep 2026
    -- and are carried here so a fresh install matches production. Their
    -- descriptions are the text entered on the live site, verbatim. They
    -- have no AWP activities yet, so they report 0% until some are added.
    (3, 'Digital Emergency Response', 'Digital ER',
        'Technologies (infrastructure and software) to improve response of AfCDC in speed, support community awareness, and help limit the spread of infection in affected regions.',
        3, 1),
    (4, 'AfCDC development', 'AfCDC Development',
        'Includes Special growth projects related to Digital Transformation or to assist in the development of AfricaCDC',
        4, 1);


-- =============================================================================
-- STEP 4  Objectives  ->  the 10 MIS key deliverables
-- =============================================================================
--
-- name  = WBS code + the human deliverable title. The WBS prefix is not
--         decoration: app/views/projects_graphs/overview.php prints
--         $objective['name'] verbatim and never renders the abbr, so without
--         the prefix the deliverable numbering is invisible on the front page.
-- abbr  = the bare WBS code (varchar(20) - a 3-character code fits easily).
-- description = the deliverable's desc field, copied verbatim from the source,
--         including the "- NO AWP ACTIVITY" marker where the source carries it.
-- outcomes = a one-line outcome restatement of that same desc. It adds no new
--         facts; where the source appended the "- NO AWP ACTIVITY" data marker
--         that marker is dropped, since it annotates the record rather than
--         describing an outcome.
-- position = 1..5 within each lens, matching the WBS sequence. See step 1 for
--         why this column exists and what still has to change for it to be read.
--
-- Ids 1-5 are the Internal Lens deliverables, 6-10 the External Lens ones,
-- assigned in WBS order.

INSERT INTO `pm_objectives_tbl`
    (`id`, `pillar_id`, `name`, `abbr`, `description`, `outcomes`, `position`, `active`)
VALUES
    (1, 1, 'Connect the RCCs as one organisation', '1.1',
        'One network, one operating platform.',
        'Outcome: One network, one operating platform.',
        1, 1),
    (2, 1, 'Data Centre operating 24/7/365', '1.2',
        'Always-on continental infrastructure.',
        'Outcome: Always-on continental infrastructure.',
        2, 1),
    (3, 1, 'Cyber security & data sovereignty', '1.3',
        'ISO/IEC 27001 certified.',
        'Outcome: ISO/IEC 27001 certified.',
        3, 1),
    (4, 1, 'ERP live across enterprise operations', '1.4',
        'Finance, Grants, HR, Procurement, Supply Chain (Dynamics 365; re-baselined 1 Jul 2026).',
        'Outcome: Finance, Grants, HR, Procurement, Supply Chain (Dynamics 365; re-baselined 1 Jul 2026).',
        4, 1),
    (5, 1, 'Capacity building & AI', '1.5',
        'KnowBe4 awareness; AI skills across the workforce.',
        'Outcome: KnowBe4 awareness; AI skills across the workforce.',
        5, 1),
    (6, 2, 'Sovereign cloud infrastructure As a Service', '2.1',
        'Continental, country-owned hosting for health data.  — NO AWP ACTIVITY',
        'Outcome: Continental, country-owned hosting for health data.',
        1, 1),
    (7, 2, 'Interoperability layer: Member States ↔ Africa CDC', '2.2',
        'Common standards for data exchange.',
        'Outcome: Common standards for data exchange.',
        2, 1),
    (8, 2, 'Digital healthcare toolkit — As a Service', '2.3',
        'Connectivity and digital tools for primary health care.  — NO AWP ACTIVITY',
        'Outcome: Connectivity and digital tools for primary health care.',
        3, 1),
    (9, 2, 'Continental Data Governance Framework', '2.4',
        'Scope marked TBD on the source slide — pending scoping.  — NO AWP ACTIVITY',
        'Outcome: Scope marked TBD on the source slide — pending scoping.',
        4, 1),
    (10, 2, 'Outbreak Digital Response in 48 hours', '2.5',
        'Alignment with EPR/RRT: toolkit + Member States & Partners coordination.  — NO AWP ACTIVITY',
        'Outcome: Alignment with EPR/RRT: toolkit + Member States & Partners coordination.',
        5, 1),
    -- Objectives of pillars 3 and 4, as entered on the live dashboard on
    -- 2 Sep 2026. No description or outcome text was entered there, so none
    -- is invented here. No AWP activity is attached to any of them yet.
    (11, 3, 'Digital ER Infrastructure', '3.1', '', '', 1, 1),
    (12, 3, 'Software, Intelligence and Innovation', '3.2', '', '', 2, 1),
    (13, 3, 'Capacity Building', '3.3', '', '', 3, 1),
    (14, 4, 'Partnerships and Funding', '4.1', '', '', 1, 1),
    (15, 4, 'Internal projects', '4.2', '', '', 2, 1);


-- =============================================================================
-- STEP 5  Programmes  ->  one per deliverable that has AWP activities
-- =============================================================================
--
-- pm_projects_tbl.programme_id is NOT NULL-friendly in practice: the
-- objective() view walks objective -> programme -> project, so a project with
-- no programme parent is unreachable from that page. Every deliverable that
-- has activities therefore gets exactly one programme, named after the
-- deliverable and parented by objective_id.
--
-- Deliverables 2.1, 2.3, 2.4 and 2.5 have zero activities in the source, so
-- they get NO programme row. They still appear as objectives on the overview
-- and pillar pages -- with 0 projects and a 0.00% gauge, which is the honest
-- reading: scope is defined, no workplan activity is yet carrying it.

INSERT INTO `pm_programmes_tbl`
    (`id`, `objective_id`, `name`, `abbr`, `description`, `active`)
VALUES
    (1, 1, 'Connect the RCCs as one organisation', '1.1-PRG',
        'FY2026 Annual Workplan activities delivering MIS key deliverable 1.1 Connect the RCCs as one organisation. 14 activities.', 1),
    (2, 2, 'Data Centre operating 24/7/365', '1.2-PRG',
        'FY2026 Annual Workplan activities delivering MIS key deliverable 1.2 Data Centre operating 24/7/365. 9 activities.', 1),
    (3, 3, 'Cyber security & data sovereignty', '1.3-PRG',
        'FY2026 Annual Workplan activities delivering MIS key deliverable 1.3 Cyber security & data sovereignty. 17 activities.', 1),
    (4, 4, 'ERP live across enterprise operations', '1.4-PRG',
        'FY2026 Annual Workplan activities delivering MIS key deliverable 1.4 ERP live across enterprise operations. 4 activities.', 1),
    (5, 5, 'Capacity building & AI', '1.5-PRG',
        'FY2026 Annual Workplan activities delivering MIS key deliverable 1.5 Capacity building & AI. 8 activities.', 1),
    (6, 7, 'Interoperability layer: Member States ↔ Africa CDC', '2.2-PRG',
        'FY2026 Annual Workplan activities delivering MIS key deliverable 2.2 Interoperability layer: Member States ↔ Africa CDC. 3 activities.', 1);


-- =============================================================================
-- STEP 6  Projects  ->  the 55 FY2026 Annual Workplan activities
-- =============================================================================
--
-- *** THE CRITICAL BIT: pillar_id, objective_id AND programme_id must all
-- *** agree on every single row.
--
-- The overview page selects projects with BOTH keys at once
-- (projects_graphs.php, overview()):
--
--     SELECT id, name FROM pm_projects_tbl
--      WHERE objective_id = <objective> AND pillar_id = <pillar>
--
-- If a project's pillar_id does not match the pillar that actually owns its
-- objective, that row matches nothing. It does not error, it does not warn --
-- it silently vanishes from the count, and the objective's gauge reads 0.00%
-- as though the work did not exist. objective() and programme() add the same
-- kind of paired filter on programme_id + objective_id, so an inconsistent
-- programme_id hides the row from those two pages too.
--
-- Every row below is emitted with all three keys derived from the same parent
-- deliverable, and step 9 includes a query that re-checks the invariant after
-- the load.
--
-- Column mapping, all copied from the source:
--     name        = the activity's task text
--     abbr        = the AWP code (max length in this data set: 11 chars)
--     description = the activity's note
--     kpi         = the activity's indicator
--     type        = 'pm_projects_tasks', the only value used anywhere in the
--                   existing dump (all 200 legacy projects carry it)
--     applies_to  = NULL. Assigning AWP activities to member states would be
--                   fabrication; the source carries no assignee data.
--
-- No task text in the source exceeds varchar(255) -- the longest is exactly
-- 255 characters (4.2.4.06 / 3.2.2.01.04) -- so nothing needed truncating.
--
-- Ids run 1-55 in source order: 1.1 first, then 1.2, 1.3, 1.4, 1.5, then 2.2.

INSERT INTO `pm_projects_tbl`
    (`id`, `pillar_id`, `objective_id`, `programme_id`, `name`, `abbr`,
     `description`, `kpi`, `type`, `applies_to`, `active`)
VALUES
    -- 1.1 Connect the RCCs as one organisation  (pillar 1, objective 1, programme 1)
    (1, 1, 1, 1,
        'Procurement of Microsoft 365 Licence, 400E5, 100E1, Copilot, Visio',
        '4.2.4.06.01', 'The common operating platform for the whole organisation.',
        'T4 SCM.13.2-16', 'pm_projects_tasks', NULL, 1),
    (2, 1, 1, 1,
        'Fibre Internet at HQ, 2 fibre, 1 air fibre, simcards, phone line',
        '4.2.4.06.03', 'Core connectivity — the ‘one network’ component.',
        'T4 SCM.13.2-16', 'pm_projects_tasks', NULL, 1),
    (3, 1, 1, 1,
        'Purchase of a Network Health Monitoring/Security tool software',
        '4.2.4.06.06', 'Tooling for the Network Operations Centre.',
        'T4 SCM.13.2-16', 'pm_projects_tasks', NULL, 1),
    (4, 1, 1, 1,
        'Purchase of Toolkits for Network expansion',
        '4.2.4.06.09', 'Network expansion to connect sites.',
        'T4 SCM.13.2-16', 'pm_projects_tasks', NULL, 1),
    (5, 1, 1, 1,
        'Provide operational support, security patches, and maintenance for internal communication systems including email servers, dashboards, and enterprise messaging platforms',
        '4.2.4.06.11', 'Keeps the shared operating platform running.',
        'T4 MIS.13.2-6', 'pm_projects_tasks', NULL, 1),
    (6, 1, 1, 1,
        'Establish comprehensive Endpoint Management Operating Model',
        '4.2.4.06.25', 'One managed device estate across HQ and the RCCs.',
        'T4 MIS.13.2-5', 'pm_projects_tasks', NULL, 1),
    (7, 1, 1, 1,
        'Deploy and operationalize Endpoint Management infrastructure',
        '4.2.4.06.26', 'One managed device estate across HQ and the RCCs.',
        'T4 MIS.13.2-5', 'pm_projects_tasks', NULL, 1),
    (8, 1, 1, 1,
        'Establish Endpoint Management performance monitoring and governance',
        '4.2.4.06.27', 'One managed device estate across HQ and the RCCs.',
        'T4 MIS.13.2-5', 'pm_projects_tasks', NULL, 1),
    (9, 1, 1, 1,
        'Develop consolidated IT Service Management Operating Model',
        '4.2.4.06.28', 'A single service desk across the organisation.',
        'T4 MIS.13.2-5', 'pm_projects_tasks', NULL, 1),
    (10, 1, 1, 1,
        'Operationalize IT Service Management & IT Asset Governance processes',
        '4.2.4.06.29', 'A single service desk across the organisation.',
        'T4 MIS.13.2-5', 'pm_projects_tasks', NULL, 1),
    (11, 1, 1, 1,
        'Mature IT Service Management and establish continuous improvement',
        '4.2.4.06.30', 'A single service desk across the organisation.',
        'T4 MIS.13.2-5', 'pm_projects_tasks', NULL, 1),
    (12, 1, 1, 1,
        'Implement network upgrades and proper segmentation to eliminate single points of failure',
        '4.2.4.06.46', 'Network architecture.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (13, 1, 1, 1,
        'Strengthen redundancy across core network components',
        '4.2.4.06.47', 'Network architecture.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (14, 1, 1, 1,
        'Establish a Network Operations Centre (NOC) for proactive monitoring and incident response',
        '4.2.4.06.48', 'Network operations.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    -- 1.2 Data Centre operating 24/7/365  (pillar 1, objective 2, programme 2)
    (15, 1, 2, 2,
        'Procurement of Microsoft Azure',
        '4.2.4.06.02', 'Hosting substrate for always-on continental infrastructure.',
        'T4 SCM.13.2-16', 'pm_projects_tasks', NULL, 1),
    (16, 1, 2, 2,
        'Establish automated cloud-based backup systems with geographic redundancy and implement failover mechanisms to ensure 99.9% uptime for critical Africa CDC systems',
        '4.2.4.06.12', 'Uptime and failover — the 24/7/365 commitment.',
        'T4 MIS.13.2-6', 'pm_projects_tasks', NULL, 1),
    (17, 1, 2, 2,
        'Africa CDC — Cloud Operating Model Operationalization',
        '4.2.4.06.22', 'Operating model underpinning always-on infrastructure.',
        'T4 MIS.13.2-5', 'pm_projects_tasks', NULL, 1),
    (18, 1, 2, 2,
        'Enforcing standardized governance, security, and cost management controls across Azure workloads',
        '4.2.4.06.23', 'Governance of the hosting platform.',
        'T4 MIS.13.2-5', 'pm_projects_tasks', NULL, 1),
    (19, 1, 2, 2,
        'Institutionalize Cloud Operating Model as standard operating framework',
        '4.2.4.06.24', 'Operating model underpinning always-on infrastructure.',
        'T4 MIS.13.2-5', 'pm_projects_tasks', NULL, 1),
    (20, 1, 2, 2,
        'Ensure stable, secure, and resilient IT infrastructure supporting business operations',
        '4.2.4.06.34', 'Indicator tracks continental data centre infrastructure.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (21, 1, 2, 2,
        'Establish service management and preventive maintenance frameworks',
        '4.2.4.06.39', 'Preventive maintenance of the data centre estate.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (22, 1, 2, 2,
        'Enhance disaster recovery and business continuity mechanisms',
        '4.2.4.06.50', 'Continuity of the always-on estate.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (23, 1, 2, 2,
        'Enhance backup, recovery, and redundancy systems',
        '4.2.4.06.55', 'Resilience of the always-on estate.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    -- 1.3 Cyber security & data sovereignty  (pillar 1, objective 3, programme 3)
    (24, 1, 3, 3,
        'Deploy role-based access control (RBAC) systems with multi-factor authentication (MFA) and implement automated account lifecycle management for staff onboarding and offboarding',
        '4.2.4.06.13', 'Access control — an ISO/IEC 27001 control area.',
        'T4 MIS.13.2-6', 'pm_projects_tasks', NULL, 1),
    (25, 1, 3, 3,
        'Execute comprehensive data migration plan to transition all critical institutional data from personal accounts to secure institutional cloud environments with data validation and integrity checks',
        '4.2.4.06.14', 'Moving data under institutional control — data sovereignty.',
        'T4 MIS.13.2-6', 'pm_projects_tasks', NULL, 1),
    (26, 1, 3, 3,
        'Develop and maintain centralized technical documentation repository with version control for all institutional systems, APIs, and infrastructure components',
        '4.2.4.06.15', 'Documented information — an ISO/IEC 27001 requirement.',
        'T4 MIS.13.2-6', 'pm_projects_tasks', NULL, 1),
    (27, 1, 3, 3,
        'Achieve ISO/IEC 27001 certification for information security',
        '4.2.4.06.35', 'The certification named in the deliverable.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (28, 1, 3, 3,
        'Successfully complete independent IT Audit with all high/medium risks remediated',
        '4.2.4.06.36', 'Assurance over the security posture.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (29, 1, 3, 3,
        'Remediate all identified high- and medium-risk audit findings',
        '4.2.4.06.37', 'Assurance over the security posture.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (30, 1, 3, 3,
        'Implement structured operational procedures and documentation',
        '4.2.4.06.38', 'ISMS documentation — due 13 Aug per the ISO plan.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (31, 1, 3, 3,
        'Conduct periodic vulnerability assessments and penetration testing',
        '4.2.4.06.41', 'Security testing.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (32, 1, 3, 3,
        'Operationalize a Security Operations Centre (SOC) for centralized security oversight',
        '4.2.4.06.42', 'Security operations.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (33, 1, 3, 3,
        'Implement and optimize a SIEM (Security Information and Event Management) tool for real-time threat detection, correlation, and incident management',
        '4.2.4.06.43', 'Security operations tooling.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (34, 1, 3, 3,
        'Develop and formalize security policies, procedures, and control frameworks',
        '4.2.4.06.44', 'ISMS policy set.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (35, 1, 3, 3,
        'Strengthen logical and physical access controls',
        '4.2.4.06.45', 'ISO/IEC 27001 control area.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (36, 1, 3, 3,
        'Strengthen endpoint, network, and perimeter security controls',
        '4.2.4.06.49', 'Security controls.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (37, 1, 3, 3,
        'Establish incident response procedures aligned with SOC operations',
        '4.2.4.06.51', 'Security operations.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (38, 1, 3, 3,
        'Conduct risk assessments, gap analysis, and control effectiveness reviews',
        '4.2.4.06.52', 'ISO/IEC 27001 risk process.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (39, 1, 3, 3,
        'Implement corrective and preventive actions to address identified compliance gaps',
        '4.2.4.06.53', 'ISO/IEC 27001 corrective action.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    (40, 1, 3, 3,
        'Establish continuous risk monitoring and reporting mechanisms',
        '4.2.4.06.54', 'ISO/IEC 27001 monitoring.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    -- 1.4 ERP live across enterprise operations  (pillar 1, objective 4, programme 4)
    (41, 1, 4, 4,
        'Procurement of Dynamics 365 for integrated digital solution for all Africa CDC business processes',
        '4.2.4.06.05', 'Dynamics 365 is the ERP platform named in the mid-year review.',
        'T3 MIS.13.2-2', 'pm_projects_tasks', NULL, 1),
    (42, 1, 4, 4,
        'Design, develop, and deploy comprehensive Fleet Management System with GPS tracking, maintenance scheduling, fuel monitoring, and driver assignment capabilities',
        '4.2.4.06.18', 'Asset and supply chain operations within the ERP scope.',
        'T3 MIS.13.2-2', 'pm_projects_tasks', NULL, 1),
    (43, 1, 4, 4,
        'Complete development and deployment of Financial Management Module with real-time SAP integration, automated budget tracking, and cross-platform mobile application for field operations',
        '4.2.4.06.20', 'Finance — named in the ERP deliverable.',
        'T3 MIS.13.2-2', 'pm_projects_tasks', NULL, 1),
    (44, 1, 4, 4,
        'Automate business processes for asset management, and partnership data synchronization with real-time updates and exception handling',
        '4.2.4.06.21', 'Enterprise business process automation within the ERP scope.',
        'T3 MIS.13.2-2', 'pm_projects_tasks', NULL, 1),
    -- 1.5 Capacity building & AI  (pillar 1, objective 5, programme 5)
    (45, 1, 5, 5,
        'Training workshops for 10 Africa CDC/RCC analysts on dashboard design, data storytelling, and policy use.',
        '3.2.1.01.01', 'Workforce skills development.',
        'T3 SDI.2.5-4', 'pm_projects_tasks', NULL, 1),
    (46, 1, 5, 5,
        'DHIS team capacity building: DHIS2 server and advanced systems administration, Azure cloud computing, Website design, system security, project management, etc training',
        '4.2.4.06.04', 'Direct capacity building.',
        'T3 SDI.2.5-4', 'pm_projects_tasks', NULL, 1),
    (47, 1, 5, 5,
        'DHIS team capacity building: Attend international seminars, conferences, etc',
        '4.2.4.06.07', 'Direct capacity building.',
        'T4 PR&A.13.1-35', 'pm_projects_tasks', NULL, 1),
    (48, 1, 5, 5,
        'Design, develop, and deploy Africa CDC Training Accreditation Platform with course catalog, accreditation workflow, certificate generation, and reporting modules',
        '4.2.4.06.16', 'Platform through which workforce training is delivered.',
        'T3 MIS.13.2-2', 'pm_projects_tasks', NULL, 1),
    (49, 1, 5, 5,
        'Establish AI & Advanced Analytics Capability governance and framework',
        '4.2.4.06.31', 'AI capability — named in the deliverable.',
        'T4 MIS.13.2-5', 'pm_projects_tasks', NULL, 1),
    (50, 1, 5, 5,
        'Formalize and institutionalize AI capability within DHIS',
        '4.2.4.06.32', 'AI capability — named in the deliverable.',
        'T4 MIS.13.2-5', 'pm_projects_tasks', NULL, 1),
    (51, 1, 5, 5,
        'Implement and operationalize AI flagship use cases',
        '4.2.4.06.33', 'AI capability — named in the deliverable.',
        'T4 MIS.13.2-5', 'pm_projects_tasks', NULL, 1),
    (52, 1, 5, 5,
        'Conduct regular cybersecurity awareness and training programs for staff',
        '4.2.4.06.40', 'KnowBe4 awareness — named in the deliverable; ISO plan dates it 25 Sep.',
        'T3 MIS.13.2-3', 'pm_projects_tasks', NULL, 1),
    -- 2.2 Interoperability layer: Member States ↔ Africa CDC  (pillar 2, objective 7, programme 6)
    (53, 2, 7, 6,
        'Organize a high-level launch/dissemination event (side of ICPHC/AHTS) for the flagship report.',
        '3.2.1.01.03', 'Member State convening — matches the AHTS/CPHIA consultation track.',
        'T3 PIC.11.1-4', 'pm_projects_tasks', NULL, 1),
    (54, 2, 7, 6,
        'Organize Partner Investment Dialogue using ADHOPT data (co-convened at AHTS 2026/CPHIA) to mobilize resources and dissemination event for annual report (webinar or conference side-event, AHTS 2026/CPHIA 2026).',
        '3.2.2.01.01', 'Partner and Member State convening built on ADHOPT data exchange.',
        'T3 PIC.11.1-4', 'pm_projects_tasks', NULL, 1),
    (55, 2, 7, 6,
        'Conduct annual country updates cycles for Africa Digital Health Observatory (ADHOPT): training sessions with RCC focal points, MS data validation workshops and produce knowledge outputs: annual State of Digital Health in Africa report + 2 thematic briefs.',
        '3.2.2.01.04', 'Member State ↔ Africa CDC data collection and validation.',
        'T3 DHIS.7.1-2', 'pm_projects_tasks', NULL, 1);


-- =============================================================================
-- STEP 7  Progress: deliberately none
-- =============================================================================
--
-- All 10 deliverables in the source read pct = 0 and status = "Not started".
-- The correct representation of that is an absence of progress rows, not a
-- row saying zero -- which is why steps 2 emptied all four pm_progress_*
-- tables and nothing re-populates them here.
--
-- No pm_projects_tasks_tbl rows are seeded either, for the same reason: the
-- source has no task breakdown below activity level and no assignee list, and
-- inventing either would put fabricated denominators into every gauge.
--
-- Consequence to expect on first load: every gauge on every page reads 0.00%
-- over 0 assignments. That is accurate for a portfolio where nothing has
-- started. It is not a load failure.


-- =============================================================================
-- STEP 8  Commit, then reset AUTO_INCREMENT
-- =============================================================================

COMMIT;

SET FOREIGN_KEY_CHECKS = @old_fk_checks;

-- The AUTO_INCREMENT resets mirror what the africacdc_dhis_schema.sql dump does at its end,
-- so the next row inserted through the admin UI continues the explicit ids
-- above instead of resuming from an old high-water mark. These are DDL and
-- would implicitly commit, so they run after COMMIT rather than inside the
-- transaction. Unlike the dump, they do not restate the column definition --
-- only the counter is changed, leaving the live column types untouched.

ALTER TABLE `pm_pillars_tbl`               AUTO_INCREMENT = 3;
ALTER TABLE `pm_objectives_tbl`            AUTO_INCREMENT = 11;
ALTER TABLE `pm_programmes_tbl`            AUTO_INCREMENT = 7;
ALTER TABLE `pm_projects_tbl`              AUTO_INCREMENT = 56;
ALTER TABLE `pm_projects_tasks_tbl`        AUTO_INCREMENT = 1;
ALTER TABLE `pm_projects_dates_tbl`        AUTO_INCREMENT = 1;
ALTER TABLE `pm_projects_milestones_tbl`   AUTO_INCREMENT = 1;
ALTER TABLE `pm_projects_percentages_tbl`  AUTO_INCREMENT = 1;
ALTER TABLE `pm_progress_tasks_tbl`        AUTO_INCREMENT = 1;
ALTER TABLE `pm_progress_dates_tbl`        AUTO_INCREMENT = 1;
ALTER TABLE `pm_progress_milestones_tbl`   AUTO_INCREMENT = 1;
ALTER TABLE `pm_progress_percentages_tbl`  AUTO_INCREMENT = 1;


-- =============================================================================
-- STEP 9  Verification
-- =============================================================================
-- Read all four result sets before declaring the load good.

-- 9a. Row counts. Expect exactly: 4 / 15 / 6 / 55, and 0 for the rest.
SELECT 'pm_pillars_tbl'              AS table_name, COUNT(*) AS rows_loaded, 4 AS expected FROM `pm_pillars_tbl`
UNION ALL SELECT 'pm_objectives_tbl',            COUNT(*), 15  FROM `pm_objectives_tbl`
UNION ALL SELECT 'pm_programmes_tbl',            COUNT(*), 6  FROM `pm_programmes_tbl`
UNION ALL SELECT 'pm_projects_tbl',              COUNT(*), 55 FROM `pm_projects_tbl`
UNION ALL SELECT 'pm_projects_tasks_tbl',        COUNT(*), 0  FROM `pm_projects_tasks_tbl`
UNION ALL SELECT 'pm_projects_dates_tbl',        COUNT(*), 0  FROM `pm_projects_dates_tbl`
UNION ALL SELECT 'pm_projects_milestones_tbl',   COUNT(*), 0  FROM `pm_projects_milestones_tbl`
UNION ALL SELECT 'pm_projects_percentages_tbl',  COUNT(*), 0  FROM `pm_projects_percentages_tbl`
UNION ALL SELECT 'pm_progress_tasks_tbl',        COUNT(*), 0  FROM `pm_progress_tasks_tbl`
UNION ALL SELECT 'pm_progress_dates_tbl',        COUNT(*), 0  FROM `pm_progress_dates_tbl`
UNION ALL SELECT 'pm_progress_milestones_tbl',   COUNT(*), 0  FROM `pm_progress_milestones_tbl`
UNION ALL SELECT 'pm_progress_percentages_tbl',  COUNT(*), 0  FROM `pm_progress_percentages_tbl`;


-- 9b. Per-lens deliverable listing, in the intended WBS order.
--     Expect 10 rows: 5 under each lens, positions 1-5, and the activity
--     counts 14 / 9 / 17 / 4 / 8 / 0 / 3 / 0 / 0 / 0 reading down.
SELECT  pil.`position`                        AS lens_pos,
        pil.`abbr`                            AS lens,
        obj.`position`                        AS wbs_pos,
        obj.`abbr`                            AS wbs,
        obj.`name`                            AS deliverable,
        COUNT(DISTINCT prg.`id`)              AS programmes,
        COUNT(DISTINCT prj.`id`)              AS awp_activities
  FROM `pm_pillars_tbl`     pil
  JOIN `pm_objectives_tbl`  obj ON obj.`pillar_id`    = pil.`id`
  LEFT JOIN `pm_programmes_tbl` prg ON prg.`objective_id` = obj.`id`
  LEFT JOIN `pm_projects_tbl`   prj ON prj.`objective_id` = obj.`id`
 GROUP BY pil.`position`, pil.`abbr`, obj.`position`, obj.`abbr`, obj.`name`
 ORDER BY pil.`position`, obj.`position`;


-- 9c. Parentage invariant. This is the check that catches the silent failure
--     described in step 6. It MUST return zero rows. Any row it returns is a
--     project the overview query will drop on the floor without an error.
SELECT  prj.`id`          AS project_id,
        prj.`abbr`        AS awp_code,
        prj.`pillar_id`   AS project_pillar,
        obj.`pillar_id`   AS objective_pillar,
        prj.`objective_id` AS project_objective,
        prg.`objective_id` AS programme_objective
  FROM `pm_projects_tbl` prj
  LEFT JOIN `pm_objectives_tbl` obj ON obj.`id` = prj.`objective_id`
  LEFT JOIN `pm_programmes_tbl` prg ON prg.`id` = prj.`programme_id`
 WHERE obj.`id` IS NULL
    OR prg.`id` IS NULL
    OR prj.`pillar_id`    <> obj.`pillar_id`
    OR prj.`objective_id` <> prg.`objective_id`;


-- 9d. The position column landed and is populated 1-5 per lens.
--     Expect 2 rows, both showing min 1 / max 5 / distinct 5.
SELECT  pil.`abbr`                        AS lens,
        MIN(obj.`position`)               AS min_pos,
        MAX(obj.`position`)               AS max_pos,
        COUNT(DISTINCT obj.`position`)    AS distinct_pos,
        COUNT(*)                          AS deliverables
  FROM `pm_objectives_tbl` obj
  JOIN `pm_pillars_tbl`    pil ON pil.`id` = obj.`pillar_id`
 GROUP BY pil.`abbr`
 ORDER BY MIN(pil.`position`);

-- =============================================================================
-- End of africacdc_dhis_seed.sql
-- =============================================================================
