<?php
/**
 * Shared staff list for view_staff.php (head office) and manage_staff.php (station admin).
 * Handles deactivate / reactivate actions (POST + CSRF) and CSV export.
 */
declare(strict_types=1);

$selfPage = basename((string) $_SERVER['SCRIPT_NAME']);

if (is_post()) {
    csrf_verify();
    $id = post_int('id', 0) ?? 0;
    $t  = db_one('SELECT * FROM staff WHERE id = ?', 'i', [$id]);
    if (!$t) {
        not_found('Staff member not found.');
    }
    $action = post_str('action', 20);
    if ($action === 'deactivate' || $action === 'activate') {
        if (!can_deactivate_staff($t)) {
            deny('You may not change that account.');
        }
        $active = $action === 'activate' ? 1 : 0;
        db_exec('UPDATE staff SET is_active = ?, session_version = session_version + 1 WHERE id = ?', 'ii', [$active, $id]);
        audit_log('staff.' . $action, 'staff', $id);
        flash('success', $t['name'] . ' has been ' . $action . 'd.');
    }
    redirect($selfPage);
}

$q       = get_str('q', 100);
$station = get_int('station', 0) ?? 0;
$role    = get_str('role', 20);
$showAll = get_str('show', 10) === 'all';
$where   = [];
$types   = '';
$params  = [];
[$scopeSql, $scopeTypes, $scopeParams] = station_scope('s.police_station_id');
$where[] = $scopeSql;
$types  .= $scopeTypes;
$params  = array_merge($params, $scopeParams);
if (!$showAll) {
    $where[] = 's.is_active = 1';
}
if ($q !== '') {
    $where[] = '(s.name LIKE ? OR s.designation LIKE ? OR s.email LIKE ?)';
    $types .= 'sss';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($station && is_admin()) {
    $where[] = 's.police_station_id = ?';
    $types .= 'i';
    $params[] = $station;
}
if (in_array($role, ROLES, true)) {
    $where[] = 's.role = ?';
    $types .= 's';
    $params[] = $role;
}
$w = implode(' AND ', $where);
[$sortKey, $sortDir, $orderSql] = db_order_by(get_str('sort'), ['name' => 's.name', 'designation' => 's.designation', 'role' => 's.role', 'station' => 'ps.police_station_name', 'login' => 's.last_login_at'], 'name', get_str('dir'));
$total = (int) db_value("SELECT COUNT(*) FROM staff s LEFT JOIN police_stations ps ON ps.id = s.police_station_id WHERE $w", $types, $params);
$pg    = paginate($total, 20);
$rows  = db_all(
    "SELECT s.*, ps.police_station_name AS station_name FROM staff s LEFT JOIN police_stations ps ON ps.id = s.police_station_id
     WHERE $w ORDER BY $orderSql LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$pg['per_page'], $pg['offset']])
);
if (get_str('export') === 'csv') {
    audit_log('staff.export', 'staff', null, ['rows' => count($rows)]);
    csv_download('staff.csv', ['Username', 'Designation', 'Role', 'Station', 'Email', 'CNIC (masked)', 'Active', 'Last login'],
        array_map(fn($r) => [$r['name'], $r['designation'], role_label($r['role']), $r['station_name'] ?? $r['police_station_name'], $r['email'], mask_cnic($r['id_card_no']), $r['is_active'] ? 'Yes' : 'No', $r['last_login_at']], $rows));
}
$sortLink = function (string $key, string $label) use ($sortKey, $sortDir): string {
    $dir = ($sortKey === $key && $sortDir === 'ASC') ? 'desc' : 'asc';
    $icon = $sortKey === $key ? ($sortDir === 'ASC' ? ' ▲' : ' ▼') : '';
    return '<a class="text-reset text-decoration-none" href="' . e(query_link(['sort' => $key, 'dir' => $dir])) . '">' . e($label) . $icon . '</a>';
};
?>
<div class="card mb-3"><div class="card-body">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label for="q" class="form-label">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="Name, designation or email">
        </div>
        <?php if (is_admin()): ?>
        <div class="col-md-3">
            <label for="station" class="form-label">Station</label>
            <select class="form-select" id="station" name="station">
                <option value="">All</option>
                <?php foreach (stations_all(false) as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $station === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['police_station_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-md-2">
            <label for="role" class="form-label">Role</label>
            <select class="form-select" id="role" name="role">
                <option value="">All</option>
                <?php foreach (ROLES as $r): ?><option value="<?= e($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-navy" type="submit">Filter</button>
            <a class="btn btn-outline-secondary" href="<?= e(app_url($selfPage)) ?>">Reset</a>
        </div>
        <div class="col-12 d-flex gap-3 small">
            <div class="form-check"><input class="form-check-input" type="checkbox" id="show" name="show" value="all" <?= $showAll ? 'checked' : '' ?> onchange="this.form.submit()"><label class="form-check-label" for="show">Include deactivated</label></div>
            <a href="<?= e(query_link(['export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
            <a href="<?= e(app_url('add_staff.php')) ?>"><i class="fa-solid fa-user-plus"></i> Add staff</a>
        </div>
    </form>
</div></div>

<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-muted small"><?= $total ?> staff member(s)</span>
        <?= pagination_html($pg) ?>
    </div>
    <?php if (!$rows): ?>
        <div class="empty-state"><i class="fa-regular fa-user"></i><div>No staff match these filters.</div></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table table-hover align-middle">
        <thead><tr><th><?= $sortLink('name', 'Name') ?></th><th><?= $sortLink('designation', 'Designation') ?></th><th><?= $sortLink('role', 'Role') ?></th><?php if (is_admin()): ?><th><?= $sortLink('station', 'Station') ?></th><?php endif; ?><th>Email</th><th>CNIC</th><th><?= $sortLink('login', 'Last login') ?></th><th>State</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="fw-semibold"><?= e($r['name']) ?></td>
                <td><?= e($r['designation']) ?></td>
                <td><?= e(role_label($r['role'])) ?></td>
                <?php if (is_admin()): ?><td><?= e($r['station_name'] ?? $r['police_station_name'] ?? '—') ?></td><?php endif; ?>
                <td class="small"><?= e($r['email'] ?? '') ?></td>
                <td class="small font-monospace"><?= e(mask_cnic($r['id_card_no'])) ?></td>
                <td class="small"><?= fmt_datetime($r['last_login_at']) ?: '—' ?></td>
                <td><?= status_badge($r['is_active'] ? 'active' : 'inactive') ?></td>
                <td class="table-actions text-end">
                    <?php if (can_edit_staff($r)): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('edit_staff.php?id=' . (int) $r['id'])) ?>">Edit</a>
                    <?php endif; ?>
                    <?php if (can_deactivate_staff($r)): ?>
                        <form method="post" class="d-inline" data-confirm="<?= $r['is_active'] ? 'Deactivate this account? The person will be signed out and cannot log in until reactivated. Their records are kept.' : 'Reactivate this account?' ?>">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button class="btn btn-sm btn-outline-<?= $r['is_active'] ? 'secondary' : 'success' ?>" name="action" value="<?= $r['is_active'] ? 'deactivate' : 'activate' ?>"><?= $r['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div></div>
