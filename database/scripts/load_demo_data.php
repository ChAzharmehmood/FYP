<?php
/**
 * OPTIONAL synthetic demonstration data for a local/demo installation.
 * Never run this against a production database.
 *
 *   php database/scripts/load_demo_data.php
 *
 * Creates two demo stations, six demo accounts (names start with "demo_"),
 * reports, duties, leave requests and alerts. Each account gets a random
 * password that is printed ONCE on the console and stored only as a hash.
 * Re-running removes and recreates the demo records (real records are untouched).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}
require_once dirname(__DIR__, 2) . '/pms/config.php';
if (config('app_env') !== 'local') {
    exit("Refusing to load demo data: app_env is not 'local' in config.local.php.\n");
}
$db = db();

// Remove earlier demo data (FK-safe order).
$db->query("DELETE ev FROM report_evidence ev JOIN reports r ON r.id = ev.report_id JOIN police_stations ps ON ps.id = r.police_station_id WHERE ps.police_station_name LIKE 'Demo %'");
$db->query("DELETE h FROM report_status_history h JOIN reports r ON r.id = h.report_id JOIN police_stations ps ON ps.id = r.police_station_id WHERE ps.police_station_name LIKE 'Demo %'");
$db->query("DELETE r FROM reports r JOIN police_stations ps ON ps.id = r.police_station_id WHERE ps.police_station_name LIKE 'Demo %'");
$db->query("DELETE h FROM leave_request_history h JOIN leave_requests lr ON lr.id = h.leave_request_id JOIN staff s ON s.id = lr.staff_id WHERE s.name LIKE 'demo\\_%'");
$db->query("DELETE lr FROM leave_requests lr JOIN staff s ON s.id = lr.staff_id WHERE s.name LIKE 'demo\\_%'");
$db->query("DELETE d FROM duties d JOIN staff s ON s.id = d.staff_id WHERE s.name LIKE 'demo\\_%'");
$db->query("DELETE a FROM alerts a JOIN staff s ON s.id = a.created_by WHERE s.name LIKE 'demo\\_%'");
$db->query("DELETE FROM audit_logs WHERE actor_name LIKE 'demo\\_%'");
$db->query("DELETE FROM staff WHERE name LIKE 'demo\\_%'");
$db->query("DELETE FROM police_stations WHERE police_station_name LIKE 'Demo %'");
if (in_array('--remove-only', $argv, true)) {
    exit("Demo data removed.\n");
}

$d = $db->query("SELECT d.id AS did, d.name AS dname, t.id AS tid, t.name AS tname FROM districts d JOIN tehsils t ON t.district_id = d.id ORDER BY d.id, t.id LIMIT 2")->fetch_all(MYSQLI_ASSOC);
if (count($d) < 2) {
    exit("Need at least two tehsils in the database (run migrations first).\n");
}
$mk = $db->prepare('INSERT INTO police_stations (police_station_name, district, tehsil, district_id, tehsil_id) VALUES (?, ?, ?, ?, ?)');
$stations = [];
foreach ([['Demo Station North', $d[0]], ['Demo Station South', $d[1]]] as [$name, $geo]) {
    $mk->bind_param('sssii', $name, $geo['dname'], $geo['tname'], $geo['did'], $geo['tid']);
    $mk->execute();
    $stations[$name] = ['id' => (int) $mk->insert_id, 'geo' => $geo];
}

$mkU = $db->prepare('INSERT INTO staff (name, email, password, designation, role, police_station_id, police_station_name, id_card_no, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
$accounts = [
    ['demo_admin', 'admin', 'Superintendent', 'Demo Station North'],
    ['demo_sho_north', 'admin station', 'SHO', 'Demo Station North'],
    ['demo_sho_south', 'admin station', 'SHO', 'Demo Station South'],
    ['demo_asi_north', 'staff', 'ASI', 'Demo Station North'],
    ['demo_constable_north', 'staff', 'Constable', 'Demo Station North'],
    ['demo_constable_south', 'staff', 'Constable', 'Demo Station South'],
];
$creds = [];
$ids = [];
$cnicSeq = 1;
foreach ($accounts as [$name, $role, $desig, $stName]) {
    $pw = bin2hex(random_bytes(6));
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $email = $name . '@example.invalid';
    $cnic = sprintf('99999-%07d-%d', $cnicSeq, $cnicSeq % 10);
    $cnicSeq++;
    $sid = $stations[$stName]['id'];
    $mkU->bind_param('sssssiss', $name, $email, $hash, $desig, $role, $sid, $stName, $cnic);
    $mkU->execute();
    $ids[$name] = (int) $mkU->insert_id;
    $creds[] = [$name, $role, $pw];
}

$crimes = ['Theft', 'Robbery', 'Assault', 'Fraud', 'Burglary', 'Harassment', 'Cyber Crime', 'Drug Trafficking'];
$sections = ['379', '392', '337', '420', '457', '509', 'other', '9C'];
$statuses = ['Open', 'Open', 'Under Investigation', 'Closed'];
$mkR = $db->prepare('INSERT INTO reports (police_station_id, police_station_name, under_section, crime_type, accused_name, accused_address, id_card_no, complainant_name, complainant_contact, investigation_officer, report_description, district_id, district, tehsil_id, tehsil, report_date, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$n = 0;
foreach ($stations as $stName => $st) {
    $creator = $stName === 'Demo Station North' ? $ids['demo_asi_north'] : $ids['demo_constable_south'];
    for ($i = 0; $i < 14; $i++) {
        $n++;
        $crime = $crimes[$i % count($crimes)];
        $section = in_array($sections[$i % count($sections)], ['379', '420', 'other'], true) ? $sections[$i % count($sections)] : '379';
        $accused = 'Demo Accused ' . $n;
        $addr = 'House ' . (10 + $n) . ', Demo Colony';
        $cnic = sprintf('88888-%07d-%d', $n, $n % 10);
        $comp = 'Demo Complainant ' . $n;
        $contact = '0300-0000' . sprintf('%03d', $n);
        $io = $stName === 'Demo Station North' ? 'ASI demo_asi_north' : 'Constable demo_constable_south';
        $desc = "Synthetic demonstration record #$n describing a reported $crime incident. Not a real case.";
        $date = date('Y-m-d', strtotime('-' . ($i * 9 + $n) . ' days'));
        $status = $statuses[$i % count($statuses)];
        $created = $date . ' 10:00:00';
        $mkR->bind_param('isssssssssssisssiis', $st['id'], $stName, $section, $crime, $accused, $addr, $cnic, $comp, $contact, $io, $desc, $st['geo']['did'], $st['geo']['dname'], $st['geo']['tid'], $st['geo']['tname'], $date, $status, $creator, $created);
        $mkR->execute();
        $rid = (int) $mkR->insert_id;
        $db->query("UPDATE reports SET reference_no = CONCAT('PMS-', YEAR(report_date), '-', LPAD(id, 6, '0')), closed_at = IF(status = 'Closed', NOW(), NULL) WHERE id = $rid");
        $db->query("INSERT INTO report_status_history (report_id, old_status, new_status, actor_id, reason) VALUES ($rid, NULL, 'Open', $creator, 'Report filed (demo)')");
        if ($status !== 'Open') {
            $sho = $stName === 'Demo Station North' ? $ids['demo_sho_north'] : $ids['demo_sho_south'];
            $db->query("INSERT INTO report_status_history (report_id, old_status, new_status, actor_id, reason) VALUES ($rid, 'Open', '$status', $sho, 'Demo status change')");
        }
    }
}

$mkD = $db->prepare('INSERT INTO duties (staff_id, duty_description, start_time, end_time, police_station_id, police_station_name, shift_type, Duty_location, assigned_date, status, notify_status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "not_sent", ?)');
$shifts = ['morning' => ['08:00', '16:00'], 'evening' => ['16:00', '00:00'], 'night' => ['22:00', '06:00']];
foreach ([['demo_asi_north', 'Demo Station North', 'demo_sho_north'], ['demo_constable_north', 'Demo Station North', 'demo_sho_north'], ['demo_constable_south', 'Demo Station South', 'demo_sho_south']] as [$who, $stName, $by]) {
    for ($i = -3; $i <= 6; $i++) {
        $shift = array_keys($shifts)[abs($i) % 3];
        [$s, $e] = $shifts[$shift];
        $day = date('Y-m-d', strtotime("$i days"));
        $start = "$day $s:00";
        $end = ($shift === 'morning') ? "$day $e:00" : date('Y-m-d', strtotime("$day +1 day")) . " $e:00";
        $desc = ucfirst($shift) . ' patrol';
        $loc = 'Demo Chowk ' . (abs($i) + 1);
        $status = $i < 0 ? 'completed' : 'scheduled';
        $sid = $stations[$stName]['id'];
        $mkD->bind_param('isssisssssi', $ids[$who], $desc, $start, $end, $sid, $stName, $shift, $loc, $day, $status, $ids[$by]);
        $mkD->execute();
    }
}

$types = $db->query('SELECT id, name FROM leave_types WHERE is_active = 1')->fetch_all(MYSQLI_ASSOC);
$mkL = $db->prepare('INSERT INTO leave_requests (staff_id, leave_type, leave_type_id, leave_start_date, leave_end_date, requested_days, reason, status, approved_days, reviewed_by, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$leaveRows = [
    ['demo_asi_north', 0, '+10 days', 2, 'pending', 0, null],
    ['demo_constable_north', 1, '-20 days', 3, 'approved', 3, 'demo_sho_north'],
    ['demo_constable_north', 0, '+25 days', 1, 'pending', 0, null],
    ['demo_constable_south', 2, '-40 days', 5, 'rejected', 0, 'demo_sho_south'],
];
foreach ($leaveRows as [$who, $ti, $when, $days, $status, $approved, $by]) {
    $t = $types[$ti % count($types)];
    $start = date('Y-m-d', strtotime($when));
    $end = date('Y-m-d', strtotime($start . ' +' . ($days - 1) . ' days'));
    $reason = 'Demonstration leave request';
    $reviewer = $by ? $ids[$by] : null;
    $reviewedAt = $by ? date('Y-m-d H:i:s') : null;
    $mkL->bind_param('isisssisiis', $ids[$who], $t['name'], $t['id'], $start, $end, $days, $reason, $status, $approved, $reviewer, $reviewedAt);
    $mkL->execute();
}

$mkA = $db->prepare('INSERT INTO alerts (title, message, target_type, target_station_id, starts_at, expires_at, status, is_active, created_by, created_at) VALUES (?, ?, ?, ?, NOW(), ?, "active", 1, ?, NOW())');
$exp = date('Y-m-d H:i:s', strtotime('+7 days'));
$null = null;
$title = 'Demo: all-hands notice';
$msg = 'Synthetic alert visible to every user of the demo installation.';
$type = 'all';
$mkA->bind_param('sssisi', $title, $msg, $type, $null, $exp, $ids['demo_admin']);
$mkA->execute();
$title = 'Demo: North station briefing';
$msg = 'Synthetic alert targeted at Demo Station North only.';
$type = 'station';
$sid = $stations['Demo Station North']['id'];
$mkA->bind_param('sssisi', $title, $msg, $type, $sid, $exp, $ids['demo_sho_north']);
$mkA->execute();

echo "Demo data loaded: 2 stations, 6 accounts, 28 reports, 30 duties, 4 leave requests, 2 alerts.\n\n";
echo "Demo accounts (passwords are random, shown once, stored hashed):\n";
foreach ($creds as [$n, $r, $p]) {
    printf("  %-24s %-14s %s\n", $n, $r, $p);
}
echo "\nRemove everything again with: php database/scripts/load_demo_data.php --remove-only (or simply re-run to reset).\n";
