<?php
/**
 * Authentication and role checks.
 *
 * Roles (staff.role): 'admin' = head office, 'admin station' = station in-charge, 'staff' = officer.
 * The logged-in user is re-read from the database on every request so that a
 * deactivated account, a changed role/station, or a password reset takes effect
 * immediately (session_version must match).
 */
declare(strict_types=1);

const ROLE_ADMIN   = 'admin';
const ROLE_STATION = 'admin station';
const ROLE_STAFF   = 'staff';
const ROLES        = [ROLE_ADMIN, ROLE_STATION, ROLE_STAFF];

/** Pages reachable without a login. */
const PUBLIC_ROUTES = [
    'index.php', 'admin_login.php', 'login_station_admin.php', 'login.php',
    'forgot_password.php', 'reset_password.php', 'logout.php',
];

/** Returns the logged-in user row (fresh from DB) or null. Cached per request. */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    $id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    if ($id <= 0) {
        return null;
    }
    $row = db_one(
        'SELECT s.id, s.name, s.email, s.designation, s.role, s.police_station_id, s.police_station_name,
                s.id_card_no, s.is_active, s.session_version, s.last_login_at,
                ps.police_station_name AS station_name, ps.district_id, ps.tehsil_id,
                d.name AS district_name, t.name AS tehsil_name
         FROM staff s
         LEFT JOIN police_stations ps ON ps.id = s.police_station_id
         LEFT JOIN districts d ON d.id = ps.district_id
         LEFT JOIN tehsils t ON t.id = ps.tehsil_id
         WHERE s.id = ?',
        'i',
        [$id]
    );
    if (!$row || (int) $row['is_active'] !== 1 || (int) $row['session_version'] !== (int) ($_SESSION['session_version'] ?? -1)) {
        // Account deactivated, deleted, or its sessions were invalidated.
        $_SESSION = [];
        return null;
    }
    $row['station_name'] = $row['station_name'] ?? $row['police_station_name'];
    $user = $row;
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function user_role(): ?string
{
    return current_user()['role'] ?? null;
}

function is_admin(): bool
{
    return user_role() === ROLE_ADMIN;
}
function is_station_admin(): bool
{
    return user_role() === ROLE_STATION;
}
function is_staff(): bool
{
    return user_role() === ROLE_STAFF;
}

/** Station the current user belongs to (null for head office admin without a station). */
function user_station_id(): ?int
{
    $u = current_user();
    return isset($u['police_station_id']) ? (int) $u['police_station_id'] : null;
}

function home_for_role(?string $role): string
{
    switch ($role) {
        case ROLE_ADMIN:   return 'dashboard.php';
        case ROLE_STATION: return 'admin_dashboard.php';
        default:           return 'staff_dashboard.php';
    }
}

function wants_json(): bool
{
    return (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json')
        || isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        || (strpos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') === 0);
}

/** Require any logged-in user. */
function require_login(): array
{
    $u = current_user();
    if ($u === null) {
        if (wants_json()) {
            json_response(['ok' => false, 'error' => 'Authentication required.'], 401);
        }
        $_SESSION['_after_login'] = $_SERVER['REQUEST_URI'] ?? null;
        flash('warning', 'Please log in to continue.');
        redirect('index.php');
    }
    return $u;
}

/** Require one of the given roles. */
function require_role(array $roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        deny('You do not have permission to open that page.');
    }
    return $u;
}

/** Render a full error page with a real HTTP status code and stop. */
function error_page(int $code, string $title, string $message): void
{
    if (wants_json()) {
        json_response(['ok' => false, 'error' => $message], $code);
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    $u = current_user();
    $home = app_url(home_for_role($u['role'] ?? null));
    $pageTitle = $title;
    $layoutPublic = $u === null;
    require PMS_ROOT . '/includes/layout_top.php';
    echo '<div class="card" style="max-width:560px;margin:0 auto"><div class="card-body text-center py-5">'
        . '<div class="display-6 mb-2">' . (int) $code . '</div>'
        . '<h2 class="h5">' . e($title) . '</h2><p class="text-muted">' . e($message) . '</p>'
        . '<a class="btn btn-navy" href="' . e($u ? $home : app_url('index.php')) . '">' . ($u ? 'Go to my dashboard' : 'Go to sign in') . '</a></div></div>';
    require PMS_ROOT . '/includes/layout_bottom.php';
    exit;
}

/** Stop the request with 403. */
function deny(string $message = 'Access denied.'): void
{
    error_page(403, 'Access denied', $message);
}

/** Stop the request with 404 (record missing, or not visible to this user - the two are deliberately indistinguishable). */
function not_found(string $message = 'The requested record was not found.'): void
{
    error_page(404, 'Not found', $message);
}

/* ---------------- Login / logout ---------------- */

const LOGIN_MAX_ATTEMPTS = 5;      // per username or IP
const LOGIN_WINDOW_MIN   = 15;     // minutes

function login_is_throttled(string $username): bool
{
    $n = (int) db_value(
        'SELECT COUNT(*) FROM login_attempts
         WHERE success = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE) AND (username = ? OR ip_address = ?)',
        'iss',
        [LOGIN_WINDOW_MIN, $username, client_ip()],
        0
    );
    return $n >= LOGIN_MAX_ATTEMPTS;
}

function login_record_attempt(string $username, bool $success): void
{
    db_exec(
        'INSERT INTO login_attempts (username, ip_address, success, attempted_at) VALUES (?, ?, ?, NOW())',
        'ssi',
        [mb_substr($username, 0, 100), client_ip(), $success ? 1 : 0]
    );
    if ($success) {
        // Clear the failure counter for this user.
        db_exec('DELETE FROM login_attempts WHERE username = ? AND success = 0', 's', [$username]);
    }
}

/**
 * Attempt a login. $allowedRoles limits which roles may use this login page
 * (kept so the three legacy login URLs still behave as before).
 * Returns [true, user] or [false, generic message].
 */
function attempt_login(string $username, string $password, ?array $allowedRoles = null): array
{
    $generic = 'Invalid username or password.';
    $username = trim($username);
    if ($username === '' || $password === '') {
        return [false, $generic];
    }
    if (login_is_throttled($username)) {
        return [false, 'Too many failed attempts. Please wait ' . LOGIN_WINDOW_MIN . ' minutes and try again.'];
    }
    $user = db_one('SELECT * FROM staff WHERE name = ? LIMIT 1', 's', [$username]);
    $ok = false;
    if ($user) {
        $info = password_get_info((string) $user['password']);
        if (!empty($info['algo'])) {
            $ok = password_verify($password, (string) $user['password']);
            if ($ok && password_needs_rehash((string) $user['password'], PASSWORD_DEFAULT)) {
                db_exec('UPDATE staff SET password = ? WHERE id = ?', 'si', [password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
            }
        } else {
            // Legacy plaintext row that the migration script has not reached yet:
            // verify once, then hash immediately. No permanent plaintext fallback remains
            // after database/scripts/hash_passwords.php has been run.
            $ok = hash_equals((string) $user['password'], $password);
            if ($ok) {
                db_exec('UPDATE staff SET password = ?, password_changed_at = NOW() WHERE id = ?', 'si', [password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
            }
        }
    }
    if ($ok && (int) $user['is_active'] !== 1) {
        login_record_attempt($username, false);
        audit_log('login.blocked_inactive', 'staff', (int) $user['id'], [], ['id' => $user['id'], 'name' => $user['name'], 'role' => $user['role']]);
        return [false, 'This account is deactivated. Contact your administrator.'];
    }
    if ($ok && $allowedRoles !== null && !in_array($user['role'], $allowedRoles, true)) {
        // Right password, wrong login page: still a valid login, send them to their own dashboard.
        $ok = true;
    }
    if (!$ok) {
        login_record_attempt($username, false);
        audit_log('login.failed', 'staff', $user ? (int) $user['id'] : null, ['username' => mb_substr($username, 0, 100)], ['id' => null, 'name' => null, 'role' => null]);
        return [false, $generic];
    }
    login_record_attempt($username, true);
    session_regenerate_id(true);
    $_SESSION['user_id']         = (int) $user['id'];
    $_SESSION['session_version'] = (int) $user['session_version'];
    // Legacy keys some old code paths still read.
    $_SESSION['staff_id']            = (int) $user['id'];
    $_SESSION['username']            = $user['name'];
    $_SESSION['role']                = $user['role'];
    $_SESSION['police_station_name'] = $user['police_station_name'];
    db_exec('UPDATE staff SET last_login_at = NOW() WHERE id = ?', 'i', [(int) $user['id']]);
    audit_log('login.success', 'staff', (int) $user['id'], [], ['id' => $user['id'], 'name' => $user['name'], 'role' => $user['role']]);
    return [true, $user];
}

function logout_user(): void
{
    $u = current_user();
    if ($u) {
        audit_log('logout', 'staff', (int) $u['id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 86400, 'path' => $p['path'], 'domain' => $p['domain'], 'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite']]);
    }
    session_destroy();
}

/** Invalidate every session of a user (after password change/reset or deactivation). */
function invalidate_user_sessions(int $userId): void
{
    db_exec('UPDATE staff SET session_version = session_version + 1 WHERE id = ?', 'i', [$userId]);
    $me = current_user();
    if ($me && (int) $me['id'] === $userId) {
        $_SESSION['session_version'] = (int) db_value('SELECT session_version FROM staff WHERE id = ?', 'i', [$userId], 0);
    }
}
