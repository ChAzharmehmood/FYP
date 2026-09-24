<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/reports.php';
$me = require_login();
if (!can_create_report()) {
    deny('Your account is not linked to a police station, so it cannot file reports.');
}
$report = null;
ob_start();
require PMS_ROOT . '/includes/report_form.php';
$formHtml = ob_get_clean();
$pageTitle = 'File a Crime Report';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card"><div class="card-body"><?= $formHtml ?></div></div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
