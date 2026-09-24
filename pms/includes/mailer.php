<?php
/**
 * Email sending through PHPMailer. Never throws: returns [bool ok, string error].
 * With mail_enabled = false (the default) nothing is sent and the caller records
 * the message as "not sent" so business records are never lost because of email.
 */
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function mail_enabled(): bool
{
    return (bool) config('mail_enabled') && (string) config('smtp_user') !== '';
}

function send_mail(string $to, string $toName, string $subject, string $htmlBody, string $textBody = ''): array
{
    if (!mail_enabled()) {
        return [false, 'Email is not configured (mail_enabled is off in config.local.php).'];
    }
    if (!valid_email($to)) {
        return [false, 'Recipient has no valid email address.'];
    }
    require_once PMS_ROOT . '/vendor/autoload.php';
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = (string) config('smtp_host');
        $mail->SMTPAuth   = true;
        $mail->Username   = (string) config('smtp_user');
        $mail->Password   = (string) config('smtp_pass');
        $mail->SMTPSecure = config('smtp_secure') === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) config('smtp_port', 587);
        $mail->Timeout    = 15;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom((string) config('mail_from'), (string) config('mail_from_name'));
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);
        $mail->send();
        return [true, ''];
    } catch (Exception $e) {
        $err = $mail->ErrorInfo ?: $e->getMessage();
        error_log('Mail failed to ' . $to . ': ' . $err);
        return [false, 'Email could not be sent.'];
    } catch (Throwable $t) {
        error_log('Mail failed to ' . $to . ': ' . $t->getMessage());
        return [false, 'Email could not be sent.'];
    }
}
