<?php
/** My duties (staff): list and calendar, read-only. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/duties.php';
require_once __DIR__ . '/includes/duty_calendar.php';
$me = require_login();
$id = (int) $me['id'];
$view = get_str('view', 10) === 'calendar' ? 'calendar' : 'list';
$show = get_str('show', 10) ?: 'upcoming';
[$calY, $calM] = calendar_month_param();

if ($view === 'calendar') {
    $rows = db_all('SELECT * FROM duties WHERE staff_id = ? AND start_time < ? AND end_time >= ? ORDER BY start_time', 'iss',
        [$id, date('Y-m-d', mktime(0, 0, 0, $calM + 1, 1, $calY)), date('Y-m-d', mktime(0, 0, 0, $calM, 1, $calY))]);
    $pg = null;
} else {
    $w = $show === 'all' ? '1=1' : ($show === 'past' ? 'end_time < NOW()' : 'end_time >= NOW() AND status = "scheduled"');
    $total = (int) db_value("SELECT COUNT(*) FROM duties WHERE staff_id = ? AND $w", 'i', [$id]);
    $pg = paginate($total, 20);
    $rows = db_all("SELECT * FROM duties WHERE staff_id = ? AND $w ORDER BY start_time " . ($show === 'past' ? 'DESC' : 'ASC') . ' LIMIT ? OFFSET ?', 'iii', [$id, $pg['per_page'], $pg['offset']]);
}
$pageTitle = 'My Duties';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <div class="btn-group btn-group-sm"><a class="btn btn-outline-secondary<?= $view === 'list' ? ' active' : '' ?>" href="?view=list">List</a><a class="btn btn-outline-secondary<?= $view === 'calendar' ? ' active' : '' ?>" href="?view=calendar">Calendar</a></div>
    <?php if ($view === 'list'): ?>
    <div class="btn-group btn-group-sm"><?php foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All'] as $k => $v): ?><a class="btn btn-outline-secondary<?= $show === $k ? ' active' : '' ?>" href="?view=list&show=<?= $k ?>"><?= $v ?></a><?php endforeach; ?></div>
    <?php endif; ?>
</div>
<div class="card"><div class="card-body">
<?php if ($view === 'calendar'): ?>
    <?= duty_calendar_html($rows, $calY, $calM, null) ?>
<?php elseif (!$rows): ?>
    <div class="empty-state"><i class="fa-regular fa-calendar"></i><div>No <?= e($show === 'all' ? '' : $show) ?> duties.</div></div>
<?php else: ?>
    <div class="table-wrap"><table class="table table-hover align-middle">
        <thead><tr><th>Duty</th><th>Start</th><th>End</th><th>Shift</th><th>Location</th><th>Station</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr><td class="fw-semibold"><?= e($r['duty_description']) ?></td><td class="text-nowrap"><?= fmt_datetime($r['start_time']) ?></td><td class="text-nowrap"><?= fmt_datetime($r['end_time']) ?></td><td><?= e(ucfirst((string) $r['shift_type'])) ?></td><td><?= e($r['Duty_location']) ?><?= $r['checkpoint_id'] ? ' (CP ' . (int) $r['checkpoint_id'] . ')' : '' ?></td><td class="small"><?= e($r['police_station_name']) ?></td><td><?= duty_status_badge($r) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <div class="d-flex justify-content-end"><?= pagination_html($pg) ?></div>
<?php endif; ?>
</div></div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
