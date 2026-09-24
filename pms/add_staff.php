<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role([ROLE_ADMIN, ROLE_STATION]);
if (is_station_admin() && user_station_id() === null) {
    deny('Your account is not linked to a station.');
}
$target = null;
ob_start();
require PMS_ROOT . '/includes/staff_form.php';
$formHtml = ob_get_clean();
$pageTitle = 'Add Staff';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card" style="max-width: 860px"><div class="card-body"><?= $formHtml ?></div></div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
