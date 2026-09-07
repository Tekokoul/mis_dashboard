-- Programme codes brought to the house form "N.n PRG" (8 rows; exact ids; 7 September 2026).
-- Run on the live database as root, inside a transaction; verify with the SELECT below.
START TRANSACTION;
UPDATE pm_programmes_tbl SET abbr = '7.6 PRG'  WHERE id = 79 AND abbr = '7.6 PGR';
UPDATE pm_programmes_tbl SET abbr = '9.4 PRG'  WHERE id = 60 AND abbr = '9.4 PGR';
UPDATE pm_programmes_tbl SET abbr = '9.5 PRG'  WHERE id = 61 AND abbr = '9.5';
UPDATE pm_programmes_tbl SET abbr = '11.2 PRG' WHERE id = 65 AND abbr = '11.2 RPG';
UPDATE pm_programmes_tbl SET abbr = '14.1 PRG' WHERE id = 62 AND abbr = '14.1 Fin';
UPDATE pm_programmes_tbl SET abbr = '12.8 PRG' WHERE id = 80 AND abbr = '12.8';
UPDATE pm_programmes_tbl SET abbr = '13.2 PRG' WHERE id = 75 AND abbr = '13.2';
UPDATE pm_programmes_tbl SET abbr = '16.1 PRG' WHERE id = 51 AND abbr = '16.1';
SELECT id, abbr, name FROM pm_programmes_tbl WHERE id IN (79,60,61,65,62,80,75,51) ORDER BY id;
COMMIT;
-- Rollback (exact ids):
-- UPDATE pm_programmes_tbl SET abbr='7.6 PGR' WHERE id=79; UPDATE pm_programmes_tbl SET abbr='9.4 PGR' WHERE id=60; UPDATE pm_programmes_tbl SET abbr='9.5' WHERE id=61; UPDATE pm_programmes_tbl SET abbr='11.2 RPG' WHERE id=65; UPDATE pm_programmes_tbl SET abbr='14.1 Fin' WHERE id=62; UPDATE pm_programmes_tbl SET abbr='12.8' WHERE id=80; UPDATE pm_programmes_tbl SET abbr='13.2' WHERE id=75; UPDATE pm_programmes_tbl SET abbr='16.1' WHERE id=51;
