<?php
/**
 * Created by PhpStorm.
 * User: zen
 * Date: 9/5/2017
 * Time: 4:46 μμ
 */


$mailFolder = _ROOT_PATH."vendor/phpmailer/phpmailer";
if(is_dir($mailFolder)){
    require $mailFolder.'/src/PHPMailer.php';
    require $mailFolder.'/src/Exception.php';
    require $mailFolder.'/src/SMTP.php';
} else {
    JSON_reply(500,"Please run composer. PHPmailer is not installed.");
}

class Mail {
    protected $mail;

    function __construct($settings) {
        $this->mail = new \PHPMailer\PHPMailer\PHPMailer();
        $this->mail->isSMTP();                                      // Set mailer to use SMTP
        $this->mail->Host = $settings['host'];  // Specify main and backup SMTP servers
        $this->mail->SMTPAuth = $settings['smtp_auth'];                               // Enable SMTP authentication
        $this->mail->Username = $settings['username'];                 // SMTP username
        $this->mail->Password = base64_decode($settings['password']);                           // SMTP password
        $this->mail->SMTPSecure = $settings['smtp_secure'];                            // Enable TLS encryption, `ssl` also accepted
        $this->mail->Port = $settings['port'];                                    // TCP port to connect to
        $this->mail->CharSet = 'utf8';
        $this->Template = file_get_contents($settings['template']);
        $this->mail->isHTML(true);                                  // Set email format to HTML
    }

    function sendMail($email) {
        $this->mail->Subject = $email['subject'];
        $this->mail->Body = $this->generateBody($email);
        $this->mail->setFrom($email['from'], $email['from_name']);
        if(is_array($email['recipients'])){
            foreach ($email['recipients'] as $recipient){
                $this->mail->addAddress($recipient);               // Name is optional
            }
        } else {
            $this->mail->addAddress($email['recipients']);               // Name is optional
        }
        if(isset($email['bcc_email'])){
            $this->mail->addBCC($email['bcc_email']);               // Name is optional
        }
        if((isset($email['attachment']))&&($email['attachment'] != "")){
            $this->mail->addAttachment($email['attachment'], basename($email['attachment']));
        }
        if(!$this->mail->send()) {
            $answer['sent'] = false;
            $answer['message'] = 'Mailer Error: ' . $this->mail->ErrorInfo;
        } else {
            $this->mail->ClearAddresses();
            $this->mail->ClearAttachments();
            $answer['sent'] = true;
            $answer['message'] = 'E-mail has been sent';
        }
        return $answer;
    }

    function generateBody($email) {
        $body = $this->Template;
        foreach ($email['parameters'] as $parameter=>$value){
            $body = str_replace("%%$parameter%%",$value, $body);
        }
        return $body;
    }
}