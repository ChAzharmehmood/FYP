<?php
/** Report detail: status workflow, archive/restore, evidence, history. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/reports.php';
$me = require_login();
$report = report_load_or_404(get_int('id', 0) ?? 0);

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 20);
    $err = null;
    if ($action === 'status') {
        $err = report_change_status($report, post_str('status', 30), post_str('reason', 500));
        if ($err === null) {
            flash('success', 'Status updated.');
        }
    } elseif ($action === 'archive') {
        $err = report_archive($report, post_str('reason', 500));
        if ($err === null) {
            flash('success', 'Report archived.');
        }
    } elseif ($action === 'restore') {
        $err = report_archive($report, post_str('reason', 500), true);
        if ($err === null) {
            flash('success', 'Report restored.');
        }
    } elseif ($action === 'assign') {
        if (!can_change_report_status($report)) {
            $err = 'You may not assign this report.';
        } else {
            $to = post_int('assigned_to');
            if ($to !== null) {
                $officer = db_one('SELECT id, name, police_station_id FROM staff WHERE id = ? AND is_active = 1', 'i', [$to]);
                if (!$officer || (!is_admin() && (int) $officer['police_station_id'] !== user_station_id())) {
                    $err = 'Choose an active officer of this station.';
                }
            }
            if ($err === null) {
                db_exec('UPDATE reports SET assigned_to = ? WHERE id = ?', 'ii', [$to, (int) $report['id']]);
                audit_log('report.assign', 'report', (int) $report['id'], ['assigned_to' => $to]);
                flash('success', $to ? 'Report assigned.' : 'Assignment cleared.');
            }
        }
    }
    if ($err !== null) {
        flash('danger', $err);
    }
    redirect('view_report.php?id=' . (int) $report['id']);
}

$evidence = evidence_list((int) $report['id']);
$history  = db_all('SELECT h.*, s.name AS actor_name FROM report_status_history h LEFT JOIN staff s ON s.id = h.actor_id WHERE h.report_id = ? ORDER BY h.id', 'i', [(int) $report['id']]);
$officers = can_change_report_status($report)
    ? db_all('SELECT id, name, designation FROM staff WHERE is_active = 1 AND police_station_id = ? ORDER BY name', 'i', [(int) $report['police_station_id']])
    : [];
$nextStatuses = report_status_options($report);

$pageTitle = 'Report ' . $report['reference_no'];
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="d-flex flex-wrap gap-2 mb-3 no-print">
    <?= status_badge($report['status']) ?>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('print_report.php?id=' . (int) $report['id'])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> Print view</a>
        <?php if (can_edit_report($report)): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('edit_report.php?id=' . (int) $report['id'])) ?>"><i class="fa-solid fa-pen"></i> Edit details</a><?php endif; ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url(is_staff() ? 'view_reports.php' : 'manage_reports.php')) ?>">Back to list</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Report details</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4">Reference</dt><dd class="col-sm-8 fw-semibold"><?= e($report['reference_no']) ?></dd>
                <dt class="col-sm-4">Police station</dt><dd class="col-sm-8"><?= e(report_display_station($report)) ?></dd>
                <dt class="col-sm-4">District / tehsil</dt><dd class="col-sm-8"><?= e(report_display_district($report)) ?> / <?= e(report_display_tehsil($report)) ?></dd>
                <dt class="col-sm-4">Report date</dt><dd class="col-sm-8"><?= fmt_date($report['report_date']) ?></dd>
                <dt class="col-sm-4">Section</dt><dd class="col-sm-8"><?= e($report['under_section']) ?></dd>
                <dt class="col-sm-4">Crime type</dt><dd class="col-sm-8"><?= e($report['crime_type'] ?: '—') ?><?= (!$report['crime_type'] && $report['complainant']) ? ' <span class="text-muted small">(legacy value: ' . e($report['complainant']) . ')</span>' : '' ?></dd>
                <dt class="col-sm-4">Accused</dt><dd class="col-sm-8"><?= e($report['accused_name']) ?><br><span class="text-muted small"><?= e($report['accused_address']) ?></span></dd>
                <dt class="col-sm-4">Accused CNIC</dt><dd class="col-sm-8 font-monospace"><?= e(is_staff() ? mask_cnic($report['id_card_no']) : ($report['id_card_no'] ?: '—')) ?></dd>
                <dt class="col-sm-4">Complainant</dt><dd class="col-sm-8"><?= e($report['complainant_name'] ?: '—') ?><?= $report['complainant_contact'] ? ' · ' . e($report['complainant_contact']) : '' ?></dd>
                <dt class="col-sm-4">Investigation officer</dt><dd class="col-sm-8"><?= e($report['investigation_officer']) ?></dd>
                <dt class="col-sm-4">Assigned to</dt><dd class="col-sm-8"><?= e($report['assigned_to_name'] ?: '—') ?></dd>
                <dt class="col-sm-4">Filed by</dt><dd class="col-sm-8"><?= e($report['created_by_name'] ?: 'unknown') ?> · <?= fmt_datetime($report['created_at']) ?></dd>
            </dl>
            <hr>
            <h3 class="h6 text-uppercase text-muted">Description</h3>
            <p class="mb-0" style="white-space: pre-wrap"><?= e($report['report_description']) ?></p>
        </div></div>

        <div class="card mb-4" id="evidence"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Evidence (<?= count($evidence) ?>)</h2>
            <?php if (!$evidence): ?><div class="text-muted small mb-3">No files attached.</div>
            <?php else: ?>
            <div class="table-wrap"><table class="table table-sm align-middle">
                <thead><tr><th>File</th><th>Type</th><th class="text-end">Size</th><th>Uploaded</th><th></th></tr></thead>
                <tbody><?php foreach ($evidence as $ev): ?>
                    <tr>
                        <td><a href="<?= e(app_url('evidence_download.php?id=' . (int) $ev['id'])) ?>"><?= e($ev['original_name']) ?></a></td>
                        <td class="small"><?= e($ev['mime_type']) ?></td>
                        <td class="text-end small"><?= number_format((int) $ev['size_bytes'] / 1024, 1) ?> KB</td>
                        <td class="small"><?= e($ev['uploaded_by_name'] ?? '') ?> · <?= fmt_datetime($ev['created_at']) ?></td>
                        <td class="text-end">
                            <?php if (can_change_report_status($report) && $report['status'] !== 'Archived'): ?>
                            <form method="post" action="<?= e(app_url('evidence.php')) ?>" class="d-inline" data-confirm="Remove this file from the report? It is kept on disk for the audit trail.">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php endif; ?>
            <?php if (can_upload_evidence($report)): ?>
            <form method="post" action="<?= e(app_url('evidence.php')) ?>" enctype="multipart/form-data" class="row g-2 align-items-end">
                <?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                <div class="col-md-8">
                    <label for="evidence_files" class="form-label">Add files</label>
                    <input type="file" class="form-control" id="evidence_files" name="evidence[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.doc,.docx,.mp3,.mp4">
                    <div class="form-text">Up to <?= (int) config('upload_max_files', 5) ?> files, <?= round((int) config('upload_max_bytes') / 1048576, 1) ?> MB each. Images, PDF, Word, text, MP3, MP4.</div>
                </div>
                <div class="col-md-4"><button class="btn btn-navy">Upload</button></div>
            </form>
            <?php endif; ?>
        </div></div>
    </div>

    <div class="col-lg-4">
        <?php if ($nextStatuses || can_archive_report($report) || ($report['status'] === 'Archived' && can_restore_report($report))): ?>
        <div class="card mb-4"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Actions</h2>
            <?php if ($nextStatuses): ?>
            <form method="post" class="mb-3">
                <?= csrf_field() ?><input type="hidden" name="action" value="status">
                <label for="status" class="form-label">Change status</label>
                <select class="form-select mb-2" id="status" name="status" required>
                    <?php foreach ($nextStatuses as $s): ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?>
                </select>
                <input type="text" class="form-control mb-2" name="reason" maxlength="500" placeholder="Reason (required when closing)">
                <button class="btn btn-navy btn-sm">Update status</button>
            </form>
            <?php endif; ?>
            <?php if ($officers): ?>
            <form method="post" class="mb-3">
                <?= csrf_field() ?><input type="hidden" name="action" value="assign">
                <label for="assigned_to" class="form-label">Assign officer</label>
                <select class="form-select mb-2" id="assigned_to" name="assigned_to">
                    <option value="">— unassigned —</option>
                    <?php foreach ($officers as $o): ?><option value="<?= (int) $o['id'] ?>" <?= (int) $report['assigned_to'] === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?> (<?= e($o['designation']) ?>)</option><?php endforeach; ?>
                </select>
                <button class="btn btn-outline-primary btn-sm">Save assignment</button>
            </form>
            <?php endif; ?>
            <?php if ($report['status'] !== 'Archived' && can_archive_report($report)): ?>
            <form method="post" data-confirm="Archive this report? It stays in the database and can be restored by head office.">
                <?= csrf_field() ?><input type="hidden" name="action" value="archive">
                <input type="text" class="form-control mb-2" name="reason" maxlength="500" placeholder="Reason for archiving" required>
                <button class="btn btn-outline-danger btn-sm">Archive report</button>
            </form>
            <?php elseif ($report['status'] === 'Archived' && can_restore_report($report)): ?>
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="restore">
                <input type="text" class="form-control mb-2" name="reason" maxlength="500" placeholder="Reason for restoring">
                <button class="btn btn-outline-success btn-sm">Restore report</button>
            </form>
            <?php endif; ?>
        </div></div>
        <?php endif; ?>

        <div class="card"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">History</h2>
            <?php if (!$history): ?><div class="text-muted small">No history recorded.</div>
            <?php else: ?>
            <ul class="list-unstyled mb-0 small">
                <?php foreach (array_reverse($history) as $h): ?>
                <li class="mb-2 pb-2 border-bottom">
                    <div><?= $h['old_status'] ? e($h['old_status']) . ' → ' : '' ?><strong><?= e($h['new_status']) ?></strong></div>
                    <div class="text-muted"><?= e($h['actor_name'] ?? 'system') ?> · <?= fmt_datetime($h['created_at']) ?></div>
                    <?php if ($h['reason']): ?><div class="fst-italic"><?= e($h['reason']) ?></div><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
