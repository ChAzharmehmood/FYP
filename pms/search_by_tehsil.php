<?php
/** Legacy URL: tehsil search now lives in the reports list filters. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$q = [];
$t = get_str('tehsil', 100);
if ($t !== '') {
    $row = db_one('SELECT id, district_id FROM tehsils WHERE name = ? LIMIT 1', 's', [$t]);
    if ($row) {
        $q['district'] = (int) $row['district_id'];
        $q['tehsil'] = (int) $row['id'];
    }
}
redirect((is_staff() ? 'view_reports.php' : 'manage_reports.php') . ($q ? '?' . http_build_query($q) : ''));
