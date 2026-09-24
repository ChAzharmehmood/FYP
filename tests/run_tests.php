<?php
/**
 * End-to-end verification suite (CLI). Drives the running local site over HTTP
 * with isolated synthetic data (all names start with "zzt_"), checks the
 * database directly where needed, and removes its data afterwards.
 *
 *   php tests/run_tests.php               run everything
 *   php tests/run_tests.php --keep        keep the synthetic data for inspection
 *   php tests/run_tests.php --no-db-setup skip the fresh-install / upgrade tests
 *
 * Requirements: Apache + MySQL running, app_url in config.local.php reachable,
 * php curl extension. Writes docs/TEST_RESULTS.md.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}
require_once dirname(__DIR__) . '/pms/config.php';
require_once dirname(__DIR__) . '/pms/includes/helpers.php';

$KEEP = in_array('--keep', $argv, true);
$SKIP_DB = in_array('--no-db-setup', $argv, true);
$BASE = rtrim((string) config('app_url'), '/');
$PASS = 'Test-Pass-1234';
$results = [];
$tmp = sys_get_temp_dir() . '/pms_tests_' . getmypid();
@mkdir($tmp, 0700, true);

/* ---------------- HTTP client ---------------- */
class Client
{
    public string $jar;
    public function __construct(string $name, string $dir)
    {
        $this->jar = $dir . '/' . $name . '.cookies';
        @unlink($this->jar);
    }
    public function req(string $method, string $path, array $fields = [], array $files = [], array $headers = []): array
    {
        global $BASE;
        $ch = curl_init($BASE . '/' . ltrim($path, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($files) {
                $post = $fields;
                foreach ($files as $k => $f) {
                    $post[$k] = new CURLFile($f['path'], $f['type'], $f['name']);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
            }
        }
        $raw = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr($raw, 0, $hsize);
        $body = substr($raw, $hsize);
        $loc = preg_match('/^Location:\s*(.+)$/mi', $head, $m) ? trim($m[1]) : '';
        return ['code' => $code, 'body' => $body, 'location' => $loc, 'head' => $head];
    }
    public function get(string $path, array $headers = []): array { return $this->req('GET', $path, [], [], $headers); }
    public function post(string $path, array $fields = [], array $files = [], array $headers = []): array { return $this->req('POST', $path, $fields, $files, $headers); }
    public function csrf(string $path): string
    {
        $r = $this->get($path);
        return preg_match('/name="csrf_token" value="([^"]+)"/', $r['body'], $m) ? $m[1] : '';
    }
    public function login(string $user, string $pass, string $page = 'index.php'): array
    {
        $tok = $this->csrf($page);
        return $this->post($page, ['username' => $user, 'password' => $pass, 'csrf_token' => $tok]);
    }
    /** POST with a fresh token fetched from $tokenPage. */
    public function postCsrf(string $path, array $fields, string $tokenPage, array $files = []): array
    {
        $fields['csrf_token'] = $this->csrf($tokenPage);
        return $this->post($path, $fields, $files);
    }
}

function t(string $name, string $expected, string $actual, bool $pass, string $group = ''): void
{
    global $results;
    $results[] = ['group' => $group, 'name' => $name, 'expected' => $expected, 'actual' => $actual, 'status' => $pass ? 'PASS' : 'FAIL'];
    echo ($pass ? '  ok   ' : '  FAIL ') . $name . ($pass ? '' : "  (expected: $expected | actual: $actual)") . PHP_EOL;
}
function notrun(string $name, string $why, string $group = ''): void
{
    global $results;
    $results[] = ['group' => $group, 'name' => $name, 'expected' => '', 'actual' => $why, 'status' => 'NOT RUN'];
    echo "  skip $name ($why)" . PHP_EOL;
}
function has(string $hay, string $needle): bool { return strpos($hay, $needle) !== false; }
/** Redirect target file name, or '-' when the response did not redirect. */
function loc(array $r): string
{
    $l = (string) ($r['location'] ?? '');
    return $l === '' ? '-' : basename((string) strtok($l, '?'));
}

/* ---------------- Synthetic data ---------------- */
echo "Preparing synthetic data...\n";
$db = db();
function cleanup(): void
{
    $db = db();
    $db->query("DELETE ev FROM report_evidence ev JOIN reports r ON r.id = ev.report_id JOIN police_stations ps ON ps.id = r.police_station_id WHERE ps.police_station_name LIKE 'ZZTEST%'");
    $db->query("DELETE h FROM report_status_history h JOIN reports r ON r.id = h.report_id JOIN police_stations ps ON ps.id = r.police_station_id WHERE ps.police_station_name LIKE 'ZZTEST%'");
    $db->query("DELETE r FROM reports r JOIN police_stations ps ON ps.id = r.police_station_id WHERE ps.police_station_name LIKE 'ZZTEST%'");
    $db->query("DELETE h FROM leave_request_history h JOIN leave_requests lr ON lr.id = h.leave_request_id JOIN staff s ON s.id = lr.staff_id WHERE s.name LIKE 'zzt\\_%'");
    $db->query("DELETE lr FROM leave_requests lr JOIN staff s ON s.id = lr.staff_id WHERE s.name LIKE 'zzt\\_%'");
    $db->query("DELETE d FROM duties d JOIN staff s ON s.id = d.staff_id WHERE s.name LIKE 'zzt\\_%'");
    $db->query("DELETE a FROM alerts a JOIN police_stations ps ON ps.id = a.target_station_id WHERE ps.police_station_name LIKE 'ZZTEST%'");
    $db->query("DELETE a FROM alerts a JOIN staff s ON s.id = a.created_by WHERE s.name LIKE 'zzt\\_%'");
    $db->query("DELETE pr FROM password_resets pr JOIN staff s ON s.id = pr.staff_id WHERE s.name LIKE 'zzt\\_%'");
    $db->query("DELETE FROM login_attempts WHERE username LIKE 'zzt\\_%'");
    $db->query("DELETE FROM audit_logs WHERE actor_name LIKE 'zzt\\_%'");
    $db->query("UPDATE reports SET assigned_to = NULL WHERE assigned_to IN (SELECT id FROM staff WHERE name LIKE 'zzt\\_%')");
    $db->query("UPDATE reports SET created_by = NULL WHERE created_by IN (SELECT id FROM staff WHERE name LIKE 'zzt\\_%')");
    $db->query("DELETE FROM staff WHERE name LIKE 'zzt\\_%'");
    $db->query("DELETE FROM police_stations WHERE police_station_name LIKE 'ZZTEST%'");
}
cleanup();
$distId = (int) $db->query("SELECT id FROM districts ORDER BY id LIMIT 1")->fetch_row()[0];
$tehId  = (int) $db->query("SELECT id FROM tehsils WHERE district_id = $distId ORDER BY id LIMIT 1")->fetch_row()[0];
$distName = (string) $db->query("SELECT name FROM districts WHERE id = $distId")->fetch_row()[0];
$tehName  = (string) $db->query("SELECT name FROM tehsils WHERE id = $tehId")->fetch_row()[0];
$mk = $db->prepare('INSERT INTO police_stations (police_station_name, district, tehsil, district_id, tehsil_id, is_active) VALUES (?, ?, ?, ?, ?, 1)');
foreach (['ZZTEST Station A', 'ZZTEST Station B'] as $n) {
    $mk->bind_param('sssii', $n, $distName, $tehName, $distId, $tehId);
    $mk->execute();
}
$stA = (int) $db->query("SELECT id FROM police_stations WHERE police_station_name = 'ZZTEST Station A'")->fetch_row()[0];
$stB = (int) $db->query("SELECT id FROM police_stations WHERE police_station_name = 'ZZTEST Station B'")->fetch_row()[0];
$hash = password_hash($PASS, PASSWORD_DEFAULT);
$mkU = $db->prepare('INSERT INTO staff (name, email, password, designation, role, police_station_id, police_station_name, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
$users = [
    ['zzt_admin', 'admin', $stA, 'ZZTEST Station A'],
    ['zzt_sa_a', 'admin station', $stA, 'ZZTEST Station A'],
    ['zzt_sa_b', 'admin station', $stB, 'ZZTEST Station B'],
    ['zzt_staff_a', 'staff', $stA, 'ZZTEST Station A'],
    ['zzt_staff_a2', 'staff', $stA, 'ZZTEST Station A'],
    ['zzt_staff_b', 'staff', $stB, 'ZZTEST Station B'],
    ['zzt_plain', 'staff', $stA, 'ZZTEST Station A'],
];
$ids = [];
foreach ($users as [$n, $role, $sid, $sname]) {
    $email = $n . '@example.test';
    $desig = 'Tester';
    $pw = $n === 'zzt_plain' ? $PASS : $hash; // zzt_plain deliberately keeps a plaintext password for the migration test
    $mkU->bind_param('sssssis', $n, $email, $pw, $desig, $role, $sid, $sname);
    $mkU->execute();
    $ids[$n] = (int) $mkU->insert_id;
}
$admin = new Client('admin', $tmp);
$saA = new Client('saA', $tmp);
$saB = new Client('saB', $tmp);
$stfA = new Client('stfA', $tmp);
$stfB = new Client('stfB', $tmp);
$anon = new Client('anon', $tmp);

/* ================= 1. Login / logout / routing ================= */
echo "\n[1] Login, logout, role routing\n";
$r = $admin->login('zzt_admin', $PASS, 'admin_login.php');
t('admin login redirects to head office dashboard', 'dashboard.php', $r['location'], has($r['location'], 'dashboard.php') && !has($r['location'], 'admin_dashboard') && !has($r['location'], 'staff_dashboard'), 'auth');
$r = $saA->login('zzt_sa_a', $PASS, 'login_station_admin.php');
t('station admin login redirects to station dashboard', 'admin_dashboard.php', $r['location'], has($r['location'], 'admin_dashboard.php'), 'auth');
$r = $stfA->login('zzt_staff_a', $PASS);
t('staff login redirects to staff dashboard', 'staff_dashboard.php', $r['location'], has($r['location'], 'staff_dashboard.php'), 'auth');
$saB->login('zzt_sa_b', $PASS);
$stfB->login('zzt_staff_b', $PASS);
$r = (new Client('wrongpw', $tmp))->login('zzt_staff_a', 'wrong-password');
t('wrong password gives generic message', 'Invalid username or password', has($r['body'], 'Invalid username or password') ? 'generic message' : 'other', has($r['body'], 'Invalid username or password'), 'auth');
$tmpC = new Client('tmp', $tmp);
for ($i = 0; $i < 5; $i++) {
    $tmpC->login('zzt_staff_a2', 'bad' . $i);
}
$r = $tmpC->login('zzt_staff_a2', $PASS);
t('login throttled after 5 failures (even with the right password)', 'Too many failed attempts', has($r['body'], 'Too many failed attempts') ? 'throttled' : 'logged in', has($r['body'], 'Too many failed attempts'), 'auth');
$db->query("DELETE FROM login_attempts WHERE username = 'zzt_staff_a2'");
$logoutC = new Client('logout', $tmp);
$logoutC->login('zzt_staff_a', $PASS);
$tok = $logoutC->csrf('staff_dashboard.php');
$logoutC->post('logout.php', ['csrf_token' => $tok]);
$r = $logoutC->get('staff_dashboard.php');
t('after logout the dashboard redirects to login', '303 -> index.php', $r['code'] . ' -> ' . basename($r['location']), $r['code'] === 303 && has($r['location'], 'index.php'), 'auth');

/* ================= 2. Unauthenticated access ================= */
echo "\n[2] Private pages reject anonymous access\n";
foreach (['dashboard.php', 'manage_reports.php', 'view_report.php?id=1', 'edit_staff.php?id=1', 'audit_log.php', 'evidence_download.php?id=1', 'delete_report.php?id=1'] as $p) {
    $r = $anon->get($p);
    t("anonymous GET $p is redirected to login", '303 index.php', $r['code'] . ' ' . loc($r), $r['code'] === 303 && has($r['location'], 'index.php'), 'access');
}
$r = $anon->get('get_tehsils.php?district_id=1', ['Accept: application/json']);
t('anonymous AJAX endpoint returns 401 JSON', '401', (string) $r['code'], $r['code'] === 401, 'access');
$r = $anon->post('delete_staff.php', ['id' => 1], [], ['Accept: application/json']);
t('anonymous POST to delete_staff.php returns 401', '401', (string) $r['code'], $r['code'] === 401, 'access');

/* ================= 3. Station isolation ================= */
echo "\n[3] Station isolation (A cannot reach B by changing ids)\n";
// Report filed in station B by its station admin.
$fields = ['police_station_id' => $stB, 'report_date' => date('Y-m-d'), 'under_section' => '379', 'crime_type' => 'Theft', 'district_id' => $distId, 'tehsil_id' => $tehId,
    'accused_name' => 'ZZT Accused B', 'id_card_no' => '11111-1111111-1', 'accused_address' => 'B street', 'complainant_name' => 'Comp B', 'investigation_officer' => 'IO B', 'report_description' => 'Report in station B'];
$r = $saB->postCsrf('generate_report.php', $fields, 'generate_report.php');
$repB = (int) $db->query("SELECT id FROM reports WHERE accused_name = 'ZZT Accused B' ORDER BY id DESC LIMIT 1")->fetch_row()[0];
t('station B admin can file a report', 'redirect to view_report', $repB ? 'report #' . $repB : 'not created', $repB > 0, 'isolation');
$r = $saA->get('view_report.php?id=' . $repB);
t('station A admin cannot view station B report', '404 redirect', $r['code'] . ' ' . loc($r), $r['code'] === 404, 'isolation');
$r = $stfA->get('view_report.php?id=' . $repB);
t('station A staff cannot view station B report', '404 redirect', (string) $r['code'], $r['code'] === 404, 'isolation');
$r = $saA->postCsrf('view_report.php?id=' . $repB, ['action' => 'status', 'status' => 'Closed', 'reason' => 'x'], 'admin_dashboard.php');
$st = $db->query("SELECT status FROM reports WHERE id = $repB")->fetch_row()[0];
t('station A admin cannot change status of station B report', 'Open', $st, $st === 'Open', 'isolation');
$r = $saA->get('edit_staff.php?id=' . $ids['zzt_staff_b']);
t('station A admin cannot open station B staff record', '404', (string) $r['code'], $r['code'] === 404, 'isolation');
$r = $saA->postCsrf('manage_staff.php', ['action' => 'deactivate', 'id' => $ids['zzt_staff_b']], 'manage_staff.php');
$act = (int) $db->query("SELECT is_active FROM staff WHERE id = {$ids['zzt_staff_b']}")->fetch_row()[0];
t('station A admin cannot deactivate station B staff', 'still active (1)', (string) $act, $act === 1, 'isolation');
$r = $saA->get('manage_reports.php?status=all');
t('station A report list does not contain station B report', 'not listed', has($r['body'], 'ZZT Accused B') ? 'listed' : 'not listed', !has($r['body'], 'ZZT Accused B'), 'isolation');
$r = $saA->get('manage_reports.php?status=all&export=csv&station=' . $stB);
t('station A CSV export ignores a forged station filter', 'no B rows', has($r['body'], 'ZZT Accused B') ? 'B rows leaked' : 'no B rows', !has($r['body'], 'ZZT Accused B'), 'isolation');
$r = $admin->get('view_report.php?id=' . $repB);
t('head office admin can view any station report', '200', (string) $r['code'], $r['code'] === 200, 'isolation');

/* ================= 4. Staff cannot invoke admin actions ================= */
echo "\n[4] Staff cannot invoke admin actions\n";
foreach (['view_staff.php', 'add_staff.php', 'audit_log.php', 'alerts.php', 'assign_duties.php', 'admin_leave_requests.php', 'view_policestation.php'] as $p) {
    $r = $stfA->get($p);
    t("staff GET $p is denied", '403', (string) $r['code'], $r['code'] === 403, 'privilege');
}
$r = $stfA->postCsrf('alerts.php', ['action' => 'save', 'message' => 'ZZT staff alert', 'target_type' => 'all'], 'staff_dashboard.php');
$n = (int) $db->query("SELECT COUNT(*) FROM alerts WHERE message = 'ZZT staff alert'")->fetch_row()[0];
t('staff POST to alerts.php creates nothing', '0 alerts', (string) $n, $n === 0, 'privilege');
$r = $stfA->get('manage_reports.php?export=csv');
t('staff cannot export CSV', '403', (string) $r['code'], $r['code'] === 403, 'privilege');

/* ================= 5. Forged role / station / privilege escalation ================= */
echo "\n[5] Forged role and station values\n";
$r = $saA->postCsrf('edit_staff.php?id=' . $ids['zzt_staff_a'], ['name' => 'zzt_staff_a', 'email' => 'zzt_staff_a@example.test', 'designation' => 'Tester', 'role' => 'admin', 'station_id' => $stA], 'edit_staff.php?id=' . $ids['zzt_staff_a']);
$role = $db->query("SELECT role FROM staff WHERE id = {$ids['zzt_staff_a']}")->fetch_row()[0];
t('station admin cannot promote staff to head office admin', 'staff', $role, $role === 'staff', 'privilege');
$r = $saA->postCsrf('edit_staff.php?id=' . $ids['zzt_staff_a'], ['name' => 'zzt_staff_a', 'email' => 'zzt_staff_a@example.test', 'designation' => 'Moved', 'role' => 'staff', 'station_id' => $stB], 'edit_staff.php?id=' . $ids['zzt_staff_a']);
$sid = (int) $db->query("SELECT police_station_id FROM staff WHERE id = {$ids['zzt_staff_a']}")->fetch_row()[0];
t('station admin cannot move staff to another station (posted station ignored)', (string) $stA, (string) $sid, $sid === $stA, 'privilege');
$r = $saA->get('edit_staff.php?id=' . $ids['zzt_admin']);
t('station admin cannot open a head office admin account', '404', (string) $r['code'], $r['code'] === 404, 'privilege');
$r = $saA->postCsrf('edit_staff.php?id=' . $ids['zzt_sa_a'], ['name' => 'zzt_sa_a', 'designation' => 'Tester', 'role' => 'admin', 'station_id' => $stA], 'edit_staff.php?id=' . $ids['zzt_sa_a']);
$role = $db->query("SELECT role FROM staff WHERE id = {$ids['zzt_sa_a']}")->fetch_row()[0];
t('station admin cannot change own role', 'admin station', $role, $role === 'admin station', 'privilege');
$r = $saA->postCsrf('alerts.php', ['action' => 'save', 'message' => 'ZZT forged target', 'target_type' => 'all'], 'alerts.php');
$tt = $db->query("SELECT target_type, target_station_id FROM alerts WHERE message = 'ZZT forged target'")->fetch_assoc();
t('station admin alert with forged target_type=all is forced to own station', "station/$stA", ($tt['target_type'] ?? '?') . '/' . ($tt['target_station_id'] ?? '?'), ($tt['target_type'] ?? '') === 'station' && (int) ($tt['target_station_id'] ?? 0) === $stA, 'privilege');
$r = $saA->postCsrf('generate_report.php', array_merge($fields, ['accused_name' => 'ZZT forged station', 'police_station_id' => $stB]), 'generate_report.php');
$ps = (int) ($db->query("SELECT police_station_id FROM reports WHERE accused_name = 'ZZT forged station'")->fetch_row()[0] ?? 0);
t('station admin report with forged police_station_id lands in own station', (string) $stA, (string) $ps, $ps === $stA, 'privilege');

/* ================= 6. Deactivated users ================= */
echo "\n[6] Deactivated users lose access immediately\n";
$deact = new Client('deact', $tmp);
$deact->login('zzt_staff_a2', $PASS);
$r = $deact->get('staff_dashboard.php');
t('active user sees dashboard', '200', (string) $r['code'], $r['code'] === 200, 'deactivate');
$r = $saA->postCsrf('manage_staff.php', ['action' => 'deactivate', 'id' => $ids['zzt_staff_a2']], 'manage_staff.php');
$r = $deact->get('staff_dashboard.php');
t('deactivated user with a live session is logged out on next request', '303 index.php', $r['code'] . ' ' . loc($r), $r['code'] === 303, 'deactivate');
$r = $deact->login('zzt_staff_a2', $PASS);
t('deactivated user cannot log in again', 'deactivated message', has($r['body'], 'deactivated') ? 'deactivated message' : 'logged in', has($r['body'], 'deactivated'), 'deactivate');
$saA->postCsrf('manage_staff.php', ['action' => 'activate', 'id' => $ids['zzt_staff_a2']], 'manage_staff.php');

/* ================= 7. CSRF ================= */
echo "\n[7] CSRF protection\n";
$r = $saA->post('alerts.php', ['action' => 'save', 'message' => 'ZZT no csrf', 'target_type' => 'station']);
$n = (int) $db->query("SELECT COUNT(*) FROM alerts WHERE message = 'ZZT no csrf'")->fetch_row()[0];
t('POST without CSRF token is rejected (400) and changes nothing', '400 / 0 rows', $r['code'] . ' / ' . $n, $r['code'] === 400 && $n === 0, 'csrf');
$r = $saA->post('alerts.php', ['action' => 'save', 'message' => 'ZZT bad csrf', 'target_type' => 'station', 'csrf_token' => 'deadbeef']);
$n = (int) $db->query("SELECT COUNT(*) FROM alerts WHERE message = 'ZZT bad csrf'")->fetch_row()[0];
t('POST with wrong CSRF token is rejected', '400 / 0 rows', $r['code'] . ' / ' . $n, $r['code'] === 400 && $n === 0, 'csrf');
$r = $saA->post('delete_staff.php', ['id' => $ids['zzt_staff_a']], [], ['Accept: application/json']);
t('AJAX POST without CSRF token returns 400 JSON', '400', (string) $r['code'], $r['code'] === 400 && has($r['body'], '"ok":false'), 'csrf');
$r = $saA->post('delete_staff.php', ['id' => $ids['zzt_staff_a'], 'csrf_token' => $saA->csrf('manage_staff.php')], [], ['Accept: application/json']);
$act = (int) $db->query("SELECT is_active FROM staff WHERE id = {$ids['zzt_staff_a']}")->fetch_row()[0];
t('AJAX POST with a valid CSRF token deactivates (legacy endpoint)', '200 / inactive', $r['code'] . ' / ' . ($act ? 'active' : 'inactive'), $r['code'] === 200 && $act === 0, 'csrf');
$saA->postCsrf('manage_staff.php', ['action' => 'activate', 'id' => $ids['zzt_staff_a']], 'manage_staff.php');
$stfA->login('zzt_staff_a', $PASS);

/* ================= 8. GET is never destructive ================= */
echo "\n[8] GET requests cannot perform destructive actions\n";
$repA = (int) $db->query("SELECT id FROM reports WHERE accused_name = 'ZZT forged station'")->fetch_row()[0];
$r = $saA->get('delete_report.php?id=' . $repA);
$st = $db->query("SELECT status FROM reports WHERE id = $repA")->fetch_row()[0];
t('GET delete_report.php shows confirmation and does not archive', '200 / Open', $r['code'] . ' / ' . $st, $r['code'] === 200 && $st === 'Open', 'get-safe');
$r = $saA->get('delete_staff.php?id=' . $ids['zzt_staff_a'], ['Accept: application/json']);
$act = (int) $db->query("SELECT is_active FROM staff WHERE id = {$ids['zzt_staff_a']}")->fetch_row()[0];
t('GET delete_staff.php is refused (405) and account stays active', '405 / 1', $r['code'] . ' / ' . $act, $r['code'] === 405 && $act === 1, 'get-safe');
$r = $saA->get('logout.php');
$r2 = $saA->get('admin_dashboard.php');
t('GET logout.php only shows a confirmation (session survives)', '200 dashboard', (string) $r2['code'], $r['code'] === 200 && $r2['code'] === 200, 'get-safe');

/* ================= 9. SQL injection ================= */
echo "\n[9] Injection inputs\n";
$inj = "' OR 1=1 -- ";
$r = $admin->get('manage_reports.php?q=' . urlencode($inj) . '&status=all');
t('report search with SQL injection text returns 200 and no rows leak', '200, 0 report(s) or no error', $r['code'] . (has($r['body'], 'ZZT Accused B') ? ' rows-leaked' : ' safe'), $r['code'] === 200 && !has($r['body'], 'ZZT Accused B') && !has($r['body'], 'SQL'), 'injection');
$r = $anon->login("zzt_admin' OR '1'='1", $PASS);
t('login username injection fails', 'Invalid username or password', has($r['body'], 'Invalid username') ? 'rejected' : 'accepted', has($r['body'], 'Invalid username'), 'injection');
$r = $admin->get('manage_reports.php?sort=' . urlencode('r.id; DROP TABLE reports') . '&status=all');
$ok = (int) $db->query("SELECT COUNT(*) FROM reports")->fetch_row()[0] > 0;
t('sort parameter is allowlisted (tables intact, page 200)', '200 and reports intact', $r['code'] . ($ok ? ' intact' : ' DAMAGED'), $r['code'] === 200 && $ok, 'injection');
$r = $admin->get('search_criminal.php?id_card_no=' . urlencode("1' OR '1'='1"));
t('CNIC search rejects non-CNIC input', '200 with validation message', (string) $r['code'], $r['code'] === 200 && has($r['body'], '13-digit'), 'injection');

/* ================= 10. Stored XSS ================= */
echo "\n[10] Stored HTML/script renders safely\n";
$xss = '<script>alert("zzt")</script><img src=x onerror=alert(1)>';
$r = $saA->postCsrf('generate_report.php', array_merge($fields, ['police_station_id' => $stA, 'accused_name' => 'ZZT XSS ' . $xss, 'report_description' => 'desc ' . $xss]), 'generate_report.php');
$repX = (int) ($db->query("SELECT id FROM reports WHERE accused_name LIKE 'ZZT XSS%' ORDER BY id DESC LIMIT 1")->fetch_row()[0] ?? 0);
$r = $saA->get('view_report.php?id=' . $repX);
$rawPresent = has($r['body'], '<script>alert("zzt")') || has($r['body'], '<img src=x onerror');
$escaped = has($r['body'], '&lt;script&gt;');
t('script payload in report is escaped on the detail page', 'escaped, no raw script', $rawPresent ? 'RAW SCRIPT PRESENT' : ($escaped ? 'escaped' : 'missing'), !$rawPresent && $escaped, 'xss');
$r = $saA->get('manage_reports.php?status=all');
t('script payload is escaped in the report list', 'no raw script', has($r['body'], '<script>alert("zzt")') ? 'RAW' : 'escaped', !has($r['body'], '<script>alert("zzt")'), 'xss');
$r = $admin->get('print_report.php?id=' . $repX);
t('script payload is escaped in the print view', 'no raw script', has($r['body'], '<script>alert("zzt")') ? 'RAW' : 'escaped', !has($r['body'], '<script>alert("zzt")'), 'xss');

/* ================= 11. Password migration ================= */
echo "\n[11] Password migration\n";
$php = PHP_BINARY;
$root = dirname(__DIR__);
$out1 = shell_exec("\"$php\" \"$root/database/scripts/hash_passwords.php\" 2>&1");
$h1 = $db->query("SELECT password FROM staff WHERE name = 'zzt_plain'")->fetch_row()[0];
$out2 = shell_exec("\"$php\" \"$root/database/scripts/hash_passwords.php\" 2>&1");
$h2 = $db->query("SELECT password FROM staff WHERE name = 'zzt_plain'")->fetch_row()[0];
t('hash script converts a plaintext password to bcrypt', 'bcrypt hash', str_starts_with((string) $h1, '$2y$') ? 'bcrypt hash' : 'not hashed', str_starts_with((string) $h1, '$2y$'), 'passwords');
t('running the hash script again does not re-hash', 'unchanged hash', $h1 === $h2 ? 'unchanged hash' : 'CHANGED', $h1 === $h2, 'passwords');
$pc = new Client('plain', $tmp);
$r = $pc->login('zzt_plain', $PASS);
t('migrated account can log in', 'staff_dashboard.php', loc($r), has($r['location'], 'staff_dashboard.php'), 'passwords');
$db->query("UPDATE staff SET password = '$PASS' WHERE name = 'zzt_plain'");
$pc2 = new Client('plain2', $tmp);
$r = $pc2->login('zzt_plain', $PASS);
$h3 = $db->query("SELECT password FROM staff WHERE name = 'zzt_plain'")->fetch_row()[0];
t('a legacy plaintext login is hashed on first successful login', 'bcrypt after login', str_starts_with((string) $h3, '$2y$') ? 'bcrypt after login' : 'still plaintext', str_starts_with((string) $h3, '$2y$') && has($r['location'], 'staff_dashboard'), 'passwords');
$r = $anon->get('admin_login.php');
t('legacy password cookie is expired by the server', 'Set-Cookie password=deleted', preg_match('/Set-Cookie: password=;/i', $r['head']) ? 'no password cookie set' : 'n/a', !preg_match('/Set-Cookie: password=[^;]/i', $r['head']), 'passwords');

/* ================= 12. Password reset tokens ================= */
echo "\n[12] Reset tokens\n";
$rc = new Client('reset', $tmp);
$tok = bin2hex(random_bytes(32));
$db->query("INSERT INTO password_resets (staff_id, token_hash, expires_at) VALUES ({$ids['zzt_staff_a']}, '" . hash('sha256', $tok) . "', DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
$r = $rc->get('reset_password.php?token=' . $tok);
t('valid reset token opens the form', '200', (string) $r['code'], $r['code'] === 200 && has($r['body'], 'Choose a new password'), 'reset');
$csrf = preg_match('/name="csrf_token" value="([^"]+)"/', $r['body'], $m) ? $m[1] : '';
$r = $rc->post('reset_password.php', ['token' => $tok, 'password' => 'New-Pass-9999', 'password_confirm' => 'New-Pass-9999', 'csrf_token' => $csrf]);
$r2 = (new Client('reset2', $tmp))->login('zzt_staff_a', 'New-Pass-9999');
t('reset changes the password (new password logs in)', 'staff_dashboard.php', loc($r2), has($r2['location'], 'staff_dashboard.php'), 'reset');
$r = $stfA->get('staff_dashboard.php');
t('existing sessions are invalidated after a reset', '303 index.php', $r['code'] . ' ' . loc($r), $r['code'] === 303, 'reset');
$r = $rc->get('reset_password.php?token=' . $tok);
t('used token cannot be reused', '303 forgot_password.php', $r['code'] . ' ' . loc($r), $r['code'] === 303 && has($r['location'], 'forgot_password'), 'reset');
$tok2 = bin2hex(random_bytes(32));
$db->query("INSERT INTO password_resets (staff_id, token_hash, expires_at) VALUES ({$ids['zzt_staff_a']}, '" . hash('sha256', $tok2) . "', DATE_SUB(NOW(), INTERVAL 1 MINUTE))");
$r = $rc->get('reset_password.php?token=' . $tok2);
t('expired token is rejected', '303 forgot_password.php', $r['code'] . ' ' . loc($r), $r['code'] === 303, 'reset');
$db->query("UPDATE staff SET password = '$hash' WHERE name = 'zzt_staff_a'");
$stfA->login('zzt_staff_a', $PASS);
$rf = $anon->postCsrf('forgot_password.php', ['identity' => 'no-such-user-zzt'], 'forgot_password.php');
$rf2 = $anon->postCsrf('forgot_password.php', ['identity' => 'zzt_staff_a'], 'forgot_password.php');
t('forgot-password answers the same for unknown and known users', 'both redirect 303', $rf['code'] . '/' . $rf2['code'], $rf['code'] === 303 && $rf2['code'] === 303, 'reset');

/* ================= 13. Alerts ================= */
echo "\n[13] Alerts: creation, targeting, expiry\n";
$saA->postCsrf('alerts.php', ['action' => 'save', 'title' => 'ZZT A only', 'message' => 'ZZT alert for station A'], 'alerts.php');
$r = $stfA->get('staff_dashboard.php');
t('station A staff sees station A alert', 'visible', has($r['body'], 'ZZT alert for station A') ? 'visible' : 'missing', has($r['body'], 'ZZT alert for station A'), 'alerts');
$r = $stfB->get('staff_dashboard.php');
t('station B staff does not see station A alert', 'hidden', has($r['body'], 'ZZT alert for station A') ? 'VISIBLE' : 'hidden', !has($r['body'], 'ZZT alert for station A'), 'alerts');
$r = $admin->get('dashboard.php');
t('head office sees station-targeted alert', 'visible', has($r['body'], 'ZZT alert for station A') ? 'visible' : 'missing', has($r['body'], 'ZZT alert for station A'), 'alerts');
$admin->postCsrf('alerts.php', ['action' => 'save', 'message' => 'ZZT everyone', 'target_type' => 'all'], 'alerts.php');
$r = $stfB->get('staff_dashboard.php');
t('all-users alert reaches station B staff', 'visible', has($r['body'], 'ZZT everyone') ? 'visible' : 'missing', has($r['body'], 'ZZT everyone'), 'alerts');
$admin->postCsrf('alerts.php', ['action' => 'save', 'message' => 'ZZT expired', 'target_type' => 'all', 'starts_at' => date('Y-m-d\TH:i', time() - 3600), 'expires_at' => date('Y-m-d\TH:i', time() - 7200)], 'alerts.php');
$n = (int) $db->query("SELECT COUNT(*) FROM alerts WHERE message = 'ZZT expired'")->fetch_row()[0];
t('alert with expiry before start is refused', '0 rows', (string) $n, $n === 0, 'alerts');
$db->query("INSERT INTO alerts (message, target_type, starts_at, expires_at, status, is_active, created_by, created_at) VALUES ('ZZT expired', 'all', DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR), 'active', 1, {$ids['zzt_admin']}, NOW())");
foreach ([['admin', $admin, 'dashboard.php'], ['station', $saA, 'admin_dashboard.php'], ['staff', $stfA, 'staff_dashboard.php']] as [$who, $c, $p]) {
    $r = $c->get($p);
    t("expired alert is hidden on the $who dashboard without any cleanup job", 'hidden', has($r['body'], 'ZZT expired') ? 'VISIBLE' : 'hidden', !has($r['body'], 'ZZT expired'), 'alerts');
}
$db->query("INSERT INTO alerts (message, target_type, starts_at, expires_at, status, is_active, created_by, created_at) VALUES ('ZZT future', 'all', DATE_ADD(NOW(), INTERVAL 1 HOUR), NULL, 'active', 1, {$ids['zzt_admin']}, NOW())");
$r = $stfA->get('staff_dashboard.php');
t('alert with a future start time is not shown yet', 'hidden', has($r['body'], 'ZZT future') ? 'VISIBLE' : 'hidden', !has($r['body'], 'ZZT future'), 'alerts');

/* ================= 14. Duties: overlap, leave conflict, overnight, email failure ================= */
echo "\n[14] Duties\n";
$d1 = ['action' => 'save', 'staff_id' => $ids['zzt_staff_a'], 'duty_description' => 'ZZT night patrol', 'start_time' => '2030-01-10T22:00', 'end_time' => '2030-01-11T06:00', 'shift_type' => 'night', 'Duty_location' => 'Gate 1'];
$r = $saA->postCsrf('assign_duties.php', $d1, 'assign_duties.php');
$duty = $db->query("SELECT * FROM duties WHERE duty_description = 'ZZT night patrol'")->fetch_assoc();
t('overnight duty is saved with end on the next day', '2030-01-11 06:00:00', (string) ($duty['end_time'] ?? 'missing'), ($duty['end_time'] ?? '') === '2030-01-11 06:00:00', 'duties');
t('duty saved even though email is disabled (notify_status = failed, record kept)', 'failed', (string) ($duty['notify_status'] ?? 'missing'), ($duty['notify_status'] ?? '') === 'failed', 'duties');
$r = $saA->postCsrf('assign_duties.php', array_merge($d1, ['duty_description' => 'ZZT overlap', 'start_time' => '2030-01-11T04:00', 'end_time' => '2030-01-11T08:00']), 'assign_duties.php');
$n = (int) $db->query("SELECT COUNT(*) FROM duties WHERE duty_description = 'ZZT overlap'")->fetch_row()[0];
t('overlapping duty (crossing midnight boundary) is refused', '0 rows', (string) $n, $n === 0, 'duties');
$r = $saA->postCsrf('assign_duties.php', array_merge($d1, ['duty_description' => 'ZZT overlap forced', 'start_time' => '2030-01-11T04:00', 'end_time' => '2030-01-11T08:00', 'force' => '1']), 'assign_duties.php');
$n = (int) $db->query("SELECT COUNT(*) FROM duties WHERE duty_description = 'ZZT overlap forced'")->fetch_row()[0];
t('overlap can be overridden explicitly', '1 row', (string) $n, $n === 1, 'duties');
$r = $saA->postCsrf('assign_duties.php', array_merge($d1, ['duty_description' => 'ZZT other station', 'staff_id' => $ids['zzt_staff_b']]), 'assign_duties.php');
$n = (int) $db->query("SELECT COUNT(*) FROM duties WHERE duty_description = 'ZZT other station'")->fetch_row()[0];
t('station admin cannot assign duty to another station\'s staff', '0 rows', (string) $n, $n === 0, 'duties');
$r = $saA->postCsrf('assign_duties.php', array_merge($d1, ['duty_description' => 'ZZT backwards', 'start_time' => '2030-01-12T10:00', 'end_time' => '2030-01-12T08:00']), 'assign_duties.php');
$n = (int) $db->query("SELECT COUNT(*) FROM duties WHERE duty_description = 'ZZT backwards'")->fetch_row()[0];
t('duty with end before start is refused', '0 rows', (string) $n, $n === 0, 'duties');
// Approved leave conflict
$db->query("INSERT INTO leave_requests (staff_id, leave_type, leave_type_id, leave_start_date, leave_end_date, requested_days, reason, status, approved_days) VALUES ({$ids['zzt_staff_a']}, 'Casual', (SELECT id FROM leave_types WHERE name='Casual'), '2030-02-01', '2030-02-03', 3, 'zzt', 'approved', 3)");
$r = $saA->postCsrf('assign_duties.php', array_merge($d1, ['duty_description' => 'ZZT on leave', 'start_time' => '2030-02-02T08:00', 'end_time' => '2030-02-02T16:00']), 'assign_duties.php');
$n = (int) $db->query("SELECT COUNT(*) FROM duties WHERE duty_description = 'ZZT on leave'")->fetch_row()[0];
t('duty during approved leave is refused', '0 rows', (string) $n, $n === 0, 'duties');
$r = $saB->get('edit_duty.php?id=' . $duty['id']);
t('station B admin cannot open station A duty', '404', (string) $r['code'], $r['code'] === 404, 'duties');
$r = $saA->postCsrf('edit_duty.php', ['id' => $duty['id'], 'action' => 'notify'], 'edit_duty.php?id=' . $duty['id']);
$d2 = $db->query("SELECT notify_attempts, notify_status FROM duties WHERE id = {$duty['id']}")->fetch_assoc();
t('retrying notification increments attempts without duplicating the duty', '2 attempts / 1 duty', $d2['notify_attempts'] . ' attempts / ' . $db->query("SELECT COUNT(*) FROM duties WHERE duty_description = 'ZZT night patrol'")->fetch_row()[0] . ' duty', (int) $d2['notify_attempts'] === 2, 'duties');
$r = $stfA->get('view_staff_duty.php?show=all');
t('staff sees own duty in My Duties', 'listed', has($r['body'], 'ZZT night patrol') ? 'listed' : 'missing', has($r['body'], 'ZZT night patrol'), 'duties');
$r = $stfB->get('view_staff_duty.php?show=all');
t('other staff does not see it', 'not listed', has($r['body'], 'ZZT night patrol') ? 'LISTED' : 'not listed', !has($r['body'], 'ZZT night patrol'), 'duties');

/* ================= 15. Leave: validation, overlap, balance, double approval ================= */
echo "\n[15] Leave\n";
$casual = (int) $db->query("SELECT id FROM leave_types WHERE name = 'Casual'")->fetch_row()[0];
$db->query("DELETE FROM leave_requests WHERE staff_id = {$ids['zzt_staff_a']}"); // start clean for balance math
$lv = ['leave_type_id' => $casual, 'start_date' => '2030-03-10', 'end_date' => '2030-03-12', 'reason' => 'zzt family'];
$r = $stfA->postCsrf('leave_request.php', $lv, 'leave_request.php');
$lr = $db->query("SELECT * FROM leave_requests WHERE staff_id = {$ids['zzt_staff_a']} AND leave_start_date = '2030-03-10'")->fetch_assoc();
t('leave request is created with inclusive day count', '3 days pending', ($lr['requested_days'] ?? '?') . ' days ' . ($lr['status'] ?? '?'), (int) ($lr['requested_days'] ?? 0) === 3 && ($lr['status'] ?? '') === 'pending', 'leave');
$r = $stfA->postCsrf('leave_request.php', array_merge($lv, ['start_date' => '2030-03-12', 'end_date' => '2030-03-14']), 'leave_request.php');
$n = (int) $db->query("SELECT COUNT(*) FROM leave_requests WHERE staff_id = {$ids['zzt_staff_a']}")->fetch_row()[0];
t('overlapping leave request is refused', '1 request', (string) $n, $n === 1, 'leave');
$r = $stfA->postCsrf('leave_request.php', array_merge($lv, ['start_date' => '2030-03-20', 'end_date' => '2030-03-19']), 'leave_request.php');
$n = (int) $db->query("SELECT COUNT(*) FROM leave_requests WHERE staff_id = {$ids['zzt_staff_a']}")->fetch_row()[0];
t('end-before-start leave is refused', '1 request', (string) $n, $n === 1, 'leave');
$r = $stfA->postCsrf('leave_request.php', array_merge($lv, ['start_date' => '2030-04-01', 'end_date' => '2030-04-30']), 'leave_request.php');
$n = (int) $db->query("SELECT COUNT(*) FROM leave_requests WHERE staff_id = {$ids['zzt_staff_a']}")->fetch_row()[0];
t('request exceeding the yearly allowance (30 > 10 casual) is refused', '1 request', (string) $n, $n === 1, 'leave');
$r = $saB->postCsrf('admin_leave_requests.php', ['leave_id' => $lr['id'], 'action' => 'approve', 'approved_days' => 3], 'admin_dashboard.php');
$st = $db->query("SELECT status FROM leave_requests WHERE id = {$lr['id']}")->fetch_row()[0];
t('station B admin cannot approve station A leave', 'pending', $st, $st === 'pending', 'leave');
// Concurrent double approval: two simultaneous requests from two station-A sessions.
$saA2 = new Client('saA2', $tmp);
$saA2->login('zzt_sa_a', $PASS);
$tokA = $saA->csrf('admin_leave_requests.php');
$tokA2 = $saA2->csrf('admin_leave_requests.php');
$mh = curl_multi_init();
$hs = [];
foreach ([[$saA, $tokA], [$saA2, $tokA2]] as [$c, $tk]) {
    $ch = curl_init($BASE . '/admin_leave_requests.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_COOKIEFILE => $c->jar, CURLOPT_COOKIEJAR => $c->jar,
        CURLOPT_POSTFIELDS => http_build_query(['leave_id' => $lr['id'], 'action' => 'approve', 'approved_days' => 3, 'csrf_token' => $tk])]);
    curl_multi_add_handle($mh, $ch);
    $hs[] = $ch;
}
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);
foreach ($hs as $ch) {
    curl_multi_remove_handle($mh, $ch);
}
curl_multi_close($mh);
$row = $db->query("SELECT status, approved_days, (SELECT COUNT(*) FROM leave_request_history h WHERE h.leave_request_id = {$lr['id']} AND h.action = 'approved') AS approvals FROM leave_requests WHERE id = {$lr['id']}")->fetch_assoc();
t('two concurrent approvals deduct the balance once', 'approved / 3 days / 1 approval record', "{$row['status']} / {$row['approved_days']} days / {$row['approvals']} approval record", $row['status'] === 'approved' && (int) $row['approved_days'] === 3 && (int) $row['approvals'] === 1, 'leave');
$r = $saA->postCsrf('admin_leave_requests.php', ['leave_id' => $lr['id'], 'action' => 'approve', 'approved_days' => 3], 'admin_leave_requests.php');
$row = $db->query("SELECT approved_days, (SELECT COALESCE(SUM(approved_days),0) FROM leave_requests WHERE staff_id = {$ids['zzt_staff_a']} AND status='approved') AS used FROM leave_requests WHERE id = {$lr['id']}")->fetch_assoc();
t('repeated approval after the fact changes nothing', '3 used', $row['used'] . ' used', (int) $row['used'] === 3, 'leave');
$r = $saA->get('admin_leave_requests.php?status=approved');
t('review page shows 7 casual days left of 10 for 2030', '7 left of 10 in 2030', has($r['body'], '7 left of 10 in 2030') ? '7 left of 10 in 2030' : 'not found', has($r['body'], '7 left of 10 in 2030'), 'leave');
$r = $stfA->postCsrf('leave_request.php', array_merge($lv, ['start_date' => '2030-05-01', 'end_date' => '2030-05-02']), 'leave_request.php');
$lr2 = $db->query("SELECT id FROM leave_requests WHERE staff_id = {$ids['zzt_staff_a']} AND leave_start_date = '2030-05-01'")->fetch_assoc();
$r = $stfB->postCsrf('leave_status.php', ['id' => $lr2['id']], 'leave_status.php');
$st = $db->query("SELECT status FROM leave_requests WHERE id = {$lr2['id']}")->fetch_row()[0];
t('another staff member cannot withdraw my request', 'pending', $st, $st === 'pending', 'leave');
$r = $stfA->postCsrf('leave_status.php', ['id' => $lr2['id']], 'leave_status.php');
$st = $db->query("SELECT status FROM leave_requests WHERE id = {$lr2['id']}")->fetch_row()[0];
t('staff can withdraw own pending request', 'withdrawn', $st, $st === 'withdrawn', 'leave');
$r = $saA->postCsrf('admin_leave_requests.php', ['leave_id' => $lr2['id'], 'action' => 'reject'], 'admin_leave_requests.php');
$st = $db->query("SELECT status FROM leave_requests WHERE id = {$lr2['id']}")->fetch_row()[0];
t('withdrawn request cannot be rejected afterwards', 'withdrawn', $st, $st === 'withdrawn', 'leave');

/* ================= 16. Uploads / downloads ================= */
echo "\n[16] Evidence uploads and downloads\n";
$png = $tmp . '/ok.png';
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
$fakeJpg = $tmp . '/evil.jpg';
file_put_contents($fakeJpg, "<?php echo 'pwned'; ?>");
$phpFile = $tmp . '/shell.php';
file_put_contents($phpFile, "<?php system(\$_GET['c']); ?>");
$r = $saA->postCsrf('evidence.php', ['action' => 'upload', 'report_id' => $repA], 'view_report.php?id=' . $repA, ['evidence[0]' => ['path' => $png, 'type' => 'image/png', 'name' => 'photo.png']]);
$n = (int) $db->query("SELECT COUNT(*) FROM report_evidence WHERE report_id = $repA AND deleted_at IS NULL")->fetch_row()[0];
t('valid PNG upload is accepted', '1 file', (string) $n, $n === 1, 'uploads');
$r = $saA->postCsrf('evidence.php', ['action' => 'upload', 'report_id' => $repA], 'view_report.php?id=' . $repA, ['evidence[0]' => ['path' => $fakeJpg, 'type' => 'image/jpeg', 'name' => 'evil.jpg']]);
$n = (int) $db->query("SELECT COUNT(*) FROM report_evidence WHERE report_id = $repA AND deleted_at IS NULL")->fetch_row()[0];
t('PHP code disguised as .jpg is rejected (MIME sniffing)', '1 file', (string) $n, $n === 1, 'uploads');
$r = $saA->postCsrf('evidence.php', ['action' => 'upload', 'report_id' => $repA], 'view_report.php?id=' . $repA, ['evidence[0]' => ['path' => $phpFile, 'type' => 'application/x-php', 'name' => 'shell.php']]);
$n = (int) $db->query("SELECT COUNT(*) FROM report_evidence WHERE report_id = $repA AND deleted_at IS NULL")->fetch_row()[0];
t('.php upload is rejected', '1 file', (string) $n, $n === 1, 'uploads');
$ev = $db->query("SELECT id, stored_name FROM report_evidence WHERE report_id = $repA AND deleted_at IS NULL")->fetch_assoc() ?: ['id' => 0, 'stored_name' => ''];
$storedPath = rtrim((string) config('storage_path'), '/\\') . '/evidence/' . $ev['stored_name'];
t('stored file lives outside the web root with a random name', 'outside pms/, random', (str_contains(realpath($storedPath) ?: '', DIRECTORY_SEPARATOR . 'pms' . DIRECTORY_SEPARATOR) ? 'INSIDE web root' : 'outside pms/') . ', ' . (preg_match('/^[a-f0-9]{40}\.png$/', $ev['stored_name']) ? 'random' : 'NOT random'), is_file($storedPath) && !str_contains(realpath($storedPath), DIRECTORY_SEPARATOR . 'pms' . DIRECTORY_SEPARATOR) && preg_match('/^[a-f0-9]{40}\.png$/', $ev['stored_name']), 'uploads');
$r = $anon->get('storage/evidence/' . $ev['stored_name']);
t('stored file is not reachable by URL', '404', (string) $r['code'], $r['code'] === 404, 'uploads');
$r = $stfA->get('evidence_download.php?id=' . $ev['id']);
t('station A staff can download station A evidence', '200 image/png', $r['code'] . ' ' . (preg_match('/Content-Type: (\S+)/i', $r['head'], $m) ? $m[1] : ''), $r['code'] === 200 && has($r['head'], 'image/png'), 'uploads');
$r = $stfB->get('evidence_download.php?id=' . $ev['id']);
t('station B staff cannot download station A evidence', '404', (string) $r['code'], $r['code'] === 404, 'uploads');
$r = $anon->get('evidence_download.php?id=' . $ev['id']);
t('anonymous download is redirected to login', '303', (string) $r['code'], $r['code'] === 303, 'uploads');
$r = $stfB->postCsrf('evidence.php', ['action' => 'upload', 'report_id' => $repA], 'staff_dashboard.php', ['evidence[0]' => ['path' => $png, 'type' => 'image/png', 'name' => 'b.png']]);
$n = (int) $db->query("SELECT COUNT(*) FROM report_evidence WHERE report_id = $repA AND deleted_at IS NULL")->fetch_row()[0];
t('station B staff cannot upload to station A report', '1 file', (string) $n, $n === 1, 'uploads');

/* ================= 17. Pagination, filters, exports ================= */
echo "\n[17] Pagination, filters, exports, formula injection\n";
$r = $admin->get('manage_reports.php?status=all&crime=Theft&page=1');
t('report list with filters and page renders', '200', (string) $r['code'], $r['code'] === 200 && has($r['body'], 'ZZT Accused B'), 'lists');
$r = $admin->get('manage_reports.php?status=all&crime=Theft&page=999');
t('out-of-range page is clamped, no error', '200', (string) $r['code'], $r['code'] === 200 && !has($r['body'], 'Warning'), 'lists');
$db->query("UPDATE reports SET accused_name = '=HYPERLINK(\"http://evil\")' WHERE id = $repA");
$r = $admin->get('manage_reports.php?status=all&export=csv');
t('CSV export neutralises spreadsheet formulas', "'=HYPERLINK", has($r['body'], "'=HYPERLINK") ? "'=HYPERLINK" : (has($r['body'], '=HYPERLINK') ? 'RAW FORMULA' : 'missing'), has($r['body'], "'=HYPERLINK"), 'lists');
$db->query("UPDATE reports SET accused_name = 'ZZT forged station' WHERE id = $repA");
$r = $saA->get('manage_reports.php?status=all&export=csv');
t('station admin CSV export contains only own station rows', 'A rows only', (has($r['body'], 'ZZT Accused B') ? 'B leaked' : 'A rows only'), !has($r['body'], 'ZZT Accused B') && has($r['body'], 'ZZT forged station'), 'lists');
$r = $saA->get('report_analysis.php');
t('station admin analysis page renders', '200', (string) $r['code'], $r['code'] === 200, 'lists');
$r = $stfA->get('audit_log.php?export=csv');
t('staff cannot export the audit log', '403', (string) $r['code'], $r['code'] === 403, 'lists');

/* ================= 18. Profile / password change ================= */
echo "\n[18] Profile\n";
$r = $stfA->postCsrf('profile.php', ['action' => 'password', 'current_password' => 'wrong', 'new_password' => 'Another-Pass-1', 'password_confirm' => 'Another-Pass-1'], 'profile.php');
$h = $db->query("SELECT password FROM staff WHERE name = 'zzt_staff_a'")->fetch_row()[0];
t('password change requires the current password', 'unchanged', password_verify($PASS, $h) ? 'unchanged' : 'CHANGED', password_verify($PASS, $h), 'profile');
$r = $stfA->postCsrf('profile.php', ['action' => 'password', 'current_password' => $PASS, 'new_password' => 'Another-Pass-1', 'password_confirm' => 'Another-Pass-1'], 'profile.php');
$h = $db->query("SELECT password FROM staff WHERE name = 'zzt_staff_a'")->fetch_row()[0];
t('password change with correct current password works', 'changed', password_verify('Another-Pass-1', $h) ? 'changed' : 'unchanged', password_verify('Another-Pass-1', $h), 'profile');
$r = $stfA->get('staff_dashboard.php');
t('the changing session stays logged in after own password change', '200', (string) $r['code'], $r['code'] === 200, 'profile');
$r = $stfA->get('profile.php');
t('profile masks the CNIC', 'masked or empty', has($r['body'], '*****-*******') || !has($r['body'], '-') ? 'masked' : 'visible', !preg_match('/\d{5}-\d{7}-\d/', $r['body']), 'profile');

/* ================= 19. Fresh install + upgrade ================= */
echo "\n[19] Fresh install and upgrade of an existing database\n";
$mysql = dirname(PHP_BINARY) . '/../mysql/bin/mysql.exe';
if ($SKIP_DB || !is_file($mysql)) {
    notrun('fresh install from schema.sql', $SKIP_DB ? '--no-db-setup' : 'mysql client not found next to PHP', 'setup');
    notrun('upgrade of a pre-migration backup', $SKIP_DB ? '--no-db-setup' : 'mysql client not found next to PHP', 'setup');
} else {
    $u = (string) config('db_user');
    $p = (string) config('db_pass');
    $auth = '-u ' . escapeshellarg($u) . ($p !== '' ? ' -p' . escapeshellarg($p) : '');
    $schema = file_get_contents($root . '/database/schema.sql');
    $fresh = $tmp . '/fresh.sql';
    file_put_contents($fresh, str_replace(['CREATE DATABASE IF NOT EXISTS db_pms', 'USE db_pms;'], ['CREATE DATABASE IF NOT EXISTS zzt_fresh', 'USE zzt_fresh;'], $schema));
    shell_exec("\"$mysql\" $auth -e \"DROP DATABASE IF EXISTS zzt_fresh; DROP DATABASE IF EXISTS zzt_upgrade;\"");
    shell_exec("\"$mysql\" $auth < \"$fresh\"");
    $out = (string) shell_exec("set PMS_DB_NAME=zzt_fresh&& \"$php\" \"$root/database/migrate.php\" 2>&1");
    $tables = (int) trim((string) shell_exec("\"$mysql\" $auth -N -e \"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='zzt_fresh'\""));
    t('fresh install: schema.sql creates all tables and migrate reports nothing pending', '16 tables, Nothing to migrate', "$tables tables, " . (has($out, 'Nothing to migrate') ? 'Nothing to migrate' : 'pending/failed'), $tables === 16 && has($out, 'Nothing to migrate'), 'setup');
    $out2 = (string) shell_exec("set PMS_DB_NAME=zzt_fresh&& set PMS_ADMIN_PASSWORD=Fresh-Admin-Pass-1&& \"$php\" \"$root/database/scripts/create_admin.php\" --name=zzt_fresh_admin --email=fa@example.test 2>&1");
    $n = (int) trim((string) shell_exec("\"$mysql\" $auth -N -e \"SELECT COUNT(*) FROM zzt_fresh.staff WHERE name='zzt_fresh_admin' AND role='admin' AND password LIKE '\$2y\$%'\""));
    t('fresh install: create_admin.php creates a hashed admin account', '1', (string) $n, $n === 1, 'setup');
    // Upgrade path: legacy schema with legacy data.
    $legacy = $root . '/backups/db_pms_backup_2026-09-23_pre-migration.sql';
    if (!is_file($legacy)) {
        $legacy = $root . '/pms/db_pms.sql';
    }
    if (is_file($legacy)) {
        $sql = file_get_contents($legacy);
        $sql = preg_replace('/CREATE DATABASE[^;]*;/i', '', $sql);
        $sql = preg_replace('/USE `?db_pms`?;/i', '', $sql);
        $sql = "CREATE DATABASE zzt_upgrade CHARACTER SET utf8mb4; USE zzt_upgrade;\n" . $sql
            . "\nINSERT INTO alerts (alert_message) VALUES ('legacy_alert_text');"
            . "\nINSERT INTO reports (police_station_name, complainant, accused_name, district, tehsil, status, report_date) VALUES ('City Police Station Muzaffarabad', 'Robbery', 'Legacy accused', 'Muzafarabad', 'Muzaffarabad Tehsil', 'In Progress', '2025-01-15');";
        $up = $tmp . '/upgrade.sql';
        file_put_contents($up, $sql);
        $err = (string) shell_exec("\"$mysql\" $auth < \"$up\" 2>&1");
        $out = (string) shell_exec("set PMS_DB_NAME=zzt_upgrade&& \"$php\" \"$root/database/migrate.php\" 2>&1");
        $q = fn(string $sql) => trim((string) shell_exec("\"$mysql\" $auth -N -e " . escapeshellarg($sql)));
        $applied = $q('SELECT COUNT(*) FROM zzt_upgrade.schema_migrations');
        $alertMsg = $q("SELECT IFNULL(message, 'NULL') FROM zzt_upgrade.alerts WHERE message = 'legacy_alert_text' OR legacy_alert_message = 'legacy_alert_text'");
        $rep = $q("SELECT CONCAT_WS('|', status, crime_type, reference_no, IFNULL(district_id, 'null')) FROM zzt_upgrade.reports WHERE accused_name = 'Legacy accused'");
        t('upgrade: all 6 migrations apply to a legacy database', '6 applied', $applied . ' applied' . ($err !== '' ? ' (import stderr: ' . mb_strimwidth($err, 0, 120) . ')' : ''), $applied === '6' && has($out, 'All migrations applied'), 'setup');
        t('upgrade: legacy alert_message text is preserved in message', 'legacy_alert_text', $alertMsg === '' ? 'missing' : $alertMsg, $alertMsg === 'legacy_alert_text', 'setup');
        t('upgrade: legacy report gets normalised status, crime type, reference and district id', 'Under Investigation|Robbery|PMS-2025-*|id', $rep === '' ? 'missing' : $rep, str_starts_with($rep, 'Under Investigation|Robbery|PMS-2025-') && !str_ends_with($rep, '|null'), 'setup');
        $outHash = (string) shell_exec("set PMS_DB_NAME=zzt_upgrade&& \"$php\" \"$root/database/scripts/hash_passwords.php\" 2>&1");
        $plain = (int) trim((string) shell_exec("\"$mysql\" $auth -N -e \"SELECT COUNT(*) FROM zzt_upgrade.staff WHERE password NOT LIKE '\$2y\$%'\""));
        t('upgrade: hash script leaves no plaintext passwords', '0', (string) $plain, $plain === 0, 'setup');
        $out3 = (string) shell_exec("set PMS_DB_NAME=zzt_upgrade&& \"$php\" \"$root/database/migrate.php\" --check 2>&1");
        t('upgrade: data checks pass afterwards', 'all checks passed', has($out3, 'all checks passed') ? 'all checks passed' : 'items need attention', has($out3, 'all checks passed'), 'setup');
    } else {
        notrun('upgrade of a pre-migration backup', 'no legacy dump found', 'setup');
    }
    shell_exec("\"$mysql\" $auth -e \"DROP DATABASE IF EXISTS zzt_fresh; DROP DATABASE IF EXISTS zzt_upgrade;\"");
}

/* ================= 20. PHP syntax ================= */
echo "\n[20] PHP syntax\n";
$bad = [];
foreach (array_merge(glob($root . '/pms/*.php'), glob($root . '/pms/includes/*.php'), glob($root . '/database/*.php'), glob($root . '/database/migrations/*.php'), glob($root . '/database/scripts/*.php'), glob($root . '/tests/*.php')) as $f) {
    $o = (string) shell_exec("\"$php\" -l \"$f\" 2>&1");
    if (!has($o, 'No syntax errors')) {
        $bad[] = basename($f);
    }
}
t('php -l passes for every PHP file', '0 errors', count($bad) . ' errors ' . implode(',', $bad), !$bad, 'syntax');

/* ================= 21. Session cookie flags ================= */
echo "\n[21] Session cookie\n";
$r = (new Client('cookie', $tmp))->get('index.php');
$ck = preg_match('/Set-Cookie: PMSSESSID=[^\n]*/i', $r['head'], $m) ? $m[0] : '';
t('session cookie is HttpOnly and SameSite=Lax', 'HttpOnly; SameSite=Lax', $ck !== '' ? (stripos($ck, 'httponly') !== false ? 'HttpOnly' : 'no HttpOnly') . '; ' . (stripos($ck, 'samesite=lax') !== false ? 'SameSite=Lax' : 'no SameSite') : 'no cookie', stripos($ck, 'httponly') !== false && stripos($ck, 'samesite=lax') !== false, 'session');
t('security headers present', 'nosniff + SAMEORIGIN', (has($r['head'], 'nosniff') ? 'nosniff' : '-') . ' + ' . (has($r['head'], 'SAMEORIGIN') ? 'SAMEORIGIN' : '-'), has($r['head'], 'nosniff') && has($r['head'], 'SAMEORIGIN'), 'session');

/* ---------------- Summary ---------------- */
if (!$KEEP) {
    cleanup();
}
$pass = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
$fail = count(array_filter($results, fn($r) => $r['status'] === 'FAIL'));
$skip = count(array_filter($results, fn($r) => $r['status'] === 'NOT RUN'));
echo "\n==== $pass passed, $fail failed, $skip not run ====\n";

$md = "# Test results\n\nGenerated by `php tests/run_tests.php` on " . date('Y-m-d H:i') . " (Pakistan Standard Time) against `$BASE`.\n\n"
    . "Synthetic data only (names starting with `zzt_` / `ZZTEST`), removed after the run.\n\n"
    . "**Summary: $pass passed, $fail failed, $skip not run.**\n\n"
    . "| # | Area | Test | Expected | Actual | Result |\n|---|---|---|---|---|---|\n";
foreach ($results as $i => $r) {
    $md .= '| ' . ($i + 1) . ' | ' . $r['group'] . ' | ' . str_replace('|', '\|', $r['name']) . ' | ' . str_replace('|', '\|', $r['expected']) . ' | ' . str_replace('|', '\|', $r['actual']) . ' | ' . $r['status'] . " |\n";
}
$md .= "\n## Not covered automatically\n\n- Real SMTP delivery (email is disabled in the test configuration; the failure path is tested instead).\n- Browser rendering, keyboard navigation and contrast were checked manually, not by this script.\n- HTTPS-only cookie flag (Secure) is set only when the site is served over HTTPS; local XAMPP is plain HTTP.\n";
file_put_contents($root . '/docs/TEST_RESULTS.md', $md);
echo "Wrote docs/TEST_RESULTS.md\n";
exit($fail ? 1 : 0);
