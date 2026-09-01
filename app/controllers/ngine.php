<?php
/**
 * Created by PhpStorm.
 * User: zen
 * Date: 8/4/2017
 * Time: 8:35 μμ
 */

class ngineController extends protectedController {

    protected $unprotected = ["info", "deploy", "p"];

    public function deploy() {
        exec('git --git-dir="'._ROOT_PATH.'".git --work-tree="'._ROOT_PATH.'" pull', $output, $code);
        if($code!=0){
            $this->setAnswer(503, "Unable to run");    
        }
        $this->setAnswer(200, array2str($output));
    }

    public function info() {
        $info['installation'] = _PROJECT_NAME;
        $info['HTTP server'] = $_SERVER['SERVER_SOFTWARE'];
        $info['PHP version'] =  phpversion();
        $info["git status"] = exec('git --git-dir="'._ROOT_PATH.'".git --work-tree="'._ROOT_PATH.'" status');
        $info["R"] = $this->R;
        $this->setAnswer(200, "nGine info", $info);
    }

//    public function emailtest(){
//        $email_texts = readJSONFile(_JSON_MODELS_PATH."data.email_texts.json");
////	    debug($email_texts);
////	    exit();
//        $mailService = new Mail($this->S['mail']);
//        $email['subject'] = "Test e-mail";

//        $email['from_name'] = "Monitor";
//        $email['recipients'] = "someone@africacdc.org";
//        $email['parameters']['title'] = "This is an email";
//        $email['parameters']['preview_text'] = "This is the preview";
//        $email['parameters']['text'] = "This is the actual test. %%email_parameter%%.";
//        $email['parameters']['footer'] = "<img width='120' src='"._PROJECT_URL."/media/logo/africacdc_logo.png' alt='"._PROJECT_NAME."'>";
////        $email['attachment'] = _MEDIA_PATH."cvs/rwservlet.pdf";
//        //parameters from now on are per case------------------
//        $email['parameters']['email_parameter'] = "Here... get a unique ID: ".uniqid();
//
//        print $mailService->sendMail($email);
//    }
    public function r(){
        debug($this->R);
    }

    public function email(){
        $this->render();
    }

    public function test_email(){
        $this->checkMethod("POST");
        $this->checkRequired(["email"], $this->query);
        $rules = [
            "email" => FILTER_SANITIZE_EMAIL
        ];
        $validated = $this->sanitize($this->query, $rules);
        $email_info = readJSONFile(_JSON_MODELS_PATH."data.email.json");
        $mailService = new Mail($this->S['mail']);
        $email['subject'] = "Test e-mail";
        $email['from'] = $email_info['from_email'];
        $email['from_name'] = $email_info['from_name'];
        $email['recipients'] = $validated['email'];
        $email['parameters']['title'] = "This is an email";
        $email['parameters']['preview_text'] = "This is the preview";
        $email['parameters']['text'] = "This is the actual test. %%email_parameter%%.";
        $email['parameters']['footer'] = "<img width='120' src='"._PROJECT_URL."/media/logo/africacdc_logo.png' alt='"._PROJECT_NAME."'>";
//        $email['attachment'] = _MEDIA_PATH."cvs/rwservlet.pdf";
        //parameters from now on are per case------------------
        $email['parameters']['email_parameter'] = "Here... get a unique ID: ".uniqid();

        debug($mailService->sendMail($email));
    }

    public function notification(){
        require _INCLUDES_PATH."expo.notification.class.php";

    }

    public function p(){
        $this->checkRequired("p", $this->query);
        debug(md5(md5($this->query['p'])));
    }
//
//    public function testTX(){
//        $response = $this->api_call(
//            "http://localhost:8116/en/remote/get_json/email",
//            "GET",
//            "Bearer " . $this->CE_encrypt($this->S["api_key"]),
//            json_encode([
//                "field1" => "some nice data",
//                "field2" => "other nice data"
//            ]));
//
//        $this->setAnswer(200, "found", json_decode($response['data'], true)['data'], "json");
////        $this->setAnswer(200, "found", json_decode($response['data'], true)['data'], "json");
//    }
//
//    public function testRX(){
//        $this->checkMethod("POST");
//        $this->checkAuthorization();
//        $data = json_decode(file_get_contents('php://input'), true);
//        setAnswer(200, "found", $data);
//
//    }

}