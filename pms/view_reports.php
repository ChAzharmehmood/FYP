<?php
/** Staff: reports of my own station (read-only list; staff can open and file). */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_login();
if (user_station_id() === null && !is_admin()) {
    deny('Your account is not linked to a police station.');
}
ob_start();
require PMS_ROOT . '/includes/report_list.php';
$listHtml = ob_get_clean();
$pageTitle = 'Station Reports';
require PMS_ROOT . '/includes/layout_top.php';
echo $listHtml;
require PMS_ROOT . '/includes/layout_bottom.php';
