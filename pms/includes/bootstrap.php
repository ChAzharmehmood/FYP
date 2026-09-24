<?php
/**
 * Include this file at the top of every page:
 *   require_once __DIR__ . '/includes/bootstrap.php';
 *
 * It loads configuration, the database, the session, authentication, CSRF
 * protection, permissions and helpers. Pages not listed in PUBLIC_ROUTES are
 * protected automatically: an anonymous visitor is sent to the login page.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/geo.php';

// Security headers.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

session_boot();

// Automatic protection for every non-public page (direct URL and AJAX alike).
$pmsScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
if (!in_array($pmsScript, PUBLIC_ROUTES, true) && PHP_SAPI !== 'cli') {
    require_login();
}
unset($pmsScript);
