<?php
/** Search reports by accused CNIC (scoped to the stations the user may see). */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/reports.php';
$me = require_role([ROLE_ADMIN, ROLE_STATION]);

$cnicIn = get_str('id_card_no', 20);
$rows = [];
$cnic = null;
if ($cnicIn !== '') {
    $cnic = normalize_cnic($cnicIn);
    if ($cnic === null) {
        flash('danger', 'Enter a 13-digit CNIC (12345-1234567-1).');
    } else {
        [$scopeSql, $scopeTypes, $scopeParams] = station_scope('r.police_station_id');
        $rows = db_all(
            "SELECT r.id, r.reference_no, r.report_date, r.crime_type, r.complainant, r.under_section, r.accused_name, r.accused_address, r.status, r.police_station_name, ps.police_station_name AS station_name
             FROM reports r LEFT JOIN police_stations ps ON ps.id = r.police_station_id
             WHERE $scopeSql AND r.id_card_no = ? ORDER BY r.report_date DESC, r.id DESC LIMIT 200",
            $scopeTypes . 's',
            array_merge($scopeParams, [$cnic])
        );
        audit_log('report.cnic_search', 'report', null, ['cnic' => mask_cnic($cnic), 'results' => count($rows)]);
    }
}
$pageTitle = 'Search by CNIC';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card mb-3"><div class="card-body">
    <form method="get" class="row g-2 align-items-end" style="max-width: 560px">
        <div class="col-8"><label for="id_card_no" class="form-label">Accused CNIC</label><input class="form-control" id="id_card_no" name="id_card_no" value="<?= e($cnicIn) ?>" placeholder="12345-1234567-1" required autofocus></div>
        <div class="col-4"><button class="btn btn-navy w-100">Search</button></div>
    </form>
</div></div>
<?php if ($cnic !== null): ?>
<div class="card"><div class="card-body">
    <h2 class="h6 text-uppercase text-muted mb-3"><?= count($rows) ?> report(s) for CNIC <?= e(mask_cnic($cnic)) ?></h2>
    <?php if (!$rows): ?><div class="empty-state"><i class="fa-regular fa-folder-open"></i><div>No reports found for this CNIC in your scope.</div></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table table-hover align-middle">
        <thead><tr><th>Reference</th><th>Date</th><th>Station</th><th>Section</th><th>Crime type</th><th>Accused</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr><td><a href="<?= e(app_url('view_report.php?id=' . (int) $r['id'])) ?>"><?= e($r['reference_no']) ?></a></td><td><?= fmt_date($r['report_date']) ?></td><td><?= e($r['station_name'] ?? $r['police_station_name']) ?></td><td><?= e($r['under_section']) ?></td><td><?= e($r['crime_type'] ?: $r['complainant']) ?></td><td><?= e($r['accused_name']) ?><br><span class="small text-muted"><?= e($r['accused_address']) ?></span></td><td><?= status_badge($r['status']) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</div></div>
<?php endif; ?>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
