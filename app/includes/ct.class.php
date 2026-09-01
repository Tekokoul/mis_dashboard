<?php
require_once _ROOT_PATH.'vendor/autoload.php';
class ct{

    function __construct(){
        global $registry;
        $this->R = $registry;
        $this->DB = $this->R->{$this->R->defaultDB};
    }

    function update_calendar($data){
        if($data['calendar']!=""){
            $query = "select * from ct_calendars_tbl where id = ".$data['calendar'];
            $calendar = $this->DB->MQ($query, "one");
            $client = new Google_Client();
            $client->setApplicationName('WBT Calendar sync');
            $client->setAuthConfig(_MODELS_SETTINGS_PATH.'wbt-calendar-sync.json');
            $client->setAccessType('offline');
            $client->setScopes(['https://www.googleapis.com/auth/calendar']);
            $client->setAccessToken(json_from_db($calendar['auth']));

            $service = new Google_Service_Calendar($client);

            $times = $this->calculate_times($data['start_time']);
            $title_start_time = display_time($data['start_time'], "H:i");
            $title_from_to = $data['from_to'];
            if($data['payment_type']==0){
                $title_price = "€".$data['price'];
            } else {
                $title_price = "€0";
            }
            if($data['driver']==0){
                $title_driver = "";
            } else {
                $query = "select * from ct_drivers_tbl where id=".$data['driver'];
                $driver = $this->DB->MQ($query, "one");
                $title_driver = $driver['name'].", ";
            }
            switch ($data['car_type']){
                case 2: $title_car = " 🚐 , ";
                    break;
                case 3: $title_car = " 🚌 , ";
                    break;
                default: $title_car = "";
            }

            if(isset($data['customer_name'])){
                $desc_customer = "Client: <b>".$data['customer_name']."</b>\n";
            } else {
                $desc_customer = "";
            }

            if(isset($data['customer_phone'])){
                $desc_customer_phone = "Client phone: <b>".$data['customer_phone']."</b>\n\n";
            } else {
                $desc_customer_phone = "";
            }

            $desc_price = "Agreed Price: <b>€".$data['price']."</b>";
            if(($data['external_company_price']!="")&&((double)$data['external_company_price']>0)){
                $desc_price .= "\nDriving Partner Price: €".$data['external_company_price'];
            }
            $desc_price .= "\n\n";

            $event_data = [
                'summary' => $title_start_time." - ".$title_driver.$title_car.$title_from_to.", ".$title_price,
                'description' => $desc_customer.$desc_customer_phone.$desc_price.$data['notes'],
                'start' => [
                    'dateTime' => $times['start']
                ],
                'end' => [
                    'dateTime' => $times['end']
                ]
            ];

            if(is_null($data['event_id'])||($data['event_id']=="")){
                $event = new Google_Service_Calendar_Event($event_data);
                $event = $service->events->insert($calendar['calendar_id'], $event);
                $query = "update ct_transfers_tbl set event_id='".$event['id']."' where id=".$data['id'];
                $this->DB->MQ($query);
            } else {
                $event = $service->events->get($calendar['calendar_id'], $data['event_id']);

                // Modify the event properties
                $event->setSummary($event_data['summary']);
                $event->setDescription($event_data['description']);
                $event->start->dateTime = $event_data['start']['dateTime'];
                $event->end->dateTime = $event_data['end']['dateTime'];

                // Update the event
                $updatedEvent = $service->events->update($calendar['calendar_id'], $data['event_id'], $event);
            }
            return true;
        } else {
            return false;
        }
    }

    function delete_calendar($data){
        if($data['calendar']!="") {
            $query = "select * from ct_calendars_tbl where id = " . $data['calendar'];
            $calendar = $this->DB->MQ($query, "one");
            $client = new Google_Client();
            $client->setApplicationName('WBT Calendar sync');
            $client->setAuthConfig(_MODELS_SETTINGS_PATH . 'wbt-calendar-sync.json');
            $client->setAccessType('offline');
            $client->setScopes(['https://www.googleapis.com/auth/calendar']);
            $client->setAccessToken(json_from_db($calendar['auth']));

            $service = new Google_Service_Calendar($client);

            $service->events->delete($calendar['calendar_id'], $data['event_id']);
            return true;
        } else {
            return false;
        }
    }

    private function calculate_times($start_time){
        $reply['start'] = display_universal_time($start_time);
        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $start_time); // Create a DateTime object from the start time string
        $dateTime->add(new DateInterval('PT1H')); // Add 1 hour to the DateTime object
        $reply['end'] = display_universal_time($dateTime->format('Y-m-d H:i:s'));
        return $reply;
    }
}