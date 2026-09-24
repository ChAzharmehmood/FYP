<?php
/** Evidence upload / soft-delete (POST only). */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/reports.php';
$me = require_login();
if (!is_post()) {
    redirect(is_staff() ? 'view_reports.php' : 'manage_reports.php');
}
csrf_verify();
$action = post_str('action', 20);

if ($action === 'upload') {
    $report = report_load_or_404(post_int('report_id', 0) ?? 0);
    if (!can_upload_evidence($report)) {
        deny('You may not add evidence to this report.');
    }
    $files = uploaded_files('evidence');
    $max   = (int) config('upload_max_files', 5);
    if (!$files) {
        flash('warning', 'No files were selected.');
    } elseif (count($files) > $max) {
        flash('danger', "You can upload at most $max files at a time.");
    } else {
        $ok = 0;
        foreach ($files as $f) {
            [$stored, $msg] = evidence_store($report, $f);
            if ($stored) {
                $ok++;
            } else {
                flash('danger', ($f['name'] ?? 'File') . ': ' . $msg);
            }
        }
        if ($ok) {
            flash('success', "$ok file(s) attached.");
        }
    }
    redirect('view_report.php?id=' . (int) $report['id'] . '#evidence');
}

if ($action === 'delete') {
    $ev = db_one('SELECT * FROM report_evidence WHERE id = ? AND deleted_at IS NULL', 'i', [post_int('id', 0) ?? 0]);
    if (!$ev) {
        not_found('Evidence not found.');
    }
    $report = report_load_or_404((int) $ev['report_id']);
    if (!can_change_report_status($report) || $report['status'] === 'Archived') {
        deny('You may not remove evidence from this report.');
    }
    // Soft delete: the record and file stay for the audit trail; the file is no longer downloadable.
    db_exec('UPDATE report_evidence SET deleted_at = NOW(), deleted_by = ? WHERE id = ?', 'ii', [(int) $me['id'], (int) $ev['id']]);
    audit_log('evidence.remove', 'report', (int) $report['id'], ['evidence_id' => (int) $ev['id'], 'name' => $ev['original_name']]);
    flash('success', 'File removed from the report.');
    redirect('view_report.php?id=' . (int) $report['id'] . '#evidence');
}
redirect('manage_reports.php');
