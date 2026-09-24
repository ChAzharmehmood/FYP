<?php
/**
 * Legacy AJAX endpoint (URL kept). Staff are never hard-deleted because
 * duties, leave and reports reference them; this DEACTIVATES the account.
 * Requires POST, a login with permission, and a CSRF token (field or X-CSRF-Token header).
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role([ROLE_ADMIN, ROLE_STATION]);

if (!is_post()) {
    json_response(['ok' => false, 'error' => 'POST required.'], 405);
}
$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
if (is_array($data)) {
    $_POST = array_merge($_POST, $data); // allow JSON body with csrf_token + id
}
csrf_verify();
$id = post_int('id', 0) ?? 0;
$t  = $id > 0 ? db_one('SELECT * FROM staff WHERE id = ?', 'i', [$id]) : null;
if (!$t) {
    json_response(['ok' => false, 'error' => 'Staff member not found.'], 404);
}
if (!can_deactivate_staff($t)) {
    json_response(['ok' => false, 'error' => 'You may not deactivate this account.'], 403);
}
db_exec('UPDATE staff SET is_active = 0, session_version = session_version + 1 WHERE id = ?', 'i', [$id]);
audit_log('staff.deactivate', 'staff', $id, ['via' => 'delete_staff.php']);
json_response(['ok' => true, 'message' => 'Account deactivated. Records are preserved.']);
