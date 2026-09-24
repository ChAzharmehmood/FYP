<?php
/** Head office admin dashboard (URL kept from the original project). */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/alerts.php';
$me = require_role([ROLE_ADMIN]);

$stats = [
    'reports'   => (int) db_value('SELECT COUNT(*) FROM reports WHERE status <> "Archived"'),
    'open'      => (int) db_value('SELECT COUNT(*) FROM reports WHERE status IN ("Open","Under Investigation")'),
    'stations'  => (int) db_value('SELECT COUNT(*) FROM police_stations WHERE is_active = 1'),
    'staff'     => (int) db_value('SELECT COUNT(*) FROM staff WHERE is_active = 1'),
    'leave'     => (int) db_value('SELECT COUNT(*) FROM leave_requests WHERE status = "pending"'),
    'duties'    => (int) db_value('SELECT COUNT(*) FROM duties WHERE status = "scheduled" AND DATE(start_time) = CURDATE()'),
];

$byMonth  = db_all('SELECT DATE_FORMAT(COALESCE(report_date, created_at), "%Y-%m") ym, COUNT(*) c FROM reports
                    WHERE COALESCE(report_date, created_at) >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY ym ORDER BY ym');
$byStatus = db_all('SELECT status, COUNT(*) c FROM reports GROUP BY status ORDER BY c DESC');
$byCrime  = db_all('SELECT COALESCE(NULLIF(crime_type, ""), "Unclassified") k, COUNT(*) c FROM reports WHERE status <> "Archived" GROUP BY k ORDER BY c DESC LIMIT 8');
$byDist   = db_all('SELECT COALESCE(d.name, NULLIF(r.district, ""), "Unknown") k, COUNT(*) c FROM reports r LEFT JOIN districts d ON d.id = r.district_id WHERE r.status <> "Archived" GROUP BY k ORDER BY c DESC LIMIT 8');
$recent   = db_all('SELECT r.id, r.reference_no, r.crime_type, r.status, r.report_date, ps.police_station_name AS station_name, r.police_station_name
                    FROM reports r LEFT JOIN police_stations ps ON ps.id = r.police_station_id ORDER BY r.id DESC LIMIT 8');

$months = [];
for ($i = 11; $i >= 0; $i--) {
    $months[date('Y-m', strtotime("-$i month", strtotime(date('Y-m-01'))))] = 0;
}
foreach ($byMonth as $m) {
    if (isset($months[$m['ym']])) {
        $months[$m['ym']] = (int) $m['c'];
    }
}
$chartData = [
    'months' => ['labels' => array_map(fn($k) => date('M y', strtotime($k . '-01')), array_keys($months)), 'values' => array_values($months)],
    'status' => ['labels' => array_column($byStatus, 'status'), 'values' => array_map('intval', array_column($byStatus, 'c'))],
    'crime'  => ['labels' => array_column($byCrime, 'k'), 'values' => array_map('intval', array_column($byCrime, 'c'))],
    'district' => ['labels' => array_column($byDist, 'k'), 'values' => array_map('intval', array_column($byDist, 'c'))],
];

$pageTitle = 'Head Office Dashboard';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Reports', $stats['reports'], 'fa-file-lines', 'manage_reports.php'],
        ['Open cases', $stats['open'], 'fa-folder-open', 'manage_reports.php?status=Open'],
        ['Stations', $stats['stations'], 'fa-building-shield', 'view_policestation.php'],
        ['Active staff', $stats['staff'], 'fa-users', 'view_staff.php'],
        ['Pending leave', $stats['leave'], 'fa-calendar-check', 'admin_leave_requests.php'],
        ['Duties today', $stats['duties'], 'fa-clipboard-list', 'view_duties.php'],
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
    <div class="col-lg-8">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Reports per month (last 12 months)</h2>
            <?php if (array_sum($months) === 0): ?>
                <div class="empty-state py-3"><i class="fa-regular fa-chart-bar"></i><div>No reports in the last 12 months.</div></div>
            <?php else: ?>
                <canvas id="chartMonths" height="110" aria-label="Reports per month" role="img"></canvas>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <?= alerts_panel_html() ?>
    </div>
</div>

<div class="row g-4 mb-4">
    <?php foreach ([['chartStatus', 'By status', $byStatus], ['chartCrime', 'By crime type', $byCrime], ['chartDistrict', 'By district', $byDist]] as [$id, $title, $rows]): ?>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3"><?= e($title) ?></h2>
            <?php if (!$rows): ?>
                <div class="empty-state py-3"><i class="fa-regular fa-chart-bar"></i><div>No data yet.</div></div>
            <?php else: ?>
                <canvas id="<?= e($id) ?>" height="200" aria-label="<?= e($title) ?>" role="img"></canvas>
            <?php endif; ?>
        </div></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 text-uppercase text-muted mb-0">Latest reports</h2>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('manage_reports.php')) ?>">All reports</a>
    </div>
    <?php if (!$recent): ?>
        <div class="empty-state"><i class="fa-regular fa-folder-open"></i><div>No reports have been filed yet.</div></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Reference</th><th>Station</th><th>Crime type</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
            <tr>
                <td><a href="<?= e(app_url('view_report.php?id=' . (int) $r['id'])) ?>"><?= e($r['reference_no']) ?></a></td>
                <td><?= e($r['station_name'] ?? $r['police_station_name']) ?></td>
                <td><?= e($r['crime_type'] ?: '—') ?></td>
                <td><?= fmt_date($r['report_date']) ?></td>
                <td><?= status_badge($r['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div></div>

<?php
$pageScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var data = ' . json_for_script($chartData) . ';
    var palette = ["#0b2545", "#c9a227", "#2a6f97", "#6c757d", "#198754", "#dc3545", "#fd7e14", "#6f42c1"];
    function bar(id, d, horizontal) {
        var el = document.getElementById(id); if (!el || !d.labels.length) return;
        new Chart(el, { type: "bar", data: { labels: d.labels, datasets: [{ data: d.values, backgroundColor: palette[0] }] },
            options: { indexAxis: horizontal ? "y" : "x", plugins: { legend: { display: false } }, scales: { x: { ticks: { precision: 0 } }, y: { ticks: { precision: 0 }, beginAtZero: true } } } });
    }
    function doughnut(id, d) {
        var el = document.getElementById(id); if (!el || !d.labels.length) return;
        new Chart(el, { type: "doughnut", data: { labels: d.labels, datasets: [{ data: d.values, backgroundColor: palette }] },
            options: { plugins: { legend: { position: "bottom" } } } });
    }
    bar("chartMonths", data.months, false);
    doughnut("chartStatus", data.status);
    bar("chartCrime", data.crime, true);
    bar("chartDistrict", data.district, true);
})();
</script>';
require PMS_ROOT . '/includes/layout_bottom.php';
