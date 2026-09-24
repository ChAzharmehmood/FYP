<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role([ROLE_ADMIN]);
$station = null;
ob_start();
require PMS_ROOT . '/includes/station_form.php';
$formHtml = ob_get_clean();
$pageTitle = 'Add Police Station';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card" style="max-width: 720px"><div class="card-body"><?= $formHtml ?></div></div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
