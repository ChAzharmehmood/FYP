<?php
/** Duties of my station (station admin) or all stations (head office): list + calendar. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/duties.php';
require_once __DIR__ . '/includes/duty_calendar.php';
$me = require_role([ROLE_STATION, ROLE_ADMIN]);

$view    = get_str('view', 10) === 'calendar' ? 'calendar' : 'list';
$status  = get_str('status', 10);
$notify  = get_str('notify', 10);
$staffId = get_int('staff', 0) ?? 0;
$station = get_int('station', 0) ?? 0;
$from    = get_str('from', 10);
$to      = get_str('to', 10);
[$scopeSql, $scopeTypes, $scopeParams] = station_scope('d.police_station_id');
$where = [$scopeSql];
$types = $scopeTypes;
$params = $scopeParams;
if (in_array($status, ['scheduled', 'completed', 'cancelled'], true)) {
    $where[] = 'd.status = ?';
    $types .= 's';
    $params[] = $status;
}
if (in_array($notify, ['not_sent', 'sent', 'failed'], true)) {
    $where[] = 'd.notify_status = ?';
    $types .= 's';
    $params[] = $notify;
}
if ($staffId) {
    $where[] = 'd.staff_id = ?';
    $types .= 'i';
    $params[] = $staffId;
}
if ($station && is_admin()) {
    $where[] = 'd.police_station_id = ?';
    $types .= 'i';
    $params[] = $station;
}
[$calY, $calM] = calendar_month_param();
if ($view === 'calendar') {
    $where[] = 'd.start_time < ? AND d.end_time >= ?';
    $types .= 'ss';
    $params[] = date('Y-m-d', mktime(0, 0, 0, $calM + 1, 1, $calY));
    $params[] = date('Y-m-d', mktime(0, 0, 0, $calM, 1, $calY));
} else {
    if ($from !== '' && valid_date($from)) {
        $where[] = 'd.end_time >= ?';
        $types .= 's';
        $params[] = $from . ' 00:00:00';
    }
    if ($to !== '' && valid_date($to)) {
        $where[] = 'd.start_time <= ?';
        $types .= 's';
        $params[] = $to . ' 23:59:59';
    }
}
$w = implode(' AND ', $where);
[$sortKey, $sortDir, $orderSql] = db_order_by(get_str('sort'), ['start' => 'd.start_time', 'staff' => 's.name', 'status' => 'd.status', 'notify' => 'd.notify_status'], 'start', get_str('dir') ?: 'desc');
$base = 'FROM duties d JOIN staff s ON s.id = d.staff_id LEFT JOIN police_stations ps ON ps.id = d.police_station_id';
$total = (int) db_value("SELECT COUNT(*) $base WHERE $w", $types, $params);
$pg = $view === 'calendar' ? ['page' => 1, 'per_page' => 500, 'offset' => 0, 'pages' => 1, 'total' => $total] : paginate($total, 20);
$rows = db_all("SELECT d.*, s.name AS staff_name, s.designation, ps.police_station_name AS station_name $base WHERE $w ORDER BY $orderSql LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [$pg['per_page'], $pg['offset']]));
if (get_str('export') === 'csv') {
    audit_log('duty.export', 'duty', null, ['rows' => count($rows)]);
    csv_download('duties.csv', ['Staff', 'Designation', 'Station', 'Duty', 'Start', 'End', 'Shift', 'Location', 'Checkpoint', 'Status', 'Email'],
        array_map(fn($r) => [$r['staff_name'], $r['designation'], $r['station_name'] ?? $r['police_station_name'], $r['duty_description'], $r['start_time'], $r['end_time'], $r['shift_type'], $r['Duty_location'], $r['checkpoint_id'], $r['status'], $r['notify_status']], $rows));
}
$staffOptions = is_admin() ? db_all('SELECT id, name FROM staff WHERE role <> "admin" ORDER BY name') : db_all('SELECT id, name FROM staff WHERE police_station_id = ? ORDER BY name', 'i', [(int) user_station_id()]);
$sortLink = function (string $key, string $label) use ($sortKey, $sortDir): string {
    $dir = ($sortKey === $key && $sortDir === 'ASC') ? 'desc' : 'asc';
    $icon = $sortKey === $key ? ($sortDir === 'ASC' ? ' ▲' : ' ▼') : '';
    return '<a class="text-reset text-decoration-none" href="' . e(query_link(['sort' => $key, 'dir' => $dir])) . '">' . e($label) . $icon . '</a>';
};

$pageTitle = 'Duties';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card mb-3"><div class="card-body">
    <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <?php if ($view === 'calendar'): ?><input type="hidden" name="month" value="<?= e(get_str('month', 7)) ?>"><?php endif; ?>
        <div class="col-md-2"><label for="staff" class="form-label">Staff</label><select class="form-select" id="staff" name="staff"><option value="">All</option><?php foreach ($staffOptions as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $staffId === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
        <?php if (is_admin()): ?><div class="col-md-2"><label for="station" class="form-label">Station</label><select class="form-select" id="station" name="station"><option value="">All</option><?php foreach (stations_all(false) as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $station === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['police_station_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <div class="col-md-2"><label for="status" class="form-label">Status</label><select class="form-select" id="status" name="status"><option value="">All</option><?php foreach (['scheduled', 'completed', 'cancelled'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label for="notify" class="form-label">Email</label><select class="form-select" id="notify" name="notify"><option value="">All</option><?php foreach (['not_sent' => 'Not sent', 'sent' => 'Sent', 'failed' => 'Failed'] as $k => $v): ?><option value="<?= $k ?>" <?= $notify === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
        <?php if ($view === 'list'): ?>
        <div class="col-md-2"><label for="from" class="form-label">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>"></div>
        <div class="col-md-2"><label for="to" class="form-label">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>"></div>
        <?php endif; ?>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-navy">Filter</button><a class="btn btn-outline-secondary" href="<?= e(app_url('view_duties.php')) ?>">Reset</a></div>
        <div class="col-12 d-flex gap-3 small align-items-center">
            <div class="btn-group btn-group-sm"><a class="btn btn-outline-secondary<?= $view === 'list' ? ' active' : '' ?>" href="<?= e(query_link(['view' => 'list'])) ?>">List</a><a class="btn btn-outline-secondary<?= $view === 'calendar' ? ' active' : '' ?>" href="<?= e(query_link(['view' => 'calendar'])) ?>">Calendar</a></div>
            <a href="<?= e(app_url('assign_duties.php')) ?>"><i class="fa-solid fa-user-clock"></i> Assign duty</a>
            <a href="<?= e(query_link(['export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        </div>
    </form>
</div></div>

<div class="card"><div class="card-body">
<?php if ($view === 'calendar'): ?>
    <?= duty_calendar_html($rows, $calY, $calM) ?>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-2"><span class="text-muted small"><?= $total ?> duty(ies)</span><?= pagination_html($pg) ?></div>
    <?php if (!$rows): ?><div class="empty-state"><i class="fa-regular fa-calendar"></i><div>No duties match.</div></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table table-hover align-middle">
        <thead><tr><th><?= $sortLink('staff', 'Staff') ?></th><?php if (is_admin()): ?><th>Station</th><?php endif; ?><th>Duty</th><th><?= $sortLink('start', 'Start') ?></th><th>End</th><th>Shift</th><th>Location</th><th><?= $sortLink('status', 'Status') ?></th><th><?= $sortLink('notify', 'Email') ?></th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr>
                <td class="fw-semibold"><?= e($r['staff_name']) ?><br><span class="small text-muted"><?= e($r['designation']) ?></span></td>
                <?php if (is_admin()): ?><td class="small"><?= e($r['station_name'] ?? $r['police_station_name']) ?></td><?php endif; ?>
                <td><?= e($r['duty_description']) ?></td>
                <td class="text-nowrap small"><?= fmt_datetime($r['start_time']) ?></td>
                <td class="text-nowrap small"><?= fmt_datetime($r['end_time']) ?></td>
                <td><?= e(ucfirst((string) $r['shift_type'])) ?></td>
                <td class="small"><?= e($r['Duty_location']) ?><?= $r['checkpoint_id'] ? ' (CP ' . (int) $r['checkpoint_id'] . ')' : '' ?></td>
                <td><?= duty_status_badge($r) ?></td>
                <td><?= status_badge($r['notify_status'] === 'not_sent' ? 'pending' : $r['notify_status']) ?></td>
                <td class="table-actions text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('edit_duty.php?id=' . (int) $r['id'])) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
<?php endif; ?>
</div></div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
