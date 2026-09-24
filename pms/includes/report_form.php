<?php
/**
 * Shared handler + form for generate_report.php (create) and edit_report.php (edit details).
 * Expects $report (array|null). Status changes, archiving and evidence are handled
 * on view_report.php / evidence.php, not here.
 */
declare(strict_types=1);
require_once PMS_ROOT . '/includes/reports.php';

$report = $report ?? null;
$isEdit = $report !== null;

if (is_post()) {
    csrf_verify();
    $in = [
        'police_station_id'    => post_int('police_station_id'),
        'under_section'        => post_str('under_section', 100),
        'crime_type'           => post_str('crime_type', 100),
        'accused_name'         => post_str('accused_name', 150),
        'accused_address'      => post_str('accused_address', 255),
        'id_card_no'           => post_str('id_card_no', 20),
        'complainant_name'     => post_str('complainant_name', 150),
        'complainant_contact'  => post_str('complainant_contact', 50),
        'investigation_officer'=> post_str('investigation_officer', 150),
        'report_description'   => post_str('report_description', 5000),
        'district_id'          => post_int('district_id'),
        'tehsil_id'            => post_int('tehsil_id'),
        'report_date'          => post_str('report_date', 10),
    ];
    $errors = [];
    $allowedStations = array_map(fn($s) => (int) $s['id'], stations_for_user());
    if (!is_admin()) {
        // Never trust a posted station id: station users always work in their own station.
        $in['police_station_id'] = $isEdit ? (int) $report['police_station_id'] : user_station_id();
    }
    if ($in['police_station_id'] === null || !in_array($in['police_station_id'], $allowedStations, true)) {
        $errors[] = 'Choose a valid police station.';
    }
    if (!in_array($in['under_section'], CRIME_SECTIONS, true)) {
        $errors[] = 'Choose a crime section.';
    }
    if (!in_array($in['crime_type'], CRIME_TYPES, true)) {
        $errors[] = 'Choose a crime type.';
    }
    foreach (['accused_name' => 'Accused name', 'accused_address' => 'Accused address', 'investigation_officer' => 'Investigation officer', 'report_description' => 'Report description'] as $k => $label) {
        if ($in[$k] === '') {
            $errors[] = $label . ' is required.';
        }
    }
    $cnic = null;
    if ($in['id_card_no'] !== '') {
        if ($isEdit && $in['id_card_no'][0] === '*') {
            $cnic = $report['id_card_no'];
        } else {
            $cnic = normalize_cnic($in['id_card_no']);
            if ($cnic === null) {
                $errors[] = 'Accused CNIC must have 13 digits (12345-1234567-1).';
            }
        }
    }
    $district = $in['district_id'] ? db_one('SELECT id, name FROM districts WHERE id = ? AND is_active = 1', 'i', [$in['district_id']]) : null;
    $tehsil   = ($district && $in['tehsil_id']) ? db_one('SELECT id, name FROM tehsils WHERE id = ? AND district_id = ? AND is_active = 1', 'ii', [$in['tehsil_id'], (int) $district['id']]) : null;
    if (!$district) {
        $errors[] = 'Choose a district.';
    }
    if (!$tehsil) {
        $errors[] = 'Choose a tehsil of that district.';
    }
    if ($in['report_date'] === '' || !valid_date($in['report_date']) || strtotime($in['report_date']) > time()) {
        $errors[] = 'Report date must be a valid date that is not in the future.';
    }
    if ($errors) {
        foreach ($errors as $er) {
            flash('danger', $er);
        }
        keep_old($_POST);
        redirect($isEdit ? 'edit_report.php?id=' . (int) $report['id'] : 'generate_report.php');
    }
    $stationName = (string) db_value('SELECT police_station_name FROM police_stations WHERE id = ?', 'i', [$in['police_station_id']], '');
    if ($isEdit) {
        db_exec(
            'UPDATE reports SET police_station_id = ?, police_station_name = ?, under_section = ?, crime_type = ?, accused_name = ?, accused_address = ?, id_card_no = ?,
                    complainant_name = NULLIF(?, ""), complainant_contact = NULLIF(?, ""), investigation_officer = ?, report_description = ?,
                    district_id = ?, district = ?, tehsil_id = ?, tehsil = ?, report_date = ? WHERE id = ?',
            'isssssssssisisssi',
            [$in['police_station_id'], $stationName, $in['under_section'], $in['crime_type'], $in['accused_name'], $in['accused_address'], $cnic,
             $in['complainant_name'], $in['complainant_contact'], $in['investigation_officer'], $in['report_description'],
             (int) $district['id'], $district['name'], (int) $tehsil['id'], $tehsil['name'], $in['report_date'], (int) $report['id']]
        );
        audit_log('report.update', 'report', (int) $report['id'], ['crime_type' => $in['crime_type']]);
        flash('success', 'Report ' . $report['reference_no'] . ' updated.');
        clear_old();
        redirect('view_report.php?id=' . (int) $report['id']);
    }
    db_begin();
    db_exec(
        'INSERT INTO reports (police_station_id, police_station_name, under_section, crime_type, accused_name, accused_address, id_card_no,
                              complainant_name, complainant_contact, investigation_officer, report_description,
                              district_id, district, tehsil_id, tehsil, report_date, status, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ""), NULLIF(?, ""), ?, ?, ?, ?, ?, ?, ?, "Open", ?, NOW())',
        'issssssssssisissi',
        [$in['police_station_id'], $stationName, $in['under_section'], $in['crime_type'], $in['accused_name'], $in['accused_address'], $cnic,
         $in['complainant_name'], $in['complainant_contact'], $in['investigation_officer'], $in['report_description'],
         (int) $district['id'], $district['name'], (int) $tehsil['id'], $tehsil['name'], $in['report_date'], (int) current_user()['id']]
    );
    $newId = db_insert_id();
    $ref   = report_reference_for($newId, $in['report_date']);
    db_exec('UPDATE reports SET reference_no = ? WHERE id = ?', 'si', [$ref, $newId]);
    db_exec('INSERT INTO report_status_history (report_id, old_status, new_status, actor_id, reason) VALUES (?, NULL, "Open", ?, "Report filed")', 'ii', [$newId, (int) current_user()['id']]);
    db_commit();
    audit_log('report.create', 'report', $newId, ['reference' => $ref, 'crime_type' => $in['crime_type']]);
    flash('success', 'Report ' . $ref . ' filed. You can now attach evidence.');
    clear_old();
    redirect('view_report.php?id=' . $newId);
}

$old = old_pull();
$val = fn(string $k, $default = '') => $old[$k] ?? ($report[$k] ?? $default);
$tehsilMap = tehsils_by_district();
$myStation = user_station_id();
?>
<form method="post" class="needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="police_station_id" class="form-label required">Police station</label>
            <select class="form-select" id="police_station_id" name="police_station_id" required <?= (!is_admin()) ? 'disabled' : '' ?>>
                <option value="">Select station</option>
                <?php foreach (stations_for_user() as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) $val('police_station_id', $myStation ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['police_station_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!is_admin()): ?><input type="hidden" name="police_station_id" value="<?= (int) ($report['police_station_id'] ?? $myStation) ?>"><?php endif; ?>
        </div>
        <div class="col-md-3">
            <label for="report_date" class="form-label required">Report date</label>
            <input type="date" class="form-control" id="report_date" name="report_date" required max="<?= date('Y-m-d') ?>" value="<?= e($val('report_date', date('Y-m-d'))) ?>">
        </div>
        <div class="col-md-3">
            <label for="under_section" class="form-label required">Section</label>
            <select class="form-select" id="under_section" name="under_section" required>
                <?php foreach (CRIME_SECTIONS as $s): ?><option value="<?= e($s) ?>" <?= $val('under_section') === $s ? 'selected' : '' ?>><?= $s === 'other' ? 'Other' : 'Section ' . e($s) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="crime_type" class="form-label required">Crime type</label>
            <select class="form-select" id="crime_type" name="crime_type" required>
                <option value="">Select crime type</option>
                <?php foreach (CRIME_TYPES as $c): ?><option value="<?= e($c) ?>" <?= $val('crime_type') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="district_id" class="form-label required">District</label>
            <select class="form-select" id="district_id" name="district_id" required data-district-select>
                <option value="">Select district</option>
                <?php foreach (districts_all() as $d): ?><option value="<?= (int) $d['id'] ?>" <?= (int) $val('district_id', 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="tehsil_id" class="form-label required">Tehsil</label>
            <select class="form-select" id="tehsil_id" name="tehsil_id" required data-tehsil-select data-tehsils="<?= e(json_encode($tehsilMap)) ?>" data-selected="<?= (int) $val('tehsil_id', 0) ?>"><option value="">Select tehsil</option></select>
        </div>

        <div class="col-12"><hr class="my-1"><h2 class="h6 text-uppercase text-muted">Accused</h2></div>
        <div class="col-md-4">
            <label for="accused_name" class="form-label required">Accused name</label>
            <input type="text" class="form-control" id="accused_name" name="accused_name" required maxlength="150" value="<?= e($val('accused_name')) ?>">
        </div>
        <div class="col-md-4">
            <label for="id_card_no" class="form-label">Accused CNIC</label>
            <input type="text" class="form-control" id="id_card_no" name="id_card_no" maxlength="20" placeholder="12345-1234567-1" value="<?= e($old['id_card_no'] ?? ($isEdit ? mask_cnic($report['id_card_no']) : '')) ?>">
            <?php if ($isEdit && !empty($report['id_card_no'])): ?><div class="form-text">Shown masked; type a full CNIC to replace.</div><?php endif; ?>
        </div>
        <div class="col-md-4">
            <label for="accused_address" class="form-label required">Accused address</label>
            <input type="text" class="form-control" id="accused_address" name="accused_address" required maxlength="255" value="<?= e($val('accused_address')) ?>">
        </div>

        <div class="col-12"><hr class="my-1"><h2 class="h6 text-uppercase text-muted">Complainant and investigation</h2></div>
        <div class="col-md-4">
            <label for="complainant_name" class="form-label">Complainant name</label>
            <input type="text" class="form-control" id="complainant_name" name="complainant_name" maxlength="150" value="<?= e($val('complainant_name')) ?>">
        </div>
        <div class="col-md-4">
            <label for="complainant_contact" class="form-label">Complainant contact</label>
            <input type="text" class="form-control" id="complainant_contact" name="complainant_contact" maxlength="50" value="<?= e($val('complainant_contact')) ?>">
        </div>
        <div class="col-md-4">
            <label for="investigation_officer" class="form-label required">Investigation officer</label>
            <input type="text" class="form-control" id="investigation_officer" name="investigation_officer" required maxlength="150" value="<?= e($val('investigation_officer')) ?>">
        </div>
        <div class="col-12">
            <label for="report_description" class="form-label required">Report description</label>
            <textarea class="form-control" id="report_description" name="report_description" rows="6" required maxlength="5000"><?= e($val('report_description')) ?></textarea>
        </div>
    </div>
    <div class="mt-4">
        <button type="submit" class="btn btn-navy"><?= $isEdit ? 'Save changes' : 'File report' ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(app_url($isEdit ? 'view_report.php?id=' . (int) $report['id'] : (is_staff() ? 'view_reports.php' : 'manage_reports.php'))) ?>">Cancel</a>
    </div>
</form>
