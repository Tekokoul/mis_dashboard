<?php
/**
 * Created by PhpStorm.
 * User: zen
 * Date: 8/4/2017
 * Time: 8:35 μμ
 */

class ngineController extends protectedController {

    // NOTHING here may be unprotected. Previously "info", "deploy" and "p" were
    // all reachable without logging in:
    //   deploy  ran `git pull` on the server for any anonymous visitor
    //   info    dumped the registry, including the settings array
    //   p       returned md5(md5($_GET['p'])) - the app's own password hash,
    //           i.e. a public oracle for forging credential hashes
    protected $unprotected = [];

    // Deploying is `git pull && ./setup-production.sh deploy` on the server;
    // there is no git inside the container, so a pull from the browser could
    // never work - and a working one would have been a second, uncontrolled
    // deploy path. The endpoint stays only so old bookmarks get a clear answer.
    public function deploy() {
        $this->setAnswer(410, "Deploying from the browser has been removed. Deploy with ./setup-production.sh deploy on the server.");
    }

    // The old info() dumped the whole registry - settings included - to any
    // logged-in user.
    public function info() {
        $this->setAnswer(410, "System information is no longer exposed here.");
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
        $this->setAnswer(200, "Registry inspection is disabled.");
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

        $this->setAnswer(200, $mailService->sendMail($email) ? "sent" : "failed");
    }

    public function notification(){
        require _INCLUDES_PATH."expo.notification.class.php";

    }

    // Removed: p() printed md5(md5($_GET['p'])), the exact hash the login query
    // compares against. Use tools/create-admin.php to set a password instead.
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