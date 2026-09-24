<?php
/** Edit, cancel, complete or re-notify a duty. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/duties.php';
$me = require_role([ROLE_STATION, ROLE_ADMIN]);
$duty = duty_load_or_404(is_post() ? (post_int('id', 0) ?? get_int('id', 0) ?? 0) : (get_int('id', 0) ?? 0));
if (!can_edit_duty($duty)) {
    deny('You may not change this duty.');
}

if (is_post() && post_str('action', 20) !== 'save') {
    csrf_verify();
    $action = post_str('action', 20);
    if ($action === 'cancel') {
        $reason = post_str('reason', 255);
        if ($duty['status'] !== 'scheduled') {
            flash('danger', 'Only scheduled duties can be cancelled.');
        } elseif ($reason === '') {
            flash('danger', 'Please give a reason for cancelling.');
        } else {
            db_exec('UPDATE duties SET status = "cancelled", cancel_reason = ? WHERE id = ? AND status = "scheduled"', 'si', [$reason, (int) $duty['id']]);
            audit_log('duty.cancel', 'duty', (int) $duty['id'], ['reason' => $reason]);
            flash('success', 'Duty cancelled.');
        }
    } elseif ($action === 'complete') {
        if ($duty['status'] !== 'scheduled') {
            flash('danger', 'Only scheduled duties can be marked complete.');
        } else {
            db_exec('UPDATE duties SET status = "completed", completed_at = NOW() WHERE id = ? AND status = "scheduled"', 'i', [(int) $duty['id']]);
            audit_log('duty.complete', 'duty', (int) $duty['id']);
            flash('success', 'Duty marked as completed.');
        }
    } elseif ($action === 'reopen') {
        if ($duty['status'] === 'scheduled') {
            flash('danger', 'The duty is already scheduled.');
        } else {
            db_exec('UPDATE duties SET status = "scheduled", cancel_reason = NULL, completed_at = NULL WHERE id = ?', 'i', [(int) $duty['id']]);
            audit_log('duty.reopen', 'duty', (int) $duty['id']);
            flash('success', 'Duty is scheduled again.');
        }
    } elseif ($action === 'notify') {
        if ((int) $duty['notify_attempts'] >= 5 && $duty['notify_status'] !== 'sent') {
            flash('warning', 'This duty already has 5 failed attempts; check the staff email address and mail settings first.');
        } else {
            [$ok, $err] = duty_notify($duty);
            flash($ok ? 'success' : 'warning', $ok ? 'Notification sent.' : 'Email not sent: ' . $err);
        }
    }
    redirect('edit_duty.php?id=' . (int) $duty['id']);
}

ob_start();
require PMS_ROOT . '/includes/duty_form.php';
$formHtml = ob_get_clean();
$pageTitle = 'Duty #' . (int) $duty['id'];
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card"><div class="card-body">
            <div class="mb-3"><?= duty_status_badge($duty) ?> <span class="text-muted small"><?= e($duty['staff_name']) ?> · <?= e($duty['station_name'] ?? $duty['police_station_name']) ?></span></div>
            <?php if ($duty['status'] === 'scheduled'): ?><?= $formHtml ?>
            <?php else: ?>
                <dl class="row mb-0">
                    <dt class="col-sm-3">Description</dt><dd class="col-sm-9"><?= e($duty['duty_description']) ?></dd>
                    <dt class="col-sm-3">When</dt><dd class="col-sm-9"><?= fmt_datetime($duty['start_time']) ?> → <?= fmt_datetime($duty['end_time']) ?></dd>
                    <dt class="col-sm-3">Location</dt><dd class="col-sm-9"><?= e($duty['Duty_location']) ?></dd>
                    <?php if ($duty['cancel_reason']): ?><dt class="col-sm-3">Cancel reason</dt><dd class="col-sm-9"><?= e($duty['cancel_reason']) ?></dd><?php endif; ?>
                </dl>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-4"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Notification</h2>
            <p class="mb-2"><?= status_badge($duty['notify_status'] === 'not_sent' ? 'pending' : $duty['notify_status']) ?> <span class="small text-muted">attempts: <?= (int) $duty['notify_attempts'] ?><?= $duty['notified_at'] ? ' · sent ' . fmt_datetime($duty['notified_at']) : '' ?></span></p>
            <?php if ($duty['notify_error']): ?><p class="small text-danger"><?= e($duty['notify_error']) ?></p><?php endif; ?>
            <?php if (!$duty['staff_email']): ?><p class="small text-warning">This staff member has no email address.</p><?php endif; ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $duty['id'] ?>"><input type="hidden" name="action" value="notify"><button class="btn btn-sm btn-outline-primary" <?= !$duty['staff_email'] || $duty['status'] !== 'scheduled' ? 'disabled' : '' ?>><?= $duty['notify_status'] === 'sent' ? 'Send again' : 'Send / retry email' ?></button></form>
        </div></div>
        <div class="card"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Lifecycle</h2>
            <?php if ($duty['status'] === 'scheduled'): ?>
                <form method="post" class="mb-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $duty['id'] ?>"><input type="hidden" name="action" value="complete"><button class="btn btn-sm btn-outline-success w-100">Mark completed</button></form>
                <form method="post" data-confirm="Cancel this duty?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $duty['id'] ?>"><input type="hidden" name="action" value="cancel">
                    <input class="form-control form-control-sm mb-2" name="reason" maxlength="255" placeholder="Reason for cancelling" required>
                    <button class="btn btn-sm btn-outline-danger w-100">Cancel duty</button></form>
            <?php else: ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $duty['id'] ?>"><input type="hidden" name="action" value="reopen"><button class="btn btn-sm btn-outline-secondary w-100">Set back to scheduled</button></form>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
