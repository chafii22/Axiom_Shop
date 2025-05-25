<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;



require '../vendor/autoload.php'; 

/**
 * Send an email using PHPMailer
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body
 * @return bool Whether the email was sent successfully
 */
function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io'; // Set your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = '9028917e510c14'; // SMTP username
        $mail->Password   = 'e72701499e9021'; // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 2525;
        
        // Recipients
        $mail->setFrom('noreply@axiomshop.com', 'Axiom Shop');
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = nl2br($body);
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}