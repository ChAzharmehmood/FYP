<?php
/**
 * Legacy URL kept. Reports are never hard-deleted: this ARCHIVES a report,
 * and only via POST with a CSRF token. A GET request shows a confirmation form.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/reports.php';
$me = require_login();
$report = report_load_or_404(is_post() ? (post_int('id', 0) ?? 0) : (get_int('id', 0) ?? 0));
if (!can_archive_report($report)) {
    deny('You may not archive this report.');
}
if (is_post()) {
    csrf_verify();
    $err = report_archive($report, post_str('reason', 500));
    if ($err !== null) {
        flash('danger', $err);
        redirect('delete_report.php?id=' . (int) $report['id']);
    }
    flash('success', 'Report ' . $report['reference_no'] . ' archived.');
    redirect('manage_reports.php');
}
$pageTitle = 'Archive Report';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card" style="max-width: 560px"><div class="card-body">
    <p>Archive report <strong><?= e($report['reference_no']) ?></strong> (<?= e($report['accused_name']) ?>)? Archived reports are hidden from lists but kept in the database. Head office can restore them.</p>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $report['id'] ?>">
        <div class="mb-3"><label for="reason" class="form-label required">Reason</label><input class="form-control" id="reason" name="reason" required maxlength="500"></div>
        <button class="btn btn-danger">Archive</button>
        <a class="btn btn-outline-secondary" href="<?= e(app_url('view_report.php?id=' . (int) $report['id'])) ?>">Cancel</a>
    </form>
</div></div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
