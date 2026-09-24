<?php
/**
 * Shared handler + form for add_police_station.php and edit_police_station.php.
 * Expects $station (array|null). Renaming a station updates the legacy name
 * columns on staff/reports/duties in one transaction so nothing is orphaned.
 */
declare(strict_types=1);

$station = $station ?? null;

function station_validate(array $in): array
{
    $errors = [];
    $name = trim($in['police_station_name'] ?? '');
    if ($name === '' || mb_strlen($name) > 150) {
        $errors[] = 'Station name is required (max 150 characters).';
    }
    $did = (int) ($in['district_id'] ?? 0);
    $tid = (int) ($in['tehsil_id'] ?? 0);
    $d = $did ? db_one('SELECT id, name FROM districts WHERE id = ? AND is_active = 1', 'i', [$did]) : null;
    if (!$d) {
        $errors[] = 'Choose a district.';
    }
    $t = $tid ? db_one('SELECT id, name FROM tehsils WHERE id = ? AND district_id = ? AND is_active = 1', 'ii', [$tid, $did]) : null;
    if (!$t) {
        $errors[] = 'Choose a tehsil that belongs to the selected district.';
    }
    return [$errors, $name, $d, $t];
}

if (is_post()) {
    csrf_verify();
    [$errors, $name, $d, $t] = station_validate($_POST);
    $dupId = $name !== '' ? db_value('SELECT id FROM police_stations WHERE police_station_name = ?', 's', [$name]) : null;
    if ($dupId !== null && (int) $dupId !== (int) ($station['id'] ?? 0)) {
        $errors[] = 'A station with that name already exists.';
    }
    if ($errors) {
        foreach ($errors as $er) {
            flash('danger', $er);
        }
        keep_old($_POST);
        redirect($station ? 'edit_police_station.php?id=' . (int) $station['id'] : 'add_police_station.php');
    }
    if ($station) {
        db_begin();
        db_exec('UPDATE police_stations SET police_station_name = ?, district = ?, tehsil = ?, district_id = ?, tehsil_id = ? WHERE id = ?',
            'sssiii', [$name, $d['name'], $t['name'], (int) $d['id'], (int) $t['id'], (int) $station['id']]);
        if ($name !== $station['police_station_name']) {
            // Keep legacy text columns consistent with the rename.
            foreach (['staff', 'reports', 'duties'] as $tbl) {
                db_exec("UPDATE `$tbl` SET police_station_name = ? WHERE police_station_id = ?", 'si', [$name, (int) $station['id']]);
            }
        }
        db_commit();
        audit_log('station.update', 'police_station', (int) $station['id'], ['name' => $name, 'renamed_from' => $name !== $station['police_station_name'] ? $station['police_station_name'] : null]);
        flash('success', 'Police station updated.');
    } else {
        db_exec('INSERT INTO police_stations (police_station_name, district, tehsil, district_id, tehsil_id, is_active) VALUES (?, ?, ?, ?, ?, 1)',
            'sssii', [$name, $d['name'], $t['name'], (int) $d['id'], (int) $t['id']]);
        audit_log('station.create', 'police_station', db_insert_id(), ['name' => $name]);
        flash('success', 'Police station added.');
    }
    clear_old();
    redirect('view_policestation.php');
}

$old = old_pull();
$val = fn(string $k, $default = '') => $old[$k] ?? ($station[$k] ?? $default);
$tehsilMap = tehsils_by_district();
?>
<form method="post" class="needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="police_station_name" class="form-label required">Station name</label>
        <input type="text" class="form-control" id="police_station_name" name="police_station_name" required maxlength="150" value="<?= e($val('police_station_name')) ?>">
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="district_id" class="form-label required">District</label>
            <select class="form-select" id="district_id" name="district_id" required data-district-select>
                <option value="">Select district</option>
                <?php foreach (districts_all() as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= (int) $val('district_id', 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label for="tehsil_id" class="form-label required">Tehsil</label>
            <select class="form-select" id="tehsil_id" name="tehsil_id" required data-tehsil-select data-tehsils="<?= e(json_encode($tehsilMap)) ?>" data-selected="<?= (int) $val('tehsil_id', 0) ?>">
                <option value="">Select tehsil</option>
            </select>
        </div>
    </div>
    <div class="mt-4">
        <button type="submit" class="btn btn-navy"><?= $station ? 'Save changes' : 'Add station' ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(app_url('view_policestation.php')) ?>">Cancel</a>
    </div>
</form>
