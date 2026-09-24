<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/reports.php';
$me = require_login();
$report = report_load_or_404(get_int('id', 0) ?? 0);
if (!can_edit_report($report)) {
    deny('You may not edit this report.');
}
ob_start();
require PMS_ROOT . '/includes/report_form.php';
$formHtml = ob_get_clean();
$pageTitle = 'Edit Report ' . $report['reference_no'];
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card"><div class="card-body">
    <div class="mb-3"><?= status_badge($report['status']) ?> <span class="text-muted small">Status changes, archiving and evidence are on the <a href="<?= e(app_url('view_report.php?id=' . (int) $report['id'])) ?>">report page</a>.</span></div>
    <?= $formHtml ?>
</div></div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
