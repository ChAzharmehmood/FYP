<?php
/**
 * Logout. The navigation posts here with a CSRF token. A plain GET (old
 * bookmarks/links) shows a one-click confirmation instead of logging out
 * directly, so a third-party page cannot end a session by embedding the URL.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    csrf_verify();
    logout_user();
    session_boot();
    flash('success', 'You have been logged out.');
    redirect('index.php');
}

if (!is_logged_in()) {
    redirect('index.php');
}

$pageTitle    = 'Log out';
$layoutPublic = true;
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card auth-card">
    <div class="card-body p-4 text-center">
        <h1 class="h5 mb-3">Log out of the Police Management System?</h1>
        <form method="post" class="d-flex gap-2 justify-content-center">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-navy">Log out</button>
            <a class="btn btn-outline-secondary" href="<?= e(app_url(home_for_role(user_role()))) ?>">Cancel</a>
        </form>
    </div>
</div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
