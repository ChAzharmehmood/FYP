<?php
/** Password recovery, step 2: single-use, expiring token. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$token = is_post() ? (string) ($_POST['token'] ?? '') : get_str('token', 128);
$valid = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
$reset = null;
if ($valid) {
    $reset = db_one(
        'SELECT pr.id, pr.staff_id, s.name FROM password_resets pr JOIN staff s ON s.id = pr.staff_id
         WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() AND s.is_active = 1 LIMIT 1',
        's',
        [hash('sha256', $token)]
    );
}
if (!$reset) {
    flash('danger', 'This reset link is invalid, expired, or already used. Request a new one.');
    redirect('forgot_password.php');
}

$errors = [];
if (is_post()) {
    csrf_verify();
    $p1 = (string) ($_POST['password'] ?? '');
    $p2 = (string) ($_POST['password_confirm'] ?? '');
    if (strlen($p1) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($p1 !== $p2) {
        $errors[] = 'The two passwords do not match.';
    }
    if (!$errors) {
        db_begin();
        $used = db_exec('UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL', 'i', [(int) $reset['id']]);
        if ($used !== 1) {
            db_rollback();
            flash('danger', 'This reset link was already used.');
            redirect('forgot_password.php');
        }
        db_exec(
            'UPDATE staff SET password = ?, password_changed_at = NOW(), session_version = session_version + 1 WHERE id = ?',
            'si',
            [password_hash($p1, PASSWORD_DEFAULT), (int) $reset['staff_id']]
        );
        db_commit();
        audit_log('password.reset_completed', 'staff', (int) $reset['staff_id'], [], ['id' => $reset['staff_id'], 'name' => $reset['name'], 'role' => null]);
        flash('success', 'Your password has been changed. Please sign in.');
        redirect('index.php');
    }
}

$pageTitle    = 'Choose a new password';
$layoutPublic = true;
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card auth-card">
    <div class="card-body p-4 p-md-5">
        <h1 class="h4 mb-3 text-center">Choose a new password</h1>
        <p class="text-muted small text-center">Account: <strong><?= e($reset['name']) ?></strong></p>
        <?php foreach ($errors as $er): ?>
            <div class="alert alert-danger py-2"><?= e($er) ?></div>
        <?php endforeach; ?>
        <form method="post" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="mb-3">
                <label for="password" class="form-label">New password</label>
                <input type="password" class="form-control" id="password" name="password" required minlength="8" autocomplete="new-password">
            </div>
            <div class="mb-3">
                <label for="password_confirm" class="form-label">Confirm new password</label>
                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-navy w-100">Change password</button>
        </form>
    </div>
</div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
