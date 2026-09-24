<?php
/** Staff dashboard: my duties, my leave, station alerts. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/alerts.php';
require_once __DIR__ . '/includes/leave.php';
$me = require_role([ROLE_STAFF, ROLE_STATION, ROLE_ADMIN]);
$id = (int) $me['id'];

$nextDuties = db_all('SELECT id, duty_description, start_time, end_time, shift_type, Duty_location, status FROM duties
                      WHERE staff_id = ? AND status = "scheduled" AND end_time >= NOW() ORDER BY start_time LIMIT 5', 'i', [$id]);
$myLeave    = db_all('SELECT lr.id, lr.leave_start_date, lr.leave_end_date, lr.requested_days, lr.approved_days, lr.status, lt.name AS type_name
                      FROM leave_requests lr LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id WHERE lr.staff_id = ? ORDER BY lr.id DESC LIMIT 5', 'i', [$id]);
$balances = [];
foreach (leave_types_active() as $t) {
    [$allow, $used, $rem] = leave_balance($id, $t, (int) date('Y'));
    $balances[] = ['name' => $t['name'], 'allow' => $allow, 'used' => $used, 'rem' => $rem];
}
$stationReports = user_station_id() !== null
    ? (int) db_value('SELECT COUNT(*) FROM reports WHERE police_station_id = ? AND status IN ("Open","Under Investigation")', 'i', [user_station_id()])
    : 0;

$pageTitle = 'My Dashboard';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a class="card stat-card text-decoration-none text-reset" href="<?= e(app_url('view_staff_duty.php')) ?>">
            <div class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></div>
            <div><div class="stat-value"><?= count($nextDuties) ?></div><div class="stat-label">Upcoming duties</div></div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a class="card stat-card text-decoration-none text-reset" href="<?= e(app_url('leave_status.php')) ?>">
            <div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
            <div><div class="stat-value"><?= count(array_filter($myLeave, fn($l) => $l['status'] === 'pending')) ?></div><div class="stat-label">Pending leave</div></div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a class="card stat-card text-decoration-none text-reset" href="<?= e(app_url('view_reports.php')) ?>">
            <div class="stat-icon"><i class="fa-solid fa-file-lines"></i></div>
            <div><div class="stat-value"><?= $stationReports ?></div><div class="stat-label">Open station cases</div></div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a class="card stat-card text-decoration-none text-reset" href="<?= e(app_url('generate_report.php')) ?>">
            <div class="stat-icon"><i class="fa-solid fa-file-circle-plus"></i></div>
            <div><div class="stat-value">+</div><div class="stat-label">File a report</div></div>
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4"><?= alerts_panel_html() ?></div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Next duties</h2>
            <?php if (!$nextDuties): ?><div class="empty-state py-3"><i class="fa-regular fa-calendar"></i><div>No duties scheduled.</div></div>
            <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($nextDuties as $d): ?>
                <li class="list-group-item px-0">
                    <div class="fw-semibold"><?= e($d['duty_description']) ?></div>
                    <div class="small text-muted"><?= fmt_datetime($d['start_time']) ?> → <?= fmt_datetime($d['end_time']) ?> · <?= e(ucfirst($d['shift_type'] ?? '')) ?> · <?= e($d['Duty_location']) ?></div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Leave balance <?= date('Y') ?></h2>
            <table class="table table-sm mb-3">
                <thead><tr><th>Type</th><th class="text-end">Used</th><th class="text-end">Left</th></tr></thead>
                <tbody><?php foreach ($balances as $b): ?>
                    <tr><td><?= e($b['name']) ?></td><td class="text-end"><?= (int) $b['used'] ?></td><td class="text-end"><?= $b['rem'] === null ? 'No limit' : (int) $b['rem'] ?></td></tr>
                <?php endforeach; ?></tbody>
            </table>
            <h3 class="h6 text-muted">Recent requests</h3>
            <?php if (!$myLeave): ?><div class="text-muted small">No leave requests yet.</div>
            <?php else: ?>
            <ul class="list-unstyled small mb-0">
                <?php foreach ($myLeave as $l): ?>
                <li class="d-flex justify-content-between py-1 border-bottom"><span><?= e($l['type_name'] ?? '') ?> · <?= fmt_date($l['leave_start_date']) ?> – <?= fmt_date($l['leave_end_date']) ?></span><?= status_badge($l['status']) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
