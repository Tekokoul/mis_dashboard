<?php
define('_DB_DEBUG_MODE', false);
include "/var/www/html/app/configuration/settings.local.php";   // _MATCHER_* constants + db
require "/var/www/html/app/includes/library.php";
require "/var/www/html/app/db.class.php";
$s=$settings['db_master']; $s['db_provider']='mysql'; $db=new DB($s);
$out=['goals'=>[], 'objectives'=>[], 'programmes'=>[], 'rows'=>[]];
foreach ((array)$db->MQ("SELECT id,abbr,name FROM pm_pillars_tbl WHERE active=1 ORDER BY position","all") as $r) $out['goals'][]=$r;
foreach ((array)$db->MQ("SELECT id,pillar_id,abbr,name,LEFT(description,160) description FROM pm_objectives_tbl WHERE active=1","all") as $r) $out['objectives'][]=$r;
foreach ((array)$db->MQ("SELECT id,objective_id,abbr,name,LEFT(description,200) description FROM pm_programmes_tbl WHERE active=1","all") as $r) $out['programmes'][]=$r;
$rows=(array)$db->MQ("SELECT p.id,p.abbr,p.name,p.description,p.kpi,p.pillar_id,p.objective_id,p.programme_id,g.objective_id prg_objective, g.abbr prg_abbr
   FROM pm_projects_tbl p LEFT JOIN pm_programmes_tbl g ON g.id=p.programme_id
   WHERE p.abbr REGEXP '^[0-9]+(\\\\.[0-9]+){4,}' ORDER BY p.id","all");
foreach ($rows as $r) {
    $text=trim($r['name'].'. '.$r['description'].' '.$r['kpi']);
    $sug=suggest_parent($db,'pm_projects',$text,3,(int)$r['id']);
    $r['proposals']=$sug['candidates']; $r['confident']=$sug['confident'];
    $r['programme_exists']=$r['prg_objective']!==null;
    $out['rows'][]=$r;
}
file_put_contents('/tmp/awp-dump.json', json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
printf("goals %d, objectives %d, programmes %d, rows %d; matcher on: %s\n", count($out['goals']),count($out['objectives']),count($out['programmes']),count($out['rows']), matcher_url()?:'no');
$agree=0; foreach($out['rows'] as $r){ if(isset($r['proposals'][0]) && (int)$r['proposals'][0]['objective_id']===(int)$r['objective_id']) $agree++; }
printf("matcher's first pick keeps the filed objective for %d of %d rows\n",$agree,count($out['rows']));
