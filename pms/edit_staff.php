<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role([ROLE_ADMIN, ROLE_STATION]);
$target = db_one('SELECT * FROM staff WHERE id = ?', 'i', [get_int('id', 0) ?? 0]);
if (!$target || !can_edit_staff($target)) {
    not_found('Staff member not found.');
}
ob_start();
require PMS_ROOT . '/includes/staff_form.php';
$formHtml = ob_get_clean();
$pageTitle = 'Edit Staff: ' . $target['name'];
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card" style="max-width: 860px"><div class="card-body">
    <div class="d-flex justify-content-between mb-3">
        <div><?= status_badge($target['is_active'] ? 'active' : 'inactive') ?> <span class="text-muted small">Last login: <?= fmt_datetime($target['last_login_at']) ?: 'never' ?></span></div>
    </div>
    <?= $formHtml ?>
</div></div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
