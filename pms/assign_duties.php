<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role([ROLE_STATION, ROLE_ADMIN]);
if (!can_manage_duties() || (is_station_admin() && user_station_id() === null)) {
    deny('Your account is not linked to a police station.');
}
$duty = null;
ob_start();
require PMS_ROOT . '/includes/duty_form.php';
$formHtml = ob_get_clean();
$pageTitle = 'Assign Duty';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card"><div class="card-body"><?= $formHtml ?></div></div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
