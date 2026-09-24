<?php
/** Station admin dashboard (URL kept from the original project). */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/alerts.php';
$me  = require_role([ROLE_STATION]);
$sid = user_station_id();
if ($sid === null) {
    flash('warning', 'Your account is not linked to a police station. Ask head office to assign one.');
}
$sidv = (int) $sid;

$stats = [
    'reports' => (int) db_value('SELECT COUNT(*) FROM reports WHERE police_station_id = ? AND status <> "Archived"', 'i', [$sidv]),
    'open'    => (int) db_value('SELECT COUNT(*) FROM reports WHERE police_station_id = ? AND status IN ("Open","Under Investigation")', 'i', [$sidv]),
    'staff'   => (int) db_value('SELECT COUNT(*) FROM staff WHERE police_station_id = ? AND is_active = 1', 'i', [$sidv]),
    'leave'   => (int) db_value('SELECT COUNT(*) FROM leave_requests lr JOIN staff s ON s.id = lr.staff_id WHERE s.police_station_id = ? AND lr.status = "pending"', 'i', [$sidv]),
    'duties'  => (int) db_value('SELECT COUNT(*) FROM duties WHERE police_station_id = ? AND status = "scheduled" AND DATE(start_time) = CURDATE()', 'i', [$sidv]),
    'failed'  => (int) db_value('SELECT COUNT(*) FROM duties WHERE police_station_id = ? AND notify_status = "failed"', 'i', [$sidv]),
];
$byStatus = db_all('SELECT status, COUNT(*) c FROM reports WHERE police_station_id = ? GROUP BY status', 'i', [$sidv]);
$byCrime  = db_all('SELECT COALESCE(NULLIF(crime_type, ""), "Unclassified") k, COUNT(*) c FROM reports WHERE police_station_id = ? AND status <> "Archived" GROUP BY k ORDER BY c DESC LIMIT 8', 'i', [$sidv]);
$today    = db_all('SELECT d.id, d.duty_description, d.start_time, d.end_time, d.status, s.name FROM duties d JOIN staff s ON s.id = d.staff_id
                    WHERE d.police_station_id = ? AND d.status = "scheduled" AND d.end_time >= NOW() ORDER BY d.start_time LIMIT 8', 'i', [$sidv]);
$pendingLeave = db_all('SELECT lr.id, lr.leave_start_date, lr.leave_end_date, lr.requested_days, s.name, lt.name AS type_name
                        FROM leave_requests lr JOIN staff s ON s.id = lr.staff_id LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                        WHERE s.police_station_id = ? AND lr.status = "pending" ORDER BY lr.created_at LIMIT 8', 'i', [$sidv]);
$chartData = [
    'status' => ['labels' => array_column($byStatus, 'status'), 'values' => array_map('intval', array_column($byStatus, 'c'))],
    'crime'  => ['labels' => array_column($byCrime, 'k'), 'values' => array_map('intval', array_column($byCrime, 'c'))],
];

$pageTitle = 'Station Dashboard';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Station reports', $stats['reports'], 'fa-file-lines', 'manage_reports.php'],
        ['Open cases', $stats['open'], 'fa-folder-open', 'manage_reports.php?status=Open'],
        ['Active staff', $stats['staff'], 'fa-users', 'manage_staff.php'],
        ['Pending leave', $stats['leave'], 'fa-calendar-check', 'admin_leave_requests.php'],
        ['Duties today', $stats['duties'], 'fa-clipboard-list', 'view_duties.php'],
        ['Emails to retry', $stats['failed'], 'fa-envelope-circle-check', 'view_duties.php?notify=failed'],
    ] as [$label, $value, $icon, $href]): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <a class="card stat-card text-decoration-none text-reset" href="<?= e(app_url($href)) ?>">
            <div class="stat-icon"><i class="fa-solid <?= e($icon) ?>"></i></div>
            <div><div class="stat-value"><?= (int) $value ?></div><div class="stat-label"><?= e($label) ?></div></div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Reports by status</h2>
            <?php if (!$byStatus): ?><div class="empty-state py-3"><i class="fa-regular fa-chart-bar"></i><div>No reports yet.</div></div>
            <?php else: ?><canvas id="chartStatus" height="200" role="img" aria-label="Reports by status"></canvas><?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Reports by crime type</h2>
            <?php if (!$byCrime): ?><div class="empty-state py-3"><i class="fa-regular fa-chart-bar"></i><div>No reports yet.</div></div>
            <?php else: ?><canvas id="chartCrime" height="200" role="img" aria-label="Reports by crime type"></canvas><?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-4"><?= alerts_panel_html() ?></div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 text-uppercase text-muted mb-0">Upcoming duties</h2>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('assign_duties.php')) ?>">Assign duty</a>
            </div>
            <?php if (!$today): ?><div class="empty-state"><i class="fa-regular fa-calendar"></i><div>No upcoming duties.</div></div>
            <?php else: ?>
            <div class="table-wrap"><table class="table table-sm mb-0">
                <thead><tr><th>Staff</th><th>Duty</th><th>Start</th></tr></thead>
                <tbody><?php foreach ($today as $d): ?>
                    <tr><td><?= e($d['name']) ?></td><td><a href="<?= e(app_url('edit_duty.php?id=' . (int) $d['id'])) ?>"><?= e(mb_strimwidth($d['duty_description'], 0, 40, '…')) ?></a></td><td><?= fmt_datetime($d['start_time']) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 text-uppercase text-muted mb-0">Pending leave requests</h2>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('admin_leave_requests.php')) ?>">Review</a>
            </div>
            <?php if (!$pendingLeave): ?><div class="empty-state"><i class="fa-regular fa-calendar-check"></i><div>Nothing waiting for review.</div></div>
            <?php else: ?>
            <div class="table-wrap"><table class="table table-sm mb-0">
                <thead><tr><th>Staff</th><th>Type</th><th>Dates</th><th>Days</th></tr></thead>
                <tbody><?php foreach ($pendingLeave as $l): ?>
                    <tr><td><?= e($l['name']) ?></td><td><?= e($l['type_name'] ?? '') ?></td><td><?= fmt_date($l['leave_start_date']) ?> – <?= fmt_date($l['leave_end_date']) ?></td><td><?= (int) $l['requested_days'] ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?php
$pageScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var data = ' . json_for_script($chartData) . ';
    var palette = ["#0b2545", "#c9a227", "#2a6f97", "#6c757d", "#198754", "#dc3545", "#fd7e14", "#6f42c1"];
    var s = document.getElementById("chartStatus");
    if (s && data.status.labels.length) new Chart(s, { type: "doughnut", data: { labels: data.status.labels, datasets: [{ data: data.status.values, backgroundColor: palette }] }, options: { plugins: { legend: { position: "bottom" } } } });
    var c = document.getElementById("chartCrime");
    if (c && data.crime.labels.length) new Chart(c, { type: "bar", data: { labels: data.crime.labels, datasets: [{ data: data.crime.values, backgroundColor: palette[0] }] }, options: { indexAxis: "y", plugins: { legend: { display: false } }, scales: { x: { ticks: { precision: 0 }, beginAtZero: true } } } });
})();
</script>';
require PMS_ROOT . '/includes/layout_bottom.php';
