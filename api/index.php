<?php
// Every request uses an allowlist; never serve PHP source, SQL or config files.
$app = dirname(__DIR__) . '/pms';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (strpos($path, '/pms/') === 0) $path = substr($path, 4);
if ($path === '/') $path = '/index.php';
if (preg_match('~^/img/[a-zA-Z0-9_.-]+\.(?:png|jpg|jpeg|gif|webp|ico)$~D', $path)) {
    $file = $app . $path;
    if (is_file($file)) {
        $types = ['png'=>'image/png', 'jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'gif'=>'image/gif', 'webp'=>'image/webp', 'ico'=>'image/x-icon'];
        header('Content-Type: ' . $types[pathinfo($file, PATHINFO_EXTENSION)]);
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }
}
$pages = require $app . '/server/pages.php';
$page = ltrim($path, '/');
if (!in_array($page, $pages, true)) {
    http_response_code(404);
    exit('Page not found.');
}
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
chdir($app);
require_once $app . '/config.php';
require_once $app . '/server/sessions.php';
// Legacy pages call session_start after this shared handler has been registered.
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>(bool)getenv('VERCEL'), 'httponly'=>true, 'samesite'=>'Lax']);
session_set_save_handler(new PmsDatabaseSessions($conn), true);
$_SERVER['SCRIPT_NAME'] = '/' . $page;
$_SERVER['PHP_SELF'] = '/' . $page;
require $app . '/' . $page;
