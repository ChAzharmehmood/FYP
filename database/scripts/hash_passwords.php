<?php
/**
 * One-time (but safely re-runnable) migration of legacy plaintext passwords.
 *
 *   php database/scripts/hash_passwords.php            hash every plaintext password
 *   php database/scripts/hash_passwords.php --dry-run  only count
 *
 * Rows whose password already looks like a bcrypt/argon hash are skipped, so
 * running the script twice never double-hashes. Values are never printed.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}
require_once dirname(__DIR__, 2) . '/pms/config.php';

$dry = in_array('--dry-run', array_slice($argv, 1), true);
$db  = db();

$rows = $db->query('SELECT id, name, password FROM staff')->fetch_all(MYSQLI_ASSOC);
$todo = 0;
$done = 0;
$upd  = $db->prepare('UPDATE staff SET password = ?, password_changed_at = COALESCE(password_changed_at, NOW()) WHERE id = ? AND password = ?');
foreach ($rows as $r) {
    $info = password_get_info((string) $r['password']);
    if (!empty($info['algo'])) {
        continue; // already hashed
    }
    $todo++;
    if ($dry) {
        continue;
    }
    if ((string) $r['password'] === '') {
        echo "  skip #{$r['id']} ({$r['name']}): empty password, set one from the staff page\n";
        continue;
    }
    $hash = password_hash((string) $r['password'], PASSWORD_DEFAULT);
    $id   = (int) $r['id'];
    $upd->bind_param('sis', $hash, $id, $r['password']);
    $upd->execute();
    if ($upd->affected_rows === 1) {
        $done++;
    }
}
echo $dry
    ? "$todo account(s) still use a plaintext password.\n"
    : "Hashed $done of $todo plaintext password(s). Re-running is safe.\n";
