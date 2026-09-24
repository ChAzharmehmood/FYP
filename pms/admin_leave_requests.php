<?php
/** Review leave requests (station admin: own station; head office: all). */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/leave.php';
$me = require_role([ROLE_STATION, ROLE_ADMIN]);

if (is_post()) {
    csrf_verify();
    $lr = leave_find(post_int('leave_id', 0) ?? 0);
    if (!$lr || !can_view_leave($lr)) {
        not_found('Leave request not found.');
    }
    $err = leave_review($lr, post_str('action', 10), post_int('approved_days'), post_str('reason', 500));
    flash($err ? 'danger' : 'success', $err ?? 'Leave request ' . post_str('action', 10) . 'd.');
    redirect('admin_leave_requests.php?' . http_build_query(array_filter(['status' => get_str('status', 10), 'page' => get_int('page')])));
}

$status = get_str('status', 10) ?: 'pending';
$staffId = get_int('staff', 0) ?? 0;
[$scopeSql, $scopeTypes, $scopeParams] = station_scope('s.police_station_id');
$where = [$scopeSql];
$types = $scopeTypes;
$params = $scopeParams;
if (in_array($status, ['pending', 'approved', 'rejected', 'withdrawn'], true)) {
    $where[] = 'lr.status = ?';
    $types .= 's';
    $params[] = $status;
}
if ($staffId) {
    $where[] = 'lr.staff_id = ?';
    $types .= 'i';
    $params[] = $staffId;
}
$w = implode(' AND ', $where);
$base = 'FROM leave_requests lr JOIN staff s ON s.id = lr.staff_id LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id LEFT JOIN police_stations ps ON ps.id = s.police_station_id LEFT JOIN staff rv ON rv.id = lr.reviewed_by';
$total = (int) db_value("SELECT COUNT(*) $base WHERE $w", $types, $params);
$pg = paginate($total, 20);
$rows = db_all("SELECT lr.*, s.name AS staff_name, s.designation, s.police_station_id, ps.police_station_name AS station_name, lt.name AS type_name, lt.annual_allowance, rv.name AS reviewed_by_name
                $base WHERE $w ORDER BY " . ($status === 'pending' ? 'lr.created_at ASC' : 'lr.reviewed_at DESC, lr.id DESC') . ' LIMIT ? OFFSET ?', $types . 'ii', array_merge($params, [$pg['per_page'], $pg['offset']]));
if (get_str('export') === 'csv') {
    audit_log('leave.export', 'leave_request', null, ['rows' => count($rows)]);
    csv_download('leave_requests.csv', ['#', 'Staff', 'Station', 'Type', 'From', 'To', 'Requested', 'Approved', 'Status', 'Reviewed by', 'Reason'],
        array_map(fn($r) => [$r['id'], $r['staff_name'], $r['station_name'], $r['type_name'] ?? $r['leave_type'], $r['leave_start_date'], $r['leave_end_date'], $r['requested_days'], $r['approved_days'], $r['status'], $r['reviewed_by_name'], $r['review_reason']], $rows));
}
$staffOptions = is_admin() ? db_all('SELECT id, name FROM staff WHERE role <> "admin" ORDER BY name') : db_all('SELECT id, name FROM staff WHERE police_station_id = ? ORDER BY name', 'i', [(int) user_station_id()]);

$pageTitle = 'Leave Requests';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card mb-3"><div class="card-body">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3"><label for="status" class="form-label">Status</label><select class="form-select" id="status" name="status"><?php foreach (['pending', 'approved', 'rejected', 'withdrawn', 'all'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label for="staff" class="form-label">Staff</label><select class="form-select" id="staff" name="staff"><option value="">All</option><?php foreach ($staffOptions as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $staffId === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-navy">Filter</button><a class="btn btn-outline-secondary" href="<?= e(app_url('admin_leave_requests.php')) ?>">Reset</a></div>
        <div class="col-md-3 text-md-end small"><a href="<?= e(query_link(['export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv"></i> Export CSV</a><?php if (is_admin()): ?> · <a href="<?= e(app_url('leave_types.php')) ?>">Leave types</a><?php endif; ?></div>
    </form>
</div></div>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2"><span class="text-muted small"><?= $total ?> request(s)</span><?= pagination_html($pg) ?></div>
    <?php if (!$rows): ?><div class="empty-state"><i class="fa-regular fa-calendar-check"></i><div>No <?= e($status === 'all' ? '' : $status) ?> leave requests.</div></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table align-middle">
        <thead><tr><th>Staff</th><th>Type</th><th>Dates</th><th class="text-end">Days</th><th>Balance</th><th>Reason</th><th>Status</th><th style="min-width:260px"></th></tr></thead>
        <tbody><?php foreach ($rows as $r):
            $year = (int) date('Y', strtotime($r['leave_start_date']));
            $used = $r['leave_type_id'] ? leave_used_days((int) $r['staff_id'], (int) $r['leave_type_id'], $year) : 0;
            $left = $r['annual_allowance'] === null ? null : max(0, (int) $r['annual_allowance'] - $used);
        ?>
            <tr>
                <td class="fw-semibold"><?= e($r['staff_name']) ?><br><span class="small text-muted"><?= e($r['designation']) ?><?= is_admin() ? ' · ' . e($r['station_name'] ?? '') : '' ?></span></td>
                <td><?= e($r['type_name'] ?? $r['leave_type']) ?></td>
                <td class="small text-nowrap"><?= fmt_date($r['leave_start_date']) ?><br>to <?= fmt_date($r['leave_end_date']) ?></td>
                <td class="text-end"><?= (int) $r['requested_days'] ?><?= $r['status'] === 'approved' ? '<br><span class="small text-success">approved ' . (int) $r['approved_days'] . '</span>' : '' ?></td>
                <td class="small"><?= $left === null ? 'No limit' : "$left left of " . (int) $r['annual_allowance'] . " in $year" ?></td>
                <td class="small" style="max-width:220px"><?= e(mb_strimwidth((string) $r['reason'], 0, 140, '…')) ?></td>
                <td><?= status_badge($r['status']) ?><?= $r['reviewed_by_name'] ? '<br><span class="small text-muted">' . e($r['reviewed_by_name']) . ' · ' . fmt_datetime($r['reviewed_at']) . '</span>' : '' ?><?= $r['review_reason'] ? '<br><em class="small">' . e($r['review_reason']) . '</em>' : '' ?></td>
                <td>
                    <?php if ($r['status'] === 'pending' && can_review_leave($r)): ?>
                    <form method="post" class="d-flex flex-column gap-1">
                        <?= csrf_field() ?><input type="hidden" name="leave_id" value="<?= (int) $r['id'] ?>">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Days</span>
                            <input type="number" class="form-control" name="approved_days" min="1" max="<?= (int) $r['requested_days'] ?>" value="<?= (int) $r['requested_days'] ?>" aria-label="Approved days">
                            <button class="btn btn-success" name="action" value="approve">Approve</button>
                        </div>
                        <div class="input-group input-group-sm">
                            <input class="form-control" name="reason" maxlength="500" placeholder="Reason (required to reject)" aria-label="Reason">
                            <button class="btn btn-outline-danger" name="action" value="reject">Reject</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</div></div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
