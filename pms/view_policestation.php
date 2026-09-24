<?php
/** Police stations: list, search, deactivate/reactivate, delete only when empty. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role([ROLE_ADMIN, ROLE_STATION]);

if (is_post()) {
    csrf_verify();
    if (!is_admin()) {
        deny();
    }
    $id = post_int('id', 0) ?? 0;
    $s  = station_find($id);
    if (!$s) {
        not_found('Police station not found.');
    }
    $action = post_str('action', 20);
    if ($action === 'deactivate' || $action === 'activate') {
        $active = $action === 'activate' ? 1 : 0;
        db_exec('UPDATE police_stations SET is_active = ? WHERE id = ?', 'ii', [$active, $id]);
        audit_log('station.' . $action, 'police_station', $id);
        flash('success', 'Station ' . $action . 'd.');
    } elseif ($action === 'delete') {
        $linked = (int) db_value('SELECT (SELECT COUNT(*) FROM staff WHERE police_station_id = ?) + (SELECT COUNT(*) FROM reports WHERE police_station_id = ?) + (SELECT COUNT(*) FROM duties WHERE police_station_id = ?) + (SELECT COUNT(*) FROM alerts WHERE target_station_id = ?)', 'iiii', [$id, $id, $id, $id]);
        if ($linked > 0) {
            flash('danger', 'This station has linked staff, reports, duties or alerts and cannot be deleted. Deactivate it instead.');
        } else {
            db_exec('DELETE FROM police_stations WHERE id = ?', 'i', [$id]);
            audit_log('station.delete', 'police_station', $id, ['name' => $s['police_station_name']]);
            flash('success', 'Station deleted.');
        }
    }
    redirect('view_policestation.php');
}

$q        = get_str('q', 100);
$district = get_int('district', 0) ?? 0;
$tehsil   = get_int('tehsil', 0) ?? 0;
$showAll  = get_str('show', 10) === 'all';
$where = ['1=1'];
$types = '';
$params = [];
if (!is_admin()) {
    $where[] = 'ps.id = ?';
    $types .= 'i';
    $params[] = (int) user_station_id();
}
if (!$showAll) {
    $where[] = 'ps.is_active = 1';
}
if ($q !== '') {
    $where[] = 'ps.police_station_name LIKE ?';
    $types .= 's';
    $params[] = '%' . $q . '%';
}
if ($district) {
    $where[] = 'ps.district_id = ?';
    $types .= 'i';
    $params[] = $district;
}
if ($tehsil) {
    $where[] = 'ps.tehsil_id = ?';
    $types .= 'i';
    $params[] = $tehsil;
}
$w = implode(' AND ', $where);
[$sortKey, $sortDir, $orderSql] = db_order_by(get_str('sort'), ['name' => 'ps.police_station_name', 'district' => 'd.name', 'tehsil' => 't.name', 'staff' => 'staff_count'], 'name', get_str('dir'));
$total = (int) db_value("SELECT COUNT(*) FROM police_stations ps LEFT JOIN districts d ON d.id = ps.district_id LEFT JOIN tehsils t ON t.id = ps.tehsil_id WHERE $w", $types, $params);
$pg = paginate($total, 20);
$rows = db_all(
    "SELECT ps.*, d.name AS district_name, t.name AS tehsil_name,
            (SELECT COUNT(*) FROM staff s WHERE s.police_station_id = ps.id AND s.is_active = 1) AS staff_count,
            (SELECT COUNT(*) FROM reports r WHERE r.police_station_id = ps.id) AS report_count
     FROM police_stations ps LEFT JOIN districts d ON d.id = ps.district_id LEFT JOIN tehsils t ON t.id = ps.tehsil_id
     WHERE $w ORDER BY $orderSql LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$pg['per_page'], $pg['offset']])
);
if (get_str('export') === 'csv') {
    audit_log('station.export', 'police_station', null, ['rows' => count($rows)]);
    csv_download('police_stations.csv', ['Name', 'District', 'Tehsil', 'Active', 'Active staff', 'Reports'],
        array_map(fn($r) => [$r['police_station_name'], $r['district_name'] ?? $r['district'], $r['tehsil_name'] ?? $r['tehsil'], $r['is_active'] ? 'Yes' : 'No', $r['staff_count'], $r['report_count']], $rows));
}
$sortLink = function (string $key, string $label) use ($sortKey, $sortDir): string {
    $dir = ($sortKey === $key && $sortDir === 'ASC') ? 'desc' : 'asc';
    $icon = $sortKey === $key ? ($sortDir === 'ASC' ? ' ▲' : ' ▼') : '';
    return '<a class="text-reset text-decoration-none" href="' . e(query_link(['sort' => $key, 'dir' => $dir])) . '">' . e($label) . $icon . '</a>';
};

$pageTitle = 'Police Stations';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card mb-3"><div class="card-body">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label for="q" class="form-label">Search name</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" maxlength="100">
        </div>
        <div class="col-md-3">
            <label for="district" class="form-label">District</label>
            <select class="form-select" id="district" name="district" data-district-select>
                <option value="">All</option>
                <?php foreach (districts_all() as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $district === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="tehsil" class="form-label">Tehsil</label>
            <select class="form-select" id="tehsil" name="tehsil" data-tehsil-select data-tehsils="<?= e(json_encode(tehsils_by_district())) ?>" data-selected="<?= $tehsil ?>"><option value="">All</option></select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-navy" type="submit">Filter</button>
            <a class="btn btn-outline-secondary" href="<?= e(app_url('view_policestation.php')) ?>">Reset</a>
        </div>
        <div class="col-12 d-flex gap-3 small">
            <div class="form-check"><input class="form-check-input" type="checkbox" id="show" name="show" value="all" <?= $showAll ? 'checked' : '' ?> onchange="this.form.submit()"><label class="form-check-label" for="show">Include deactivated</label></div>
            <a href="<?= e(query_link(['export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
            <?php if (is_admin()): ?><a href="<?= e(app_url('add_police_station.php')) ?>"><i class="fa-solid fa-plus"></i> Add station</a><?php endif; ?>
        </div>
    </form>
</div></div>

<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-muted small"><?= $total ?> station(s)</span>
        <?= pagination_html($pg) ?>
    </div>
    <?php if (!$rows): ?>
        <div class="empty-state"><i class="fa-regular fa-building"></i><div>No stations match.</div></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table table-hover align-middle">
        <thead><tr><th><?= $sortLink('name', 'Station') ?></th><th><?= $sortLink('district', 'District') ?></th><th><?= $sortLink('tehsil', 'Tehsil') ?></th><th class="text-end"><?= $sortLink('staff', 'Staff') ?></th><th class="text-end">Reports</th><th>State</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="fw-semibold"><?= e($r['police_station_name']) ?></td>
                <td><?= e($r['district_name'] ?? $r['district']) ?></td>
                <td><?= e($r['tehsil_name'] ?? $r['tehsil']) ?></td>
                <td class="text-end"><?= (int) $r['staff_count'] ?></td>
                <td class="text-end"><?= (int) $r['report_count'] ?></td>
                <td><?= status_badge($r['is_active'] ? 'active' : 'inactive') ?></td>
                <td class="table-actions text-end">
                    <?php if (is_admin()): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('edit_police_station.php?id=' . (int) $r['id'])) ?>">Edit</a>
                        <form method="post" class="d-inline" data-confirm="<?= $r['is_active'] ? 'Deactivate this station? Its staff keep their records but the station is hidden from new forms.' : 'Reactivate this station?' ?>">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button class="btn btn-sm btn-outline-<?= $r['is_active'] ? 'secondary' : 'success' ?>" name="action" value="<?= $r['is_active'] ? 'deactivate' : 'activate' ?>"><?= $r['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                        <?php if ((int) $r['staff_count'] === 0 && (int) $r['report_count'] === 0): ?>
                        <form method="post" class="d-inline" data-confirm="Permanently delete this empty station?">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" name="action" value="delete">Delete</button>
                        </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div></div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
