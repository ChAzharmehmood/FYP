<?php
/**
 * Local configuration. Copy this file to config.local.php and edit it.
 * config.local.php is git-ignored and must never be committed.
 */
return [
    // Database
    'db_host' => 'localhost',
    'db_user' => 'root',
    'db_pass' => '',
    'db_name' => 'db_pms',

    // Application
    'app_name'   => 'Police Management System',
    'app_url'    => 'http://localhost/pms',   // no trailing slash
    'app_env'    => 'local',                  // local | production
    'app_debug'  => false,                    // true shows technical errors on screen (local only)
    'timezone'   => 'Asia/Karachi',

    // Session
    'session_lifetime_minutes' => 30,         // inactivity timeout

    // Uploads: stored OUTSIDE the web root (default: <project>/storage)
    'storage_path' => dirname(__DIR__) . '/storage',
    'upload_max_bytes' => 5 * 1024 * 1024,   // 5 MB per file
    'upload_max_files' => 5,                  // per request

    // Email (PHPMailer over SMTP). Leave smtp_user empty to disable sending.
    'mail_enabled'  => false,
    'smtp_host'     => 'smtp.gmail.com',
    'smtp_port'     => 587,
    'smtp_secure'   => 'tls',                 // tls | ssl
    'smtp_user'     => '',
    'smtp_pass'     => '',                    // Gmail App Password, never your account password
    'mail_from'     => 'no-reply@example.com',
    'mail_from_name'=> 'Police Management System',
];
