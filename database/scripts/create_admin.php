<?php
/**
 * Create (or reset) the head-office administrator account. No default
 * credentials are shipped with the project; run this once after installing.
 *
 *   php database/scripts/create_admin.php --name=admin --email=you@example.com
 *
 * The password is read from the terminal without echo (or from the
 * PMS_ADMIN_PASSWORD environment variable for unattended setup). It is never
 * written to any log.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}
require_once dirname(__DIR__, 2) . '/pms/config.php';

$opts  = getopt('', ['name:', 'email::', 'station::']);
$name  = trim((string) ($opts['name'] ?? ''));
$email = trim((string) ($opts['email'] ?? ''));
if ($name === '' || !preg_match('/^[A-Za-z0-9._ -]{3,50}$/', $name)) {
    exit("Usage: php database/scripts/create_admin.php --name=<login name> [--email=<email>] [--station=<station id>]\n");
}
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    exit("Invalid email address.\n");
}

$password = getenv('PMS_ADMIN_PASSWORD') ?: '';
if ($password === '') {
    echo "Password (min 8 characters, typing hidden): ";
    if (stripos(PHP_OS, 'WIN') === 0) {
        $password = trim((string) shell_exec('powershell -NoProfile -Command "$p = Read-Host -AsSecureString; $b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($p); [Runtime.InteropServices.Marshal]::PtrToStringAuto($b)"'));
    } else {
        shell_exec('stty -echo');
        $password = trim((string) fgets(STDIN));
        shell_exec('stty echo');
    }
    echo "\n";
}
if (strlen($password) < 8) {
    exit("Password must be at least 8 characters.\n");
}

$db   = db();
$hash = password_hash($password, PASSWORD_DEFAULT);
$stationId = isset($opts['station']) && $opts['station'] !== '' ? (int) $opts['station'] : null;

$stmt = $db->prepare('SELECT id FROM staff WHERE name = ?');
$stmt->bind_param('s', $name);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    $id = (int) $existing['id'];
    $u  = $db->prepare("UPDATE staff SET password = ?, role = 'admin', is_active = 1, email = COALESCE(NULLIF(?, ''), email), session_version = session_version + 1, password_changed_at = NOW() WHERE id = ?");
    $u->bind_param('ssi', $hash, $email, $id);
    $u->execute();
    echo "Updated existing account '$name' (id $id): password reset, role set to admin, account activated.\n";
} else {
    $i = $db->prepare("INSERT INTO staff (name, email, password, designation, role, police_station_id, police_station_name, is_active, created_at)
                       VALUES (?, NULLIF(?, ''), ?, 'Administrator', 'admin', ?, (SELECT police_station_name FROM police_stations WHERE id = ?), 1, NOW())");
    $i->bind_param('sssii', $name, $email, $hash, $stationId, $stationId);
    $i->execute();
    echo "Created head-office admin '$name' (id " . $i->insert_id . ").\n";
}
