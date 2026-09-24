<?php
/**
 * Shared report list (filters, pagination, allowlisted sort, CSV export).
 * Used by manage_reports.php (admins) and view_reports.php (staff, read-only).
 * Scope is enforced server-side by station_scope(); staff see their station.
 */
declare(strict_types=1);
require_once PMS_ROOT . '/includes/reports.php';

$selfPage = basename((string) $_SERVER['SCRIPT_NAME']);
$q        = get_str('q', 100);
$status   = get_str('status', 30);
$crime    = get_str('crime', 100);
$station  = get_int('station', 0) ?? 0;
$district = get_int('district', 0) ?? 0;
$tehsil   = get_int('tehsil', 0) ?? 0;
$from     = get_str('from', 10);
$to       = get_str('to', 10);
$cnic     = get_str('cnic', 20);

$where  = [];
$types  = '';
$params = [];
[$scopeSql, $scopeTypes, $scopeParams] = station_scope('r.police_station_id');
$where[] = $scopeSql;
$types  .= $scopeTypes;
$params  = array_merge($params, $scopeParams);
if ($status !== '' && in_array($status, REPORT_STATUSES, true)) {
    $where[] = 'r.status = ?';
    $types .= 's';
    $params[] = $status;
} elseif ($status === 'active') {
    $where[] = 'r.status IN ("Open","Under Investigation")';
} elseif ($status !== 'all') {
    $where[] = 'r.status <> "Archived"';
}
if ($q !== '') {
    $where[] = '(r.reference_no LIKE ? OR r.accused_name LIKE ? OR r.complainant_name LIKE ? OR r.investigation_officer LIKE ? OR r.report_description LIKE ?)';
    $types .= 'sssss';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($crime !== '' && in_array($crime, CRIME_TYPES, true)) {
    $where[] = 'r.crime_type = ?';
    $types .= 's';
    $params[] = $crime;
}
if ($station && is_admin()) {
    $where[] = 'r.police_station_id = ?';
    $types .= 'i';
    $params[] = $station;
}
if ($district) {
    $where[] = 'r.district_id = ?';
    $types .= 'i';
    $params[] = $district;
}
if ($tehsil) {
    $where[] = 'r.tehsil_id = ?';
    $types .= 'i';
    $params[] = $tehsil;
}
if ($from !== '' && valid_date($from)) {
    $where[] = 'r.report_date >= ?';
    $types .= 's';
    $params[] = $from;
}
if ($to !== '' && valid_date($to)) {
    $where[] = 'r.report_date <= ?';
    $types .= 's';
    $params[] = $to;
}
if ($cnic !== '') {
    $n = normalize_cnic($cnic);
    $where[] = 'r.id_card_no = ?';
    $types .= 's';
    $params[] = $n ?? $cnic;
}
$w = implode(' AND ', $where);
[$sortKey, $sortDir, $orderSql] = db_order_by(get_str('sort'), [
    'ref' => 'r.id', 'date' => 'r.report_date', 'station' => 'ps.police_station_name', 'crime' => 'r.crime_type',
    'accused' => 'r.accused_name', 'status' => 'r.status', 'updated' => 'r.updated_at',
], 'ref', get_str('dir') ?: 'desc');
$base = 'FROM reports r LEFT JOIN police_stations ps ON ps.id = r.police_station_id LEFT JOIN districts d ON d.id = r.district_id LEFT JOIN tehsils t ON t.id = r.tehsil_id';
$total = (int) db_value("SELECT COUNT(*) $base WHERE $w", $types, $params);
$pg    = paginate($total, 20);
$rows  = db_all(
    "SELECT r.id, r.reference_no, r.report_date, r.crime_type, r.complainant, r.accused_name, r.investigation_officer, r.status, r.updated_at, r.police_station_name, r.district, r.tehsil,
            ps.police_station_name AS station_name, d.name AS district_name, t.name AS tehsil_name,
            (SELECT COUNT(*) FROM report_evidence ev WHERE ev.report_id = r.id AND ev.deleted_at IS NULL) AS evidence_count
     $base WHERE $w ORDER BY $orderSql LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$pg['per_page'], $pg['offset']])
);
if (get_str('export') === 'csv') {
    if (is_staff()) {
        deny('Exports are available to station and head office admins.');
    }
    audit_log('report.export', 'report', null, ['rows' => count($rows), 'filters' => array_filter(compact('q', 'status', 'crime', 'station', 'district', 'from', 'to'))]);
    csv_download('reports.csv', ['Reference', 'Date', 'Station', 'District', 'Tehsil', 'Crime type', 'Accused', 'Investigation officer', 'Status', 'Evidence files'],
        array_map(fn($r) => [$r['reference_no'], $r['report_date'], $r['station_name'] ?? $r['police_station_name'], $r['district_name'] ?? $r['district'], $r['tehsil_name'] ?? $r['tehsil'], $r['crime_type'] ?: $r['complainant'], $r['accused_name'], $r['investigation_officer'], $r['status'], $r['evidence_count']], $rows));
}
$sortLink = function (string $key, string $label) use ($sortKey, $sortDir): string {
    $dir = ($sortKey === $key && $sortDir === 'ASC') ? 'desc' : 'asc';
    $icon = $sortKey === $key ? ($sortDir === 'ASC' ? ' ▲' : ' ▼') : '';
    return '<a class="text-reset text-decoration-none" href="' . e(query_link(['sort' => $key, 'dir' => $dir])) . '">' . e($label) . $icon . '</a>';
};
?>
<div class="card mb-3"><div class="card-body">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3"><label for="q" class="form-label">Search</label><input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Reference, accused, officer…"></div>
        <div class="col-md-2"><label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">Not archived</option><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Open + Under investigation</option>
                <?php foreach (REPORT_STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Everything</option>
            </select></div>
        <div class="col-md-2"><label for="crime" class="form-label">Crime type</label>
            <select class="form-select" id="crime" name="crime"><option value="">All</option><?php foreach (CRIME_TYPES as $c): ?><option value="<?= e($c) ?>" <?= $crime === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
        <?php if (is_admin()): ?>
        <div class="col-md-2"><label for="station" class="form-label">Station</label>
            <select class="form-select" id="station" name="station"><option value="">All</option><?php foreach (stations_all(false) as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $station === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['police_station_name']) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <div class="col-md-2"><label for="district" class="form-label">District</label>
            <select class="form-select" id="district" name="district" data-district-select><option value="">All</option><?php foreach (districts_all(false) as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $district === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label for="tehsil" class="form-label">Tehsil</label>
            <select class="form-select" id="tehsil" name="tehsil" data-tehsil-select data-tehsils="<?= e(json_encode(tehsils_by_district())) ?>" data-selected="<?= $tehsil ?>"><option value="">All</option></select></div>
        <div class="col-md-2"><label for="from" class="form-label">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>"></div>
        <div class="col-md-2"><label for="to" class="form-label">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>"></div>
        <div class="col-md-2"><label for="cnic" class="form-label">Accused CNIC</label><input class="form-control" id="cnic" name="cnic" value="<?= e($cnic) ?>" placeholder="12345-1234567-1"></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-navy">Filter</button><a class="btn btn-outline-secondary" href="<?= e(app_url($selfPage)) ?>">Reset</a></div>
        <div class="col-12 d-flex gap-3 small">
            <?php if (can_create_report()): ?><a href="<?= e(app_url('generate_report.php')) ?>"><i class="fa-solid fa-file-circle-plus"></i> File a report</a><?php endif; ?>
            <?php if (!is_staff()): ?><a href="<?= e(query_link(['export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv"></i> Export this page as CSV</a><?php endif; ?>
        </div>
    </form>
</div></div>

<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2"><span class="text-muted small"><?= $total ?> report(s)</span><?= pagination_html($pg) ?></div>
    <?php if (!$rows): ?><div class="empty-state"><i class="fa-regular fa-folder-open"></i><div>No reports match these filters.</div></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table table-hover align-middle">
        <thead><tr><th><?= $sortLink('ref', 'Reference') ?></th><th><?= $sortLink('date', 'Date') ?></th><?php if (is_admin()): ?><th><?= $sortLink('station', 'Station') ?></th><?php endif; ?><th>District / Tehsil</th><th><?= $sortLink('crime', 'Crime type') ?></th><th><?= $sortLink('accused', 'Accused') ?></th><th>Officer</th><th><?= $sortLink('status', 'Status') ?></th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr>
                <td><a href="<?= e(app_url('view_report.php?id=' . (int) $r['id'])) ?>" class="fw-semibold"><?= e($r['reference_no']) ?></a><?= $r['evidence_count'] ? ' <i class="fa-solid fa-paperclip text-muted" title="' . (int) $r['evidence_count'] . ' file(s)"></i>' : '' ?></td>
                <td class="text-nowrap"><?= fmt_date($r['report_date']) ?></td>
                <?php if (is_admin()): ?><td><?= e($r['station_name'] ?? $r['police_station_name']) ?></td><?php endif; ?>
                <td class="small"><?= e($r['district_name'] ?? $r['district']) ?> / <?= e($r['tehsil_name'] ?? $r['tehsil']) ?></td>
                <td><?= e($r['crime_type'] ?: ($r['complainant'] ?: '—')) ?></td>
                <td><?= e($r['accused_name']) ?></td>
                <td class="small"><?= e($r['investigation_officer']) ?></td>
                <td><?= status_badge($r['status']) ?></td>
                <td class="table-actions text-end">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('view_report.php?id=' . (int) $r['id'])) ?>">Open</a>
                </td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <div class="d-flex justify-content-end mt-2"><?= pagination_html($pg) ?></div>
    <?php endif; ?>
</div></div>
