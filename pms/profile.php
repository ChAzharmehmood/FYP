<?php
/** My profile: view account details, edit contact email, change password. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_login();

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 30);
    if ($action === 'email') {
        $email = post_str('email', 150);
        if ($email !== '' && !valid_email($email)) {
            flash('danger', 'Please enter a valid email address.');
        } else {
            $dup = db_value('SELECT COUNT(*) FROM staff WHERE email = ? AND id <> ?', 'si', [$email, (int) $me['id']], 0);
            if ($email !== '' && (int) $dup > 0) {
                flash('danger', 'That email address is already used by another account.');
            } else {
                db_exec('UPDATE staff SET email = NULLIF(?, "") WHERE id = ?', 'si', [$email, (int) $me['id']]);
                audit_log('profile.email_changed', 'staff', (int) $me['id']);
                flash('success', 'Email address updated.');
            }
        }
        redirect('profile.php');
    }
    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $hash    = (string) db_value('SELECT password FROM staff WHERE id = ?', 'i', [(int) $me['id']], '');
        if (!password_verify($current, $hash)) {
            audit_log('password.change_failed', 'staff', (int) $me['id']);
            flash('danger', 'Your current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('danger', 'The new password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            flash('danger', 'The new passwords do not match.');
        } elseif (password_verify($new, $hash)) {
            flash('danger', 'The new password must be different from the current one.');
        } else {
            db_exec('UPDATE staff SET password = ?, password_changed_at = NOW() WHERE id = ?', 'si', [password_hash($new, PASSWORD_DEFAULT), (int) $me['id']]);
            invalidate_user_sessions((int) $me['id']);
            audit_log('password.changed', 'staff', (int) $me['id']);
            flash('success', 'Password changed. Other devices have been signed out.');
        }
        redirect('profile.php');
    }
}

$pageTitle = 'My profile';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-muted mb-3">Account</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Username</dt><dd class="col-sm-8"><?= e($me['name']) ?></dd>
                    <dt class="col-sm-4">Role</dt><dd class="col-sm-8"><?= e(role_label($me['role'])) ?></dd>
                    <dt class="col-sm-4">Designation</dt><dd class="col-sm-8"><?= e($me['designation'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Police station</dt><dd class="col-sm-8"><?= e($me['station_name'] ?: '—') ?></dd>
                    <dt class="col-sm-4">CNIC</dt><dd class="col-sm-8"><?= e(mask_cnic($me['id_card_no'])) ?: '—' ?></dd>
                    <dt class="col-sm-4">Last login</dt><dd class="col-sm-8"><?= fmt_datetime($me['last_login_at']) ?: '—' ?></dd>
                </dl>
                <hr>
                <form method="post" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="email">
                    <label for="email" class="form-label">Contact email</label>
                    <div class="input-group">
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($me['email']) ?>" maxlength="150">
                        <button class="btn btn-outline-primary" type="submit">Save</button>
                    </div>
                    <div class="form-text">Used for duty notifications and password recovery.</div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-muted mb-3">Change password</h2>
                <form method="post" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="mb-3">
                        <label for="current_password" class="form-label required">Current password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label required">New password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                        <div class="form-text">At least 8 characters.</div>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label required">Confirm new password</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-navy">Change password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
