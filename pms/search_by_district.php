<?php
/** Legacy URL: district search now lives in the reports list filters. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$q = [];
$d = get_str('district', 100);
if ($d !== '') {
    $id = db_value('SELECT id FROM districts WHERE name = ?', 's', [$d]);
    if ($id !== null) {
        $q['district'] = (int) $id;
    }
}
redirect((is_staff() ? 'view_reports.php' : 'manage_reports.php') . ($q ? '?' . http_build_query($q) : ''));
