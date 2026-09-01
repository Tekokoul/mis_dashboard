<?php
require_once _CONTROLLERS_PATH."core.php";

class cases_statisticsController extends coreController{
    public function frequency(){
        $this->AddJS("/assets/youspeak/js/chart.js");
        $this->AddJS("/assets/youspeak/js/chart-utils.js");
        $this->AddJS("/assets/youspeak/js/custom-chart-statistics.js");
        $this->render();
    }

    public function opinion_categories(){
        $this->AddJS("/assets/youspeak/js/chart.js");
        $this->AddJS("/assets/youspeak/js/chart-utils.js");
        $this->AddJS("/assets/youspeak/js/custom-chart-statistics.js");
        $this->render();    }

    public function status(){
        $query = "select id, name from youspeak_constituencies_tbl order by name;";
        $data['constituencies'] = $this->DB->MQ($query, "all");
        $this->AddJS("/assets/youspeak/js/chart.js");
        $this->AddJS("/assets/youspeak/js/chart-utils.js");
        $this->AddJS("/assets/youspeak/js/custom-chart-wards.js");
        $this->render($data);
    }


    public function wards(){
        $query = "select id, name from youspeak_constituencies_tbl order by name;";
        $data['constituencies'] = $this->DB->MQ($query, "all");
        $this->AddJS("/assets/youspeak/js/chart.js");
        $this->AddJS("/assets/youspeak/js/chart-utils.js");
        $this->AddJS("/assets/youspeak/js/custom-chart-wards.js");
        $this->render($data);
    }

    public function top(){
        $query = "select id, name from youspeak_constituencies_tbl order by name;";
        $data['constituencies'] = $this->DB->MQ($query, "all");
        foreach ($data['constituencies'] as &$constituency){
            $query = "select youspeak_issues_tbl.title, count(idIssues_1) as issues from youspeak_cases_tbl 
left JOIN youspeak_issues_tbl on youspeak_issues_tbl.id=youspeak_cases_tbl.idIssues_1
where idConstituency=".$constituency['id']."
group by youspeak_issues_tbl.title
order by issues desc
limit 5";
            $constituency['issues'] = $this->DB->MQ($query, "all");
        }
        $query = "select youspeak_issues_tbl.title, count(idIssues_1) as issues from youspeak_cases_tbl 
left JOIN youspeak_issues_tbl on youspeak_issues_tbl.id=youspeak_cases_tbl.idIssues_1
group by youspeak_issues_tbl.title
order by issues desc
limit 5";
        $data['totals'] = $this->DB->MQ($query, "all");
        $this->AddCSS("/assets/youspeak/css/cases_statistics_top.css");
        $this->render($data);
    }
}