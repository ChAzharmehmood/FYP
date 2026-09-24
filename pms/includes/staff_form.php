<?php
/**
 * Shared handler + form for add_staff.php and edit_staff.php.
 * Expects $target (array|null: the staff row being edited).
 * Never trusts posted role/station beyond what assignable_roles() and
 * stations_for_user() allow for the current user.
 */
declare(strict_types=1);

$target = $target ?? null;
$isEdit = $target !== null;

if (is_post()) {
    csrf_verify();
    $name        = post_str('name', 100);
    $email       = post_str('email', 150);
    $designation = post_str('designation', 100);
    $role        = post_str('role', 20);
    $stationId   = post_int('station_id');
    $cnicRaw     = post_str('id_card_no', 20);
    $password    = (string) ($_POST['password'] ?? '');
    $errors      = [];

    if (!preg_match('/^[A-Za-z0-9._ -]{3,100}$/', $name)) {
        $errors[] = 'Username must be 3-100 characters (letters, numbers, dot, dash, underscore, space).';
    }
    if ($email !== '' && !valid_email($email)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($designation === '') {
        $errors[] = 'Designation is required.';
    }
    if (!in_array($role, assignable_roles(), true)) {
        $errors[] = 'You may not assign that role.';
    }
    $cnic = null;
    if ($isEdit && ($cnicRaw === '' || $cnicRaw[0] === '*')) {
        $cnic = $target['id_card_no'] ?: null; // untouched masked value: keep the stored CNIC
    } elseif ($cnicRaw !== '') {
        $cnic = normalize_cnic($cnicRaw);
        if ($cnic === null) {
            $errors[] = 'CNIC must have 13 digits (12345-1234567-1).';
        }
    }
    $allowedStations = array_map(fn($s) => (int) $s['id'], stations_for_user());
    if ($isEdit && is_station_admin()) {
        $stationId = (int) $target['police_station_id']; // station admins cannot move staff between stations
    }
    if ($stationId === null || !in_array($stationId, $allowedStations, true)) {
        if (!(is_admin() && $role === ROLE_ADMIN && $stationId === null)) {
            $errors[] = 'Choose a valid police station.';
        }
    }
    if (!$isEdit && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($isEdit && $password !== '' && strlen($password) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    if ($isEdit && is_station_admin() && $target['role'] === ROLE_ADMIN) {
        $errors[] = 'Head office accounts can only be edited by head office.';
    }
    // Duplicates.
    $dupName = db_value('SELECT id FROM staff WHERE name = ?', 's', [$name]);
    if ($dupName !== null && (int) $dupName !== (int) ($target['id'] ?? 0)) {
        $errors[] = 'That username is already taken.';
    }
    if ($email !== '') {
        $dupEmail = db_value('SELECT id FROM staff WHERE email = ?', 's', [$email]);
        if ($dupEmail !== null && (int) $dupEmail !== (int) ($target['id'] ?? 0)) {
            $errors[] = 'That email address is already used by another account.';
        }
    }
    if ($cnic !== null) {
        $dupCnic = db_value('SELECT id FROM staff WHERE id_card_no = ?', 's', [$cnic]);
        if ($dupCnic !== null && (int) $dupCnic !== (int) ($target['id'] ?? 0)) {
            $errors[] = 'Another staff member already has that CNIC.';
        }
    }
    if ($isEdit && (int) $target['id'] === (int) current_user()['id'] && $role !== $target['role']) {
        $errors[] = 'You cannot change your own role.';
    }
    if ($errors) {
        foreach ($errors as $er) {
            flash('danger', $er);
        }
        keep_old($_POST);
        redirect($isEdit ? 'edit_staff.php?id=' . (int) $target['id'] : 'add_staff.php');
    }

    $stationName = $stationId ? (string) db_value('SELECT police_station_name FROM police_stations WHERE id = ?', 'i', [$stationId], '') : null;
    if ($isEdit) {
        db_exec('UPDATE staff SET name = ?, email = NULLIF(?, ""), designation = ?, role = ?, police_station_id = ?, police_station_name = ?, id_card_no = ? WHERE id = ?',
            'ssssissi', [$name, $email, $designation, $role, $stationId, $stationName, $cnic, (int) $target['id']]);
        $meta = ['role' => $role, 'station_id' => $stationId];
        if ($password !== '') {
            db_exec('UPDATE staff SET password = ?, password_changed_at = NOW() WHERE id = ?', 'si', [password_hash($password, PASSWORD_DEFAULT), (int) $target['id']]);
            invalidate_user_sessions((int) $target['id']);
            $meta['password_reset'] = true;
        }
        if ($role !== $target['role'] || $stationId !== (int) $target['police_station_id']) {
            invalidate_user_sessions((int) $target['id']); // permissions changed: force re-login
        }
        audit_log('staff.update', 'staff', (int) $target['id'], $meta);
        flash('success', 'Staff record updated.');
        clear_old();
        redirect(is_admin() ? 'view_staff.php' : 'manage_staff.php');
    }
    db_exec('INSERT INTO staff (name, email, password, designation, role, police_station_id, police_station_name, id_card_no, is_active, created_at)
             VALUES (?, NULLIF(?, ""), ?, ?, ?, ?, ?, ?, 1, NOW())',
        'sssssiss', [$name, $email, password_hash($password, PASSWORD_DEFAULT), $designation, $role, $stationId, $stationName, $cnic]);
    audit_log('staff.create', 'staff', db_insert_id(), ['role' => $role, 'station_id' => $stationId]);
    flash('success', 'Staff member added.');
    clear_old();
    redirect(is_admin() ? 'view_staff.php' : 'manage_staff.php');
}

$old = old_pull();
$val = fn(string $k, $default = '') => $old[$k] ?? ($target[$k] ?? $default);
$roleLabels = [ROLE_ADMIN => 'Head Office Admin', ROLE_STATION => 'Station Admin', ROLE_STAFF => 'Staff'];
?>
<form method="post" class="needs-validation" novalidate autocomplete="off">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="name" class="form-label required">Username</label>
            <input type="text" class="form-control" id="name" name="name" required maxlength="100" pattern="[A-Za-z0-9._ -]{3,100}" value="<?= e($val('name')) ?>">
            <div class="form-text">Used to sign in. Letters, numbers, dot, dash, underscore.</div>
        </div>
        <div class="col-md-6">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" maxlength="150" value="<?= e($val('email')) ?>">
        </div>
        <div class="col-md-6">
            <label for="designation" class="form-label required">Designation</label>
            <input type="text" class="form-control" id="designation" name="designation" required maxlength="100" value="<?= e($val('designation')) ?>" placeholder="Constable, ASI, SHO…">
        </div>
        <div class="col-md-6">
            <label for="id_card_no" class="form-label">CNIC</label>
            <input type="text" class="form-control" id="id_card_no" name="id_card_no" maxlength="20" value="<?= e($old['id_card_no'] ?? ($isEdit ? mask_cnic($target['id_card_no']) : '')) ?>" placeholder="12345-1234567-1" <?= $isEdit && !empty($target['id_card_no']) ? 'data-masked="1"' : '' ?>>
            <?php if ($isEdit && !empty($target['id_card_no'])): ?><div class="form-text">Shown masked. Type a full CNIC to replace it, or leave as is.</div><?php endif; ?>
        </div>
        <div class="col-md-6">
            <label for="role" class="form-label required">Role</label>
            <select class="form-select" id="role" name="role" required <?= $isEdit && (int) $target['id'] === (int) current_user()['id'] ? 'disabled' : '' ?>>
                <?php foreach (assignable_roles() as $r): ?>
                    <option value="<?= e($r) ?>" <?= $val('role', ROLE_STAFF) === $r ? 'selected' : '' ?>><?= e($roleLabels[$r]) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($isEdit && (int) $target['id'] === (int) current_user()['id']): ?><input type="hidden" name="role" value="<?= e($target['role']) ?>"><?php endif; ?>
        </div>
        <div class="col-md-6">
            <label for="station_id" class="form-label required">Police station</label>
            <select class="form-select" id="station_id" name="station_id" <?= is_station_admin() ? 'disabled' : '' ?>>
                <?php if (is_admin()): ?><option value="">— none (head office) —</option><?php endif; ?>
                <?php foreach (stations_for_user() as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) $val('station_id', $target['police_station_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['police_station_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (is_station_admin()): ?><input type="hidden" name="station_id" value="<?= (int) user_station_id() ?>"><div class="form-text">Station admins cannot move staff between stations.</div><?php endif; ?>
        </div>
        <div class="col-md-6">
            <label for="password" class="form-label <?= $isEdit ? '' : 'required' ?>"><?= $isEdit ? 'Set new password' : 'Password' ?></label>
            <input type="password" class="form-control" id="password" name="password" <?= $isEdit ? '' : 'required' ?> minlength="8" autocomplete="new-password">
            <div class="form-text"><?= $isEdit ? 'Leave empty to keep the current password. Setting one signs the user out everywhere.' : 'At least 8 characters. Share it with the staff member securely; they can change it in their profile.' ?></div>
        </div>
    </div>
    <div class="mt-4">
        <button type="submit" class="btn btn-navy"><?= $isEdit ? 'Save changes' : 'Add staff member' ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(app_url(is_admin() ? 'view_staff.php' : 'manage_staff.php')) ?>">Cancel</a>
    </div>
</form>
<script>
// A masked CNIC must not be re-submitted as if it were a value.
document.addEventListener('submit', function (ev) {
    var f = ev.target.querySelector('#id_card_no[data-masked]');
    if (f && f.value.indexOf('*') === 0) { f.value = ''; f.name = 'id_card_no_unchanged'; }
});
</script>
