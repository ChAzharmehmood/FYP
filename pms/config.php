<?php
/**
 * Application configuration loader.
 * Real settings live in config.local.php (git-ignored). See config.local.example.php.
 * Every page should include includes/bootstrap.php, which includes this file.
 */
declare(strict_types=1);

if (defined('PMS_CONFIG_LOADED')) {
    return;
}
define('PMS_CONFIG_LOADED', true);
define('PMS_ROOT', __DIR__);                      // web root (pms/)
define('PMS_PROJECT_ROOT', dirname(__DIR__));     // project root (holds database/, storage/, docs/)

$pmsDefaults = require PMS_ROOT . '/config.local.example.php';
$pmsLocal    = is_file(PMS_ROOT . '/config.local.php') ? require PMS_ROOT . '/config.local.php' : [];
if (!is_array($pmsLocal)) {
    $pmsLocal = [];
}
$GLOBALS['PMS_CONFIG'] = array_merge($pmsDefaults, $pmsLocal);
unset($pmsDefaults, $pmsLocal);
// CLI tooling (tests, migrations) may point at another database without touching config.local.php.
if (PHP_SAPI === 'cli' && getenv('PMS_DB_NAME')) {
    $GLOBALS['PMS_CONFIG']['db_name'] = (string) getenv('PMS_DB_NAME');
}

function config(string $key, $default = null)
{
    return $GLOBALS['PMS_CONFIG'][$key] ?? $default;
}

date_default_timezone_set((string) config('timezone', 'Asia/Karachi'));

// Error handling: never show technical details to users unless app_debug is on.
error_reporting(E_ALL);
ini_set('display_errors', config('app_debug') ? '1' : '0');
ini_set('log_errors', '1');
$pmsLogDir = rtrim((string) config('storage_path'), '/\\') . '/logs';
if (!is_dir($pmsLogDir)) {
    @mkdir($pmsLogDir, 0770, true);
}
ini_set('error_log', $pmsLogDir . '/php-error.log');
unset($pmsLogDir);

// Backwards compatibility: the old pages used $conn from this file.
require_once PMS_ROOT . '/includes/db.php';
$conn = db();
