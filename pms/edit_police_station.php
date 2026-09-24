<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role([ROLE_ADMIN]);
$station = station_find(get_int('id', 0) ?? 0);
if (!$station) {
    not_found('Police station not found.');
}
ob_start();
require PMS_ROOT . '/includes/station_form.php';
$formHtml = ob_get_clean();
$counts = [
    'staff'   => (int) db_value('SELECT COUNT(*) FROM staff WHERE police_station_id = ?', 'i', [(int) $station['id']]),
    'reports' => (int) db_value('SELECT COUNT(*) FROM reports WHERE police_station_id = ?', 'i', [(int) $station['id']]),
    'duties'  => (int) db_value('SELECT COUNT(*) FROM duties WHERE police_station_id = ?', 'i', [(int) $station['id']]),
];
$pageTitle = 'Edit Police Station';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="row g-4">
    <div class="col-lg-7"><div class="card"><div class="card-body"><?= $formHtml ?></div></div></div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Linked records</h2>
            <ul class="list-unstyled mb-0">
                <li>Staff: <strong><?= $counts['staff'] ?></strong></li>
                <li>Reports: <strong><?= $counts['reports'] ?></strong></li>
                <li>Duties: <strong><?= $counts['duties'] ?></strong></li>
            </ul>
            <p class="small text-muted mt-3 mb-0">Renaming keeps all links. A station with linked records can be deactivated but not deleted.</p>
        </div></div>
    </div>
</div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
