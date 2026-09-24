<?php
/**
 * Password recovery, step 1. Always answers with the same generic message so
 * usernames cannot be enumerated. Rate limited per IP. The reset link is sent
 * by email; when email is not configured the request is logged privately and
 * an administrator can reset the password from the staff page instead.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('profile.php');
}

$devLink = null;
if (is_post()) {
    csrf_verify();
    $ident = post_str('identity', 150);
    $recent = (int) db_value(
        'SELECT COUNT(*) FROM password_resets WHERE requested_ip = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)',
        's',
        [client_ip()],
        0
    );
    if ($recent >= 5) {
        flash('warning', 'Too many requests. Please wait 15 minutes and try again.');
        redirect('forgot_password.php');
    }
    if ($ident !== '') {
        $user = db_one('SELECT id, name, email, is_active FROM staff WHERE (name = ? OR email = ?) AND is_active = 1 LIMIT 1', 'ss', [$ident, $ident]);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            db_exec('UPDATE password_resets SET used_at = NOW() WHERE staff_id = ? AND used_at IS NULL', 'i', [(int) $user['id']]);
            db_exec(
                'INSERT INTO password_resets (staff_id, token_hash, expires_at, requested_ip) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), ?)',
                'iss',
                [(int) $user['id'], hash('sha256', $token), client_ip()]
            );
            audit_log('password.reset_requested', 'staff', (int) $user['id'], [], ['id' => null, 'name' => null, 'role' => null]);
            $link = app_url('reset_password.php?token=' . $token);
            if (!empty($user['email'])) {
                $html = '<p>Hello ' . e($user['name']) . ',</p><p>A password reset was requested for your Police Management System account. '
                    . 'The link below is valid for 30 minutes and can be used once.</p>'
                    . '<p><a href="' . e($link) . '">' . e($link) . '</a></p><p>If you did not request this, ignore this email.</p>';
                [$sent, $err] = send_mail((string) $user['email'], (string) $user['name'], 'Password reset', $html);
                if (!$sent) {
                    error_log('Password reset email not sent for user #' . $user['id'] . ': ' . $err);
                }
            } else {
                error_log('Password reset requested for user #' . $user['id'] . ' who has no email address.');
            }
            // Local development aid: with email disabled, the link is written to a private
            // log (storage/logs/reset-links.log) so a developer can complete the flow.
            // The HTTP response stays identical for known and unknown users.
            if (config('app_env') === 'local' && !mail_enabled()) {
                $logFile = rtrim((string) config('storage_path'), '/\\') . '/logs/reset-links.log';
                @file_put_contents($logFile, '[' . now_sql() . '] user #' . $user['id'] . ' ' . $link . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        } else {
            // Uniform timing for unknown users.
            usleep(random_int(50000, 150000));
        }
    }
    flash('info', 'If an account matches, a reset link has been sent to its email address. The link expires in 30 minutes.');
    redirect('forgot_password.php');
}

$pageTitle    = 'Forgot password';
$layoutPublic = true;
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card auth-card">
    <div class="card-body p-4 p-md-5">
        <h1 class="h4 mb-3 text-center">Reset your password</h1>
        <p class="text-muted small">Enter your username or email address. If an account matches, we will email a link that is valid for 30 minutes.</p>
        <form method="post" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="identity" class="form-label">Username or email</label>
                <input type="text" class="form-control" id="identity" name="identity" required maxlength="150" autofocus>
            </div>
            <button type="submit" class="btn btn-navy w-100">Send reset link</button>
        </form>
        <div class="mt-3 small text-center"><a href="<?= e(app_url('index.php')) ?>">Back to sign in</a></div>
    </div>
</div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
