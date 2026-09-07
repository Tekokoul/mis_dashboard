<?php
// The eleven rows put back during vetting, settled per the evaluation:
// accept the proposal for 1, 48, 17, 52; keep the programme and move the
// objective to the one it belongs to for 22, 23, 31, 35, 28; 46 and 47 stay
// as re-filed by hand. Codes are the next free under the programme.
define('_DB_DEBUG_MODE', false);
include "/var/www/html/app/configuration/settings.local.php";
require "/var/www/html/app/includes/library.php";
require "/var/www/html/app/db.class.php";
$s=$settings['db_master']; $s['db_provider']='mysql'; $db=new DB($s);
$plan = [
  1  => ['mode' => 'proposal'], 48 => ['mode' => 'proposal'], 17 => ['mode' => 'proposal'], 52 => ['mode' => 'proposal'],
  22 => ['mode' => 'keep_programme'], 23 => ['mode' => 'keep_programme'], 31 => ['mode' => 'keep_programme'], 35 => ['mode' => 'keep_programme'], 28 => ['mode' => 'keep_programme'],
];
printf("%-4s %-13s %-8s %s\n", 'id', 'old code', 'new', 'placement');
foreach ($plan as $id => $p) {
    $r = $db->MQ("SELECT * FROM pm_allocation_review_tbl WHERE project_id = ? AND status = 'reverted'", "one", [$id]);
    $a = $db->MQ("SELECT id, abbr, pillar_id, objective_id, programme_id FROM pm_projects_tbl WHERE id = ?", "one", [$id]);
    if (!is_set($r) || !is_set($a)) { printf("%-4d SKIP: not a put-back row\n", $id); continue; }
    if ($p['mode'] === 'proposal') { $prg = (int)$r['new_programme_id']; }
    else { $prg = (int)$a['programme_id']; }
    $g = $db->MQ("SELECT id, objective_id, abbr FROM pm_programmes_tbl WHERE id = ?", "one", [$prg]);
    if (!is_set($g)) { printf("%-4d SKIP: programme %d missing\n", $id, $prg); continue; }
    $obj = (int)$g['objective_id'];
    $o = $db->MQ("SELECT pillar_id, abbr FROM pm_objectives_tbl WHERE id = ?", "one", [$obj]);
    $pillar = (int)$o['pillar_id'];
    $code = auto_wbs_code($db, 'pm_projects', ['programme_id' => $prg]);
    printf("%-4d %-13s %-8s %s / %s  [%s]\n", $id, $a['abbr'], $code, $o['abbr'], $g['abbr'], $p['mode']);
    $db->MQ("UPDATE pm_projects_tbl SET pillar_id = ?, objective_id = ?, programme_id = ?, abbr = ? WHERE id = ?", false, [$pillar, $obj, $prg, $code, $id]);
    $reason = $p['mode'] === 'proposal' ? $r['reason'] : 'Kept the programme chosen by staff; the objective now follows the programme so the activity counts on the overview.';
    $db->MQ("UPDATE pm_allocation_review_tbl SET new_pillar_id = ?, new_objective_id = ?, new_programme_id = ?, new_abbr = ?, confidence = 'agreed', reason = ?, status = 'accepted', decided_by = 0, decided_at = NOW() WHERE id = ?", false,
        [$pillar, $obj, $prg, $code, mb_substr((string)$reason, 0, 1000), (int)$r['id']]);
}
