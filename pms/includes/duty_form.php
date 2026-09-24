<?php
/**
 * Shared handler + form for assign_duties.php (create) and edit_duty.php (edit).
 * Expects $duty (array|null). Saves first, then tries the email: a failed
 * notification never loses the duty (notify_status records it for retry).
 */
declare(strict_types=1);
require_once PMS_ROOT . '/includes/duties.php';

$duty   = $duty ?? null;
$isEdit = $duty !== null;
$stationId = is_admin() ? null : user_station_id();

if (is_post() && post_str('action', 20) === 'save') {
    csrf_verify();
    $staffId  = post_int('staff_id', 0) ?? 0;
    $desc     = post_str('duty_description', 500);
    $start    = post_str('start_time', 20);
    $end      = post_str('end_time', 20);
    $shift    = post_str('shift_type', 20);
    $location = post_str('Duty_location', 255);
    $checkpoint = post_int('checkpoint_id');
    $force    = post_str('force', 1) === '1';
    $errors   = [];

    $staff = $staffId ? db_one('SELECT id, name, email, police_station_id FROM staff WHERE id = ? AND is_active = 1', 'i', [$staffId]) : null;
    if (!$staff) {
        $errors[] = 'Choose an active staff member.';
    } elseif (!is_admin() && (int) $staff['police_station_id'] !== user_station_id()) {
        $errors[] = 'That staff member belongs to another station.';
    }
    if ($desc === '') {
        $errors[] = 'Duty description is required.';
    }
    if (!valid_datetime_local($start) || !valid_datetime_local($end)) {
        $errors[] = 'Start and end must be valid date/times.';
    } elseif (strtotime($end) <= strtotime($start)) {
        $errors[] = 'End must be after start (overnight duties simply end on the next day).';
    } elseif ((strtotime($end) - strtotime($start)) > 24 * 3600) {
        $errors[] = 'A single duty cannot be longer than 24 hours.';
    }
    if (!in_array($shift, SHIFT_TYPES, true)) {
        $errors[] = 'Choose a shift type.';
    }
    if ($location === '') {
        $errors[] = 'Duty location is required.';
    }
    $conflicts = [];
    if (!$errors) {
        $startSql = date('Y-m-d H:i:s', strtotime($start));
        $endSql   = date('Y-m-d H:i:s', strtotime($end));
        $conflicts = duty_conflicts((int) $staff['id'], $startSql, $endSql, $isEdit ? (int) $duty['id'] : null);
        if ($conflicts && !$force) {
            $errors[] = 'Conflicts found: ' . implode('; ', $conflicts) . '. Tick "Save anyway" to override.';
        }
    }
    if ($errors) {
        foreach ($errors as $er) {
            flash('danger', $er);
        }
        keep_old($_POST);
        redirect($isEdit ? 'edit_duty.php?id=' . (int) $duty['id'] : 'assign_duties.php');
    }
    $psId = (int) $staff['police_station_id'];
    $psName = (string) db_value('SELECT police_station_name FROM police_stations WHERE id = ?', 'i', [$psId], '');
    $assignedDate = date('Y-m-d', strtotime($start));
    if ($isEdit) {
        db_exec(
            'UPDATE duties SET staff_id = ?, duty_description = ?, start_time = ?, end_time = ?, police_station_id = ?, police_station_name = ?, shift_type = ?, Duty_location = ?, checkpoint_id = ?, assigned_date = ? WHERE id = ?',
            'isssisssisi',
            [(int) $staff['id'], $desc, $startSql, $endSql, $psId, $psName, $shift, $location, $checkpoint, $assignedDate, (int) $duty['id']]
        );
        audit_log('duty.update', 'duty', (int) $duty['id'], ['staff_id' => $staff['id'], 'override_conflicts' => (bool) $conflicts]);
        flash('success', 'Duty updated.');
        $dutyId = (int) $duty['id'];
    } else {
        db_exec(
            'INSERT INTO duties (staff_id, duty_description, start_time, end_time, police_station_id, police_station_name, shift_type, Duty_location, checkpoint_id, assigned_date, status, notify_status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "scheduled", "not_sent", ?, NOW())',
            'isssisssisi',
            [(int) $staff['id'], $desc, $startSql, $endSql, $psId, $psName, $shift, $location, $checkpoint, $assignedDate, (int) current_user()['id']]
        );
        $dutyId = db_insert_id();
        audit_log('duty.create', 'duty', $dutyId, ['staff_id' => $staff['id'], 'override_conflicts' => (bool) $conflicts]);
        flash('success', 'Duty assigned to ' . $staff['name'] . '.');
    }
    // Notify after the record is safely saved.
    $saved = duty_find($dutyId);
    if ($saved) {
        [$ok, $err] = duty_notify($saved);
        if ($ok) {
            flash('success', 'Notification email sent to ' . $staff['name'] . '.');
        } else {
            flash('warning', 'Duty saved, but the email was not sent (' . $err . '). You can retry from the duties list.');
        }
    }
    clear_old();
    redirect('view_duties.php');
}

$old = old_pull();
$val = fn(string $k, $default = '') => $old[$k] ?? ($duty[$k] ?? $default);
$toLocal = fn(?string $dt) => $dt ? date('Y-m-d\TH:i', strtotime($dt)) : '';
$staffOptions = is_admin()
    ? db_all('SELECT s.id, s.name, s.designation, ps.police_station_name FROM staff s LEFT JOIN police_stations ps ON ps.id = s.police_station_id WHERE s.is_active = 1 AND s.role <> "admin" ORDER BY ps.police_station_name, s.name')
    : db_all('SELECT s.id, s.name, s.designation, NULL AS police_station_name FROM staff s WHERE s.is_active = 1 AND s.police_station_id = ? ORDER BY s.name', 'i', [(int) $stationId]);
?>
<form method="post" class="needs-validation" novalidate>
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="staff_id" class="form-label required">Staff member</label>
            <select class="form-select" id="staff_id" name="staff_id" required>
                <option value="">Select staff</option>
                <?php foreach ($staffOptions as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) $val('staff_id', 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?> (<?= e($s['designation']) ?>)<?= $s['police_station_name'] ? ' · ' . e($s['police_station_name']) : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label for="duty_description" class="form-label required">Duty description</label>
            <input class="form-control" id="duty_description" name="duty_description" required maxlength="500" value="<?= e($val('duty_description')) ?>">
        </div>
        <div class="col-md-3">
            <label for="start_time" class="form-label required">Start</label>
            <input type="datetime-local" class="form-control" id="start_time" name="start_time" required value="<?= e($old['start_time'] ?? $toLocal($duty['start_time'] ?? null)) ?>">
        </div>
        <div class="col-md-3">
            <label for="end_time" class="form-label required">End</label>
            <input type="datetime-local" class="form-control" id="end_time" name="end_time" required value="<?= e($old['end_time'] ?? $toLocal($duty['end_time'] ?? null)) ?>">
            <div class="form-text">Overnight: choose the next day's date.</div>
        </div>
        <div class="col-md-2">
            <label for="shift_type" class="form-label required">Shift</label>
            <select class="form-select" id="shift_type" name="shift_type" required>
                <?php foreach (SHIFT_TYPES as $s): ?><option value="<?= e($s) ?>" <?= $val('shift_type') === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="Duty_location" class="form-label required">Location</label>
            <input class="form-control" id="Duty_location" name="Duty_location" required maxlength="255" value="<?= e($val('Duty_location')) ?>">
        </div>
        <div class="col-md-2">
            <label for="checkpoint_id" class="form-label">Checkpoint #</label>
            <input type="number" class="form-control" id="checkpoint_id" name="checkpoint_id" min="0" value="<?= e($val('checkpoint_id')) ?>">
        </div>
        <div class="col-12">
            <div class="form-check"><input class="form-check-input" type="checkbox" id="force" name="force" value="1"><label class="form-check-label" for="force">Save anyway if the staff member has an overlapping duty or approved leave</label></div>
        </div>
    </div>
    <div class="mt-4">
        <button type="submit" class="btn btn-navy"><?= $isEdit ? 'Save changes' : 'Assign duty' ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(app_url('view_duties.php')) ?>">Cancel</a>
    </div>
</form>
