<?php
/** Report analysis: role-scoped counts and charts by month, status, crime type, district, station. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/reports.php';
$me = require_role([ROLE_ADMIN, ROLE_STATION]);

$from = get_str('from', 10);
$to   = get_str('to', 10);
if ($from === '' || !valid_date($from)) {
    $from = date('Y-m-d', strtotime('-11 months', strtotime(date('Y-m-01'))));
}
if ($to === '' || !valid_date($to)) {
    $to = date('Y-m-d');
}
[$scopeSql, $scopeTypes, $scopeParams] = station_scope('r.police_station_id');
$w = "$scopeSql AND r.status <> 'Archived' AND COALESCE(r.report_date, DATE(r.created_at)) BETWEEN ? AND ?";
$types = $scopeTypes . 'ss';
$params = array_merge($scopeParams, [$from, $to]);

$total    = (int) db_value("SELECT COUNT(*) FROM reports r WHERE $w", $types, $params);
$byMonth  = db_all("SELECT DATE_FORMAT(COALESCE(r.report_date, r.created_at), '%Y-%m') ym, COUNT(*) c FROM reports r WHERE $w GROUP BY ym ORDER BY ym", $types, $params);
$byStatus = db_all("SELECT r.status k, COUNT(*) c FROM reports r WHERE $w GROUP BY k ORDER BY c DESC", $types, $params);
$byCrime  = db_all("SELECT COALESCE(NULLIF(r.crime_type, ''), 'Unclassified') k, COUNT(*) c FROM reports r WHERE $w GROUP BY k ORDER BY c DESC LIMIT 12", $types, $params);
$byDist   = db_all("SELECT COALESCE(d.name, NULLIF(r.district, ''), 'Unknown') k, COUNT(*) c FROM reports r LEFT JOIN districts d ON d.id = r.district_id WHERE $w GROUP BY k ORDER BY c DESC LIMIT 12", $types, $params);
$byStation = is_admin() ? db_all("SELECT COALESCE(ps.police_station_name, r.police_station_name) k, COUNT(*) c FROM reports r LEFT JOIN police_stations ps ON ps.id = r.police_station_id WHERE $w GROUP BY k ORDER BY c DESC LIMIT 12", $types, $params) : [];
$byTehsil = db_all("SELECT COALESCE(t.name, NULLIF(r.tehsil, ''), 'Unknown') k, COUNT(*) c FROM reports r LEFT JOIN tehsils t ON t.id = r.tehsil_id WHERE $w GROUP BY k ORDER BY c DESC LIMIT 12", $types, $params);

if (get_str('export') === 'csv') {
    audit_log('analysis.export', 'report', null, ['from' => $from, 'to' => $to]);
    $rows = [];
    foreach ([['Month', $byMonth, 'ym'], ['Status', $byStatus, 'k'], ['Crime type', $byCrime, 'k'], ['District', $byDist, 'k'], ['Tehsil', $byTehsil, 'k'], ['Station', $byStation, 'k']] as [$dim, $set, $key]) {
        foreach ($set as $s) {
            $rows[] = [$dim, $s[$key], $s['c']];
        }
    }
    csv_download('report_analysis.csv', ['Dimension', 'Value', 'Reports'], $rows);
}
$series = fn(array $set, string $key = 'k') => ['labels' => array_column($set, $key), 'values' => array_map('intval', array_column($set, 'c'))];
$chartData = ['months' => $series($byMonth, 'ym'), 'status' => $series($byStatus), 'crime' => $series($byCrime), 'district' => $series($byDist), 'station' => $series($byStation), 'tehsil' => $series($byTehsil)];

$pageTitle = 'Report Analysis';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card mb-3"><div class="card-body">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3"><label for="from" class="form-label">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>"></div>
        <div class="col-md-3"><label for="to" class="form-label">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>"></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-navy">Apply</button><a class="btn btn-outline-secondary" href="<?= e(app_url('report_analysis.php')) ?>">Reset</a></div>
        <div class="col-md-3 text-md-end small"><a href="<?= e(query_link(['export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv"></i> Export CSV</a></div>
    </form>
</div></div>
<p class="text-muted"><strong><?= $total ?></strong> non-archived report(s) between <?= fmt_date($from) ?> and <?= fmt_date($to) ?><?= is_station_admin() ? ' for ' . e($me['station_name'] ?? 'your station') : '' ?>.</p>
<?php if ($total === 0): ?>
    <div class="card"><div class="card-body empty-state"><i class="fa-regular fa-chart-bar"></i><div>No reports in this period.</div></div></div>
<?php else: ?>
<div class="row g-4">
    <div class="col-12"><div class="card"><div class="card-body"><h2 class="h6 text-uppercase text-muted mb-3">Reports per month</h2><canvas id="chartMonths" height="90" role="img" aria-label="Reports per month"></canvas></div></div></div>
    <?php foreach ([['chartStatus', 'By status', 'doughnut'], ['chartCrime', 'By crime type', 'bar'], ['chartDistrict', 'By district', 'bar'], ['chartTehsil', 'By tehsil', 'bar']] as [$id, $title]): ?>
    <div class="col-md-6"><div class="card h-100"><div class="card-body"><h2 class="h6 text-uppercase text-muted mb-3"><?= e($title) ?></h2><canvas id="<?= $id ?>" height="220" role="img" aria-label="<?= e($title) ?>"></canvas></div></div></div>
    <?php endforeach; ?>
    <?php if (is_admin()): ?><div class="col-md-6"><div class="card h-100"><div class="card-body"><h2 class="h6 text-uppercase text-muted mb-3">By police station</h2><canvas id="chartStation" height="220" role="img" aria-label="By police station"></canvas></div></div></div><?php endif; ?>
</div>
<?php endif; ?>
<?php
$pageScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var data = ' . json_for_script($chartData) . ';
    var palette = ["#0b2545", "#c9a227", "#2a6f97", "#6c757d", "#198754", "#dc3545", "#fd7e14", "#6f42c1", "#20c997", "#0dcaf0", "#adb5bd", "#e83e8c"];
    function bar(id, d, horizontal) { var el = document.getElementById(id); if (!el || !d.labels.length) return;
        new Chart(el, { type: "bar", data: { labels: d.labels, datasets: [{ data: d.values, backgroundColor: palette[0] }] },
            options: { indexAxis: horizontal ? "y" : "x", plugins: { legend: { display: false } }, scales: { x: { ticks: { precision: 0 }, beginAtZero: true }, y: { ticks: { precision: 0 }, beginAtZero: true } } } }); }
    function doughnut(id, d) { var el = document.getElementById(id); if (!el || !d.labels.length) return;
        new Chart(el, { type: "doughnut", data: { labels: d.labels, datasets: [{ data: d.values, backgroundColor: palette }] }, options: { plugins: { legend: { position: "bottom" } } } }); }
    bar("chartMonths", data.months, false); doughnut("chartStatus", data.status); bar("chartCrime", data.crime, true);
    bar("chartDistrict", data.district, true); bar("chartTehsil", data.tehsil, true); bar("chartStation", data.station, true);
})();
</script>';
require PMS_ROOT . '/includes/layout_bottom.php';
