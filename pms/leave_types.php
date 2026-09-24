<?php
/** Head office: configurable leave types and yearly allowances (demonstration defaults, not official policy). */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role([ROLE_ADMIN]);

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 20);
    $id = post_int('id', 0) ?? 0;
    if ($action === 'save') {
        $name = post_str('name', 50);
        $allow = post_str('annual_allowance', 5);
        $allowance = $allow === '' ? null : (preg_match('/^\d{1,3}$/', $allow) ? (int) $allow : -1);
        if ($name === '' || $allowance === -1) {
            flash('danger', 'Name is required and allowance must be a whole number of days (or empty for no limit).');
        } else {
            $dup = db_value('SELECT id FROM leave_types WHERE name = ?', 's', [$name]);
            if ($dup !== null && (int) $dup !== $id) {
                flash('danger', 'A leave type with that name already exists.');
            } elseif ($id > 0) {
                db_exec('UPDATE leave_types SET name = ?, annual_allowance = ? WHERE id = ?', 'sii', [$name, $allowance, $id]);
                audit_log('leave_type.update', 'leave_type', $id, ['name' => $name, 'allowance' => $allowance]);
                flash('success', 'Leave type updated.');
            } else {
                db_exec('INSERT INTO leave_types (name, annual_allowance) VALUES (?, ?)', 'si', [$name, $allowance]);
                audit_log('leave_type.create', 'leave_type', db_insert_id(), ['name' => $name, 'allowance' => $allowance]);
                flash('success', 'Leave type added.');
            }
        }
    } elseif ($action === 'toggle' && $id > 0) {
        db_exec('UPDATE leave_types SET is_active = 1 - is_active WHERE id = ?', 'i', [$id]);
        audit_log('leave_type.toggle', 'leave_type', $id);
        flash('success', 'Leave type updated.');
    }
    redirect('leave_types.php');
}
$rows = db_all('SELECT lt.*, (SELECT COUNT(*) FROM leave_requests lr WHERE lr.leave_type_id = lt.id) AS used FROM leave_types lt ORDER BY lt.name');
$editing = get_int('edit', 0) ? db_one('SELECT * FROM leave_types WHERE id = ?', 'i', [get_int('edit', 0)]) : null;

$pageTitle = 'Leave Types';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3"><?= $editing ? 'Edit leave type' : 'New leave type' ?></h2>
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                <div class="mb-3"><label for="name" class="form-label required">Name</label><input class="form-control" id="name" name="name" required maxlength="50" value="<?= e($editing['name'] ?? '') ?>"></div>
                <div class="mb-3"><label for="annual_allowance" class="form-label">Days per year</label><input class="form-control" id="annual_allowance" name="annual_allowance" inputmode="numeric" pattern="\d{0,3}" value="<?= e($editing['annual_allowance'] ?? '') ?>"><div class="form-text">Empty = no limit enforced.</div></div>
                <button class="btn btn-navy"><?= $editing ? 'Save' : 'Add' ?></button>
                <?php if ($editing): ?><a class="btn btn-outline-secondary" href="<?= e(app_url('leave_types.php')) ?>">Cancel</a><?php endif; ?>
            </form>
            <p class="small text-muted mt-3 mb-0">The shipped values (Casual 10, Sick 8, Annual 15) are demonstration defaults, not an official police leave policy.</p>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card"><div class="card-body">
            <table class="table align-middle">
                <thead><tr><th>Type</th><th class="text-end">Days / year</th><th class="text-end">Requests</th><th>State</th><th></th></tr></thead>
                <tbody><?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['name']) ?></td>
                        <td class="text-end"><?= $r['annual_allowance'] === null ? 'No limit' : (int) $r['annual_allowance'] ?></td>
                        <td class="text-end"><?= (int) $r['used'] ?></td>
                        <td><?= status_badge($r['is_active'] ? 'active' : 'inactive') ?></td>
                        <td class="text-end table-actions">
                            <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int) $r['id'] ?>">Edit</a>
                            <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-secondary"><?= $r['is_active'] ? 'Disable' : 'Enable' ?></button></form>
                        </td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table>
        </div></div>
    </div>
</div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
