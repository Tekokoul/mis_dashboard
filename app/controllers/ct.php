<?php
require_once _CONTROLLERS_PATH."core.php";
require_once _ROOT_PATH.'vendor/autoload.php';

class ctController extends coreController{

    public function get_calendar(){
        $this->checkMethod("GET");
//        $this->checkRequired(["class", "function", "table", "id"], $this->query);
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        if(empty($_SESSION['temp_cal'])){
            $validated = $this->sanitize($this->query, $rules);
            $_SESSION['temp_cal'] = $validated;
        } else {
            $validated = $_SESSION['temp_cal'];
        }
        $this->select_calendars($validated);
    }

    public function save_calendar(){
        $query = "update ct_calendars_tbl set auth='".json_to_db($_SESSION['google_oauth'])."', calendar_id='".$this->query['calendar_id']."' where id='".$_SESSION['temp_cal']['id']."'";
        $this->DB->MQ($query);
        $validated = $_SESSION['temp_cal'];
        unset($_SESSION['temp_cal']);
        redirect($this->L("core/db_edit/ct_calendars/".$validated['id']));
    }

    function select_calendars($data){
        $client = new Google_Client();
        $client->setApplicationName('WBT Calendar sync');
        $client->setAuthConfig(_MODELS_SETTINGS_PATH.'wbt-calendar-sync.json');
        $client->setAccessType('offline');
        $client->setScopes(['https://www.googleapis.com/auth/calendar']);

        $redirectUri = _PROJECT_URL."/ct/get_calendar";
        $client->setRedirectUri($redirectUri);

        $authUrl = $client->createAuthUrl();
        if (isset($_GET['code'])) {
            $authCode = $_GET['code'];
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $_SESSION['google_oauth'] = $accessToken;
            $client->setAccessToken($accessToken);
        } elseif (!$client->getAccessToken()) {
            print '<a href="' . htmlspecialchars($authUrl) . '">Authorize application</a>';
            exit;
        }

        $service = new Google_Service_Calendar($client);

        $calendarList = $service->calendarList->listCalendarList();
        print "<form action='/ct/save_calendar'>";
        foreach ($_SESSION['cal_temp'] as $key=>$value){
            print "<input type='hidden' name='".$key."' value='".$value."'>";
        }
        print "<select name='calendar_id'>";
        foreach ($calendarList->getItems() as $calendar) {
            print "<option value='".$calendar->getId()."'>".$calendar->getSummary() . "</option>" ;
        }
        print "</select><button type='submit'>Submit</button></form>";
    }
}