<?php

class Youspeak
{
    protected $R;
    protected $S;
    protected $DB;
    public function __construct(Registry $registry)
    {
        $this->R = $registry;
        $this->S = $this->R->settings;
        $this->DB = $this->R->{$this->R->defaultDB};
    }

    function notify($data){
            $query = "select title from youspeak_cases_tbl where id=".$data['case_id'];
            $case = $this->DB->MQ($query, "one");
            $query = "select title, user_id from youspeak_options_referrals_tbl where id=".$data['redirect_to_user'];
            $referral = $this->DB->MQ($query, "one");
            if(!is_null($referral['user_id'])){
                $query = "select * from core_users_tbl where id=".$referral['user_id'];
                $user = $this->DB->MQ($query, "one");

                $query = "select * from youspeak_cases_to_users_tbl where case_id = '".$data['case_id']."' AND user_id = '".$user['id']."'";
                $exists = $this->DB->MQ($query, "one");
                if(!is_set($exists)){
                    $query = "INSERT INTO youspeak_cases_to_users_tbl (case_id, user_id) VALUES( '".$data['case_id']."', '".$user['id']."')";
                    $this->DB->MQ($query);
                    if($data['method']==11) {

                        $recipients = $user['username'];
                        $fields = [
                            "title" => $case['title'],
                            "referral" => $referral['title'],
                            "message" => $data['message'] . "<br>" . $data['reason']
                        ];
                        $email_result = $this->_sendEmail("case_redirect", $recipients, $fields);
                    }
                }
            } else {
                $query = "INSERT IGNORE INTO youspeak_cases_to_users_tbl (case_id, unregistered_user_id)
SELECT '".$data['case_id']."', '".$data['redirect_to_user']."' FROM dual WHERE NOT EXISTS ( SELECT 1 FROM youspeak_cases_to_users_tbl WHERE case_id = '".$data['case_id']."' AND unregistered_user_id = '".$data['redirect_to_user']."');";
                $this->DB->MQ($query);
            }
        return true;
    }

    function case_update($data){
        $query = "select * from youspeak_cases_tbl where id=".$data['case_id'];
        $case = $this->DB->MQ($query, "one");
        $query = "select * from youspeak_users_tbl where id = ".$case['idUser'];
        $user = $this->DB->MQ($query, "one");
        $query = "select * from youspeak_cases_status_tbl where id=".$data['status'];
        $status = $this->DB->MQ($query, "one");
        $notifications = readJSONFile(_JSON_MODELS_PATH."data.cases_notifications.json", "en");
        if($user['has_app']&&$user['accept_notifications']){
            $notification['tokens'] = json_from_db($user['app_tokens']);
            $notification['title'] = $notifications[$status['class'].'_notification_case_title'];
            $notification['body'] = str_replace("%%title%%",$case['title'], $notifications[$status['class'].'_notification_case_text']);
            $notification['id'] = $case['id'];
            $expo = new expo_notifications();
            $expo->send_notification($notification);
        }
    }

    protected function _sendEmail($email_template, $recipients, $fields, $attachment = ""){
        require_once _INCLUDES_PATH."mail.class.php";
        $email_texts = readJSONFile(_JSON_MODELS_PATH."data.email.json", "en");
        $mailService = new Mail($this->S['mail']);
        $email['subject'] = $email_texts[$email_template.'_title'];
        $email['from'] = $email_texts['from_email'];
        $email['from_name'] = $email_texts['from_name'];
        $email['recipients'] = $recipients;
        $email['parameters']['preview_text'] = $email_texts[$email_template.'_preview_text'];
        $email['parameters']['text'] = $email_texts[$email_template.'_text'];
        $email['parameters']['footer'] = "<img width='150' src='"._PROJECT_URL."/".$email_texts['logo']."'>";
        //parameters from now on are per case------------------
        foreach ($fields as $field => $value){
            $email['parameters'][$field] =  $value;
        }
        if($attachment!=""){
            $email['attachment'] = $attachment;
        }
        return $mailService->sendMail($email);
    }
}