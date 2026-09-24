<?php
/** Head office: all staff. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role([ROLE_ADMIN]);
ob_start();
require PMS_ROOT . '/includes/staff_list.php';
$listHtml = ob_get_clean();
$pageTitle = 'Staff';
require PMS_ROOT . '/includes/layout_top.php';
echo $listHtml;
require PMS_ROOT . '/includes/layout_bottom.php';
