<?php
/** Session configuration: HttpOnly, SameSite, Secure on HTTPS, inactivity timeout. */
declare(strict_types=1);

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('PMSSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,   // local HTTP development still works
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    // Inactivity expiry.
    $limit = max(5, (int) config('session_lifetime_minutes', 30)) * 60;
    $last  = $_SESSION['_last_activity'] ?? null;
    if ($last !== null && (time() - (int) $last) > $limit) {
        $wasLoggedIn = !empty($_SESSION['user_id']);
        $_SESSION = [];
        session_regenerate_id(true);
        if ($wasLoggedIn) {
            $_SESSION['_flash'][] = ['type' => 'warning', 'message' => 'You were logged out after a period of inactivity.'];
        }
    }
    $_SESSION['_last_activity'] = time();

    // Expire the legacy "remember me" cookies that stored the password in plain text.
    foreach (['username', 'password'] as $legacy) {
        if (isset($_COOKIE[$legacy])) {
            setcookie($legacy, '', ['expires' => time() - 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            unset($_COOKIE[$legacy]);
        }
    }
}
