<?php
/**
 * Shared login page used by index.php, admin_login.php and login_station_admin.php.
 * Set $loginHeading before including. Any active account can log in from any of
 * the three URLs; the user is always sent to the dashboard for their own role.
 */
declare(strict_types=1);

$loginHeading = $loginHeading ?? 'Sign in';

if (is_logged_in()) {
    redirect(home_for_role(user_role()));
}

$error = '';
if (is_post()) {
    csrf_verify();
    $username = post_str('username', 100);
    $password = (string) ($_POST['password'] ?? '');
    [$ok, $result] = attempt_login($username, $password);
    if ($ok) {
        $next = $_SESSION['_after_login'] ?? null;
        unset($_SESSION['_after_login']);
        if (is_string($next) && preg_match('#^/[^/].*#', $next) && strpos($next, 'logout') === false) {
            redirect(app_url('/') . ltrim(substr($next, strlen(parse_url(app_url('/'), PHP_URL_PATH) ?: '/')), '/'));
        }
        redirect(home_for_role($result['role']));
    }
    $error = (string) $result;
}

$pageTitle    = $loginHeading;
$layoutPublic = true;
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card auth-card">
    <div class="card-body p-4 p-md-5 text-center">
        <img src="<?= e(app_url('img/logo.jpg')) ?>" alt="AJK Police" class="logo mb-3">
        <h1 class="h4 mb-1"><?= e((string) config('app_name')) ?></h1>
        <p class="text-muted mb-4"><?= e($loginHeading) ?></p>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" class="text-start needs-validation" novalidate autocomplete="on">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required maxlength="100" autocomplete="username" autofocus>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-navy w-100">Sign in</button>
        </form>
        <div class="mt-3 small">
            <a href="<?= e(app_url('forgot_password.php')) ?>">Forgot your password?</a>
        </div>
        <div class="mt-3 small text-muted">
            Head office: <a href="<?= e(app_url('admin_login.php')) ?>">admin login</a> ·
            Station: <a href="<?= e(app_url('login_station_admin.php')) ?>">station admin login</a> ·
            <a href="<?= e(app_url('index.php')) ?>">staff login</a>
        </div>
    </div>
</div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
