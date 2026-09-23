<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require 'vendor/autoload.php';
require __DIR__ . '/mail_config.php';

$mail = new PHPMailer(true);  // Passing `true` enables exceptions

try {
    //Server settings
    $mail->isSMTP();                                 // Set mailer to use SMTP
    $mail->Host = 'smtp.gmail.com';                  // Set the SMTP server to send through
    $mail->SMTPAuth = true;                          // Enable SMTP authentication
    $mail->Username = SMTP_ALERTS_USER;        // SMTP username (your email)
    $mail->Password = SMTP_ALERTS_PASS;         // SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
    $mail->Port = 587;                               // TCP port to connect to

    //Recipients
    $mail->setFrom('your-email@gmail.com', 'Your Name');  // Sender's email address and name
    $mail->addAddress('recipient@example.com', 'Recipient Name');  // Add a recipient

    // Content
    $mail->isHTML(true);                           // Set email format to HTML
    $mail->Subject = 'Subject of the Email';
    $mail->Body    = 'This is the body of the email <b>in bold!</b>';

    $mail->send();
    echo 'Message has been sent';
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
?>
