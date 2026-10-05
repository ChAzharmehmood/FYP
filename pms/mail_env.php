<?php
if (is_file(__DIR__ . '/mail_config.php')) require_once __DIR__ . '/mail_config.php';
foreach (['SMTP_ALERTS_USER', 'SMTP_ALERTS_PASS', 'SMTP_DUTIES_USER', 'SMTP_DUTIES_PASS'] as $name) {
    if (!defined($name)) define($name, getenv($name) ?: '');
}
