<?php
/**
 * Clean print view (use the browser's Print / Save as PDF). This is a browser
 * print page, not a server-generated PDF, and it does not claim to follow any
 * official FIR format.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/reports.php';
$me = require_login();
$report = report_load_or_404(get_int('id', 0) ?? 0);
audit_log('report.print', 'report', (int) $report['id']);
$evidence = evidence_list((int) $report['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report <?= e($report['reference_no']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>?v=2">
</head>
<body class="bg-white">
<div class="print-sheet">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
        <div class="d-flex align-items-center gap-3">
            <img src="<?= e(app_url('img/logo1.png')) ?>" alt="" width="56" height="56">
            <div><div class="fw-bold fs-5">Azad Jammu &amp; Kashmir Police</div><div class="text-muted">Crime report record</div></div>
        </div>
        <div class="text-end"><div class="fw-bold fs-5"><?= e($report['reference_no']) ?></div><div><?= e($report['status']) ?></div></div>
    </div>
    <h2>Case information</h2>
    <dl>
        <dt>Police station</dt><dd><?= e(report_display_station($report)) ?></dd>
        <dt>District / tehsil</dt><dd><?= e(report_display_district($report)) ?> / <?= e(report_display_tehsil($report)) ?></dd>
        <dt>Report date</dt><dd><?= fmt_date($report['report_date']) ?></dd>
        <dt>Section</dt><dd><?= e($report['under_section']) ?></dd>
        <dt>Crime type</dt><dd><?= e($report['crime_type'] ?: ($report['complainant'] ?: '—')) ?></dd>
        <dt>Investigation officer</dt><dd><?= e($report['investigation_officer']) ?></dd>
        <dt>Assigned officer</dt><dd><?= e($report['assigned_to_name'] ?: '—') ?></dd>
    </dl>
    <h2>Accused</h2>
    <dl>
        <dt>Name</dt><dd><?= e($report['accused_name']) ?></dd>
        <dt>CNIC</dt><dd><?= e(is_staff() ? mask_cnic($report['id_card_no']) : ($report['id_card_no'] ?: '—')) ?></dd>
        <dt>Address</dt><dd><?= e($report['accused_address']) ?></dd>
    </dl>
    <h2>Complainant</h2>
    <dl>
        <dt>Name</dt><dd><?= e($report['complainant_name'] ?: '—') ?></dd>
        <dt>Contact</dt><dd><?= e($report['complainant_contact'] ?: '—') ?></dd>
    </dl>
    <h2>Description</h2>
    <p style="white-space: pre-wrap"><?= e($report['report_description']) ?></p>
    <?php if ($evidence): ?>
    <h2>Attached evidence</h2>
    <ol><?php foreach ($evidence as $ev): ?><li><?= e($ev['original_name']) ?> (<?= e($ev['mime_type']) ?>, <?= number_format((int) $ev['size_bytes'] / 1024, 1) ?> KB)</li><?php endforeach; ?></ol>
    <?php endif; ?>
    <div class="row mt-5 pt-4">
        <div class="col-6 text-center"><div class="border-top pt-2 small">Investigating officer</div></div>
        <div class="col-6 text-center"><div class="border-top pt-2 small">Station in-charge</div></div>
    </div>
    <p class="small text-muted mt-4 mb-0">Printed by <?= e($me['name']) ?> on <?= fmt_datetime(now_sql()) ?> (Pakistan Standard Time). Filed by <?= e($report['created_by_name'] ?: 'unknown') ?> on <?= fmt_datetime($report['created_at']) ?>.</p>
    <div class="no-print mt-4 text-center">
        <button class="btn btn-navy" onclick="window.print()">Print / Save as PDF</button>
        <a class="btn btn-outline-secondary" href="<?= e(app_url('view_report.php?id=' . (int) $report['id'])) ?>">Back</a>
    </div>
</div>
</body>
</html>
