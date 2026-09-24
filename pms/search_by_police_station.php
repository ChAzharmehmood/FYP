<?php
/** Legacy URL: station search now lives in the reports list filters. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$q = [];
$s = get_str('police_station', 150);
if ($s !== '' && is_admin()) {
    $id = db_value('SELECT id FROM police_stations WHERE police_station_name = ?', 's', [$s]);
    if ($id !== null) {
        $q['station'] = (int) $id;
    }
}
redirect((is_staff() ? 'view_reports.php' : 'manage_reports.php') . ($q ? '?' . http_build_query($q) : ''));
