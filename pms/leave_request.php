<?php
/** Staff: submit a leave request with date validation, overlap check and balance display. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/leave.php';
$me = require_login();
$id = (int) $me['id'];
$types = leave_types_active();

if (is_post()) {
    csrf_verify();
    $typeId = post_int('leave_type_id', 0) ?? 0;
    $start  = post_str('start_date', 10);
    $end    = post_str('end_date', 10);
    $reason = post_str('reason', 1000);
    $errors = [];
    $type = $typeId ? leave_type_find($typeId) : null;
    if (!$type || !(int) $type['is_active']) {
        $errors[] = 'Choose a leave type.';
    }
    if (!valid_date($start) || !valid_date($end)) {
        $errors[] = 'Enter valid start and end dates.';
    } elseif (strtotime($end) < strtotime($start)) {
        $errors[] = 'End date cannot be before start date.';
    } elseif (strtotime($start) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Start date cannot be in the past.';
    } elseif (leave_days_between($start, $end) > 90) {
        $errors[] = 'A single request cannot exceed 90 days.';
    }
    if ($type && (int) $type['requires_reason'] === 1 && $reason === '') {
        $errors[] = 'Please give a reason.';
    }
    if (!$errors) {
        $days = leave_days_between($start, $end);
        $overlaps = leave_overlaps($id, $start, $end);
        if ($overlaps) {
            $o = $overlaps[0];
            $errors[] = 'This overlaps your ' . $o['status'] . ' request #' . $o['id'] . ' (' . fmt_date($o['leave_start_date']) . ' – ' . fmt_date($o['leave_end_date']) . ').';
        }
        [$allow, $used, $remaining] = leave_balance($id, $type, (int) date('Y', strtotime($start)));
        if ($remaining !== null && $days > $remaining) {
            $errors[] = "You have $remaining day(s) of {$type['name']} leave left this year; this request needs $days.";
        }
    }
    if ($errors) {
        foreach ($errors as $er) {
            flash('danger', $er);
        }
        keep_old($_POST);
        redirect('leave_request.php');
    }
    db_begin();
    db_exec('INSERT INTO leave_requests (staff_id, leave_type, leave_type_id, leave_start_date, leave_end_date, requested_days, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, "pending")',
        'isissis', [$id, $type['name'], (int) $type['id'], $start, $end, $days, $reason]);
    $newId = db_insert_id();
    db_exec('INSERT INTO leave_request_history (leave_request_id, action, actor_id) VALUES (?, "submitted", ?)', 'ii', [$newId, $id]);
    db_commit();
    audit_log('leave.submit', 'leave_request', $newId, ['type' => $type['name'], 'days' => $days]);
    clear_old();
    flash('success', 'Leave request submitted for review.');
    redirect('leave_status.php');
}
$old = old_pull();
$balances = [];
foreach ($types as $t) {
    [$allow, $used, $rem] = leave_balance($id, $t, (int) date('Y'));
    $balances[] = ['t' => $t, 'allow' => $allow, 'used' => $used, 'rem' => $rem];
}
$pageTitle = 'Request Leave';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <form method="post" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="leave_type_id" class="form-label required">Leave type</label>
                        <select class="form-select" id="leave_type_id" name="leave_type_id" required>
                            <option value="">Select</option>
                            <?php foreach ($types as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($old['leave_type_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label for="start_date" class="form-label required">Start date</label><input type="date" class="form-control" id="start_date" name="start_date" required min="<?= date('Y-m-d') ?>" value="<?= e($old['start_date'] ?? '') ?>"></div>
                    <div class="col-md-4"><label for="end_date" class="form-label required">End date</label><input type="date" class="form-control" id="end_date" name="end_date" required min="<?= date('Y-m-d') ?>" value="<?= e($old['end_date'] ?? '') ?>"></div>
                    <div class="col-12"><label for="reason" class="form-label required">Reason</label><textarea class="form-control" id="reason" name="reason" rows="3" maxlength="1000" required><?= e($old['reason'] ?? '') ?></textarea></div>
                </div>
                <div class="mt-4"><button class="btn btn-navy">Submit request</button> <a class="btn btn-outline-secondary" href="<?= e(app_url('leave_status.php')) ?>">My requests</a></div>
            </form>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Your balance for <?= date('Y') ?></h2>
            <table class="table table-sm mb-2"><thead><tr><th>Type</th><th class="text-end">Allowance</th><th class="text-end">Used</th><th class="text-end">Left</th></tr></thead>
                <tbody><?php foreach ($balances as $b): ?><tr><td><?= e($b['t']['name']) ?></td><td class="text-end"><?= $b['allow'] === null ? '—' : (int) $b['allow'] ?></td><td class="text-end"><?= (int) $b['used'] ?></td><td class="text-end"><?= $b['rem'] === null ? 'No limit' : (int) $b['rem'] ?></td></tr><?php endforeach; ?></tbody></table>
            <p class="small text-muted mb-0">Allowances are demonstration defaults set by head office, not an official policy. Days are counted inclusively (Mon–Wed = 3 days).</p>
        </div></div>
    </div>
</div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
