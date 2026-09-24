<?php
/** Reports list for head office (all stations) and station admins (own station). */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role([ROLE_ADMIN, ROLE_STATION]);
ob_start();
require PMS_ROOT . '/includes/report_list.php';
$listHtml = ob_get_clean();
$pageTitle = 'Crime Reports';
require PMS_ROOT . '/includes/layout_top.php';
echo $listHtml;
require PMS_ROOT . '/includes/layout_bottom.php';
