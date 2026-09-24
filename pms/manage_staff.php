<?php
/** Station admin: staff of my station. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role([ROLE_STATION, ROLE_ADMIN]);
if (is_station_admin() && user_station_id() === null) {
    deny('Your account is not linked to a police station.');
}
ob_start();
require PMS_ROOT . '/includes/staff_list.php';
$listHtml = ob_get_clean();
$pageTitle = 'Station Staff';
require PMS_ROOT . '/includes/layout_top.php';
echo $listHtml;
require PMS_ROOT . '/includes/layout_bottom.php';
