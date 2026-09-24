<?php
/** General helpers: escaping, redirects, flash messages, formatting, pagination. */
declare(strict_types=1);

/** Escape for HTML text and attribute context. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Safe JSON for embedding inside <script>. */
function json_for_script($value): string
{
    return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

function app_url(string $path = ''): string
{
    return rtrim((string) config('app_url'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path, int $code = 303): void
{
    $url = preg_match('#^https?://#', $path) ? $path : app_url($path);
    header('Location: ' . $url, true, $code);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/* ---------- Flash messages (Post/Redirect/Get) ---------- */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}
function flash_pull(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/* ---------- Old input after a failed form ---------- */
function old(string $key, $default = '')
{
    return $_SESSION['_old'][$key] ?? $default;
}
function keep_old(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['current_password'], $data['new_password'], $data['csrf_token']);
    $_SESSION['_old'] = $data;
}
function clear_old(): void
{
    unset($_SESSION['_old']);
}
/** Pull old input once (call from the page after handling POST). */
function old_pull(): array
{
    $o = $_SESSION['_old'] ?? [];
    unset($_SESSION['_old']);
    return $o;
}

/* ---------- Input helpers ---------- */
function post_str(string $key, int $max = 1000): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    return mb_substr(trim($v), 0, $max);
}
function post_int(string $key, ?int $default = null): ?int
{
    $v = $_POST[$key] ?? null;
    if ($v === null || $v === '' || !is_scalar($v) || !preg_match('/^-?\d{1,10}$/', (string) $v)) {
        return $default;
    }
    return (int) $v;
}
function get_int(string $key, ?int $default = null): ?int
{
    $v = $_GET[$key] ?? null;
    if ($v === null || $v === '' || !is_scalar($v) || !preg_match('/^-?\d{1,10}$/', (string) $v)) {
        return $default;
    }
    return (int) $v;
}
function get_str(string $key, int $max = 200): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}
function valid_date(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
}
function valid_datetime_local(string $d): bool
{
    // HTML datetime-local: 2026-09-23T08:00 (seconds optional)
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $d) && strtotime($d) !== false;
}
function valid_email(string $e): bool
{
    return filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}
/** Pakistani CNIC: 13 digits, optionally 12345-1234567-1 */
function normalize_cnic(string $c): ?string
{
    $digits = preg_replace('/\D/', '', $c);
    if (strlen($digits) !== 13) {
        return null;
    }
    return substr($digits, 0, 5) . '-' . substr($digits, 5, 7) . '-' . substr($digits, 12, 1);
}
function mask_cnic(?string $c): string
{
    if ($c === null || $c === '') {
        return '';
    }
    $digits = preg_replace('/\D/', '', $c);
    if (strlen($digits) < 4) {
        return '****';
    }
    return '*****-*******-' . substr($digits, -1);
}

/* ---------- Dates (stored and displayed in Asia/Karachi) ---------- */
function fmt_date(?string $d): string
{
    if (!$d || $d === '0000-00-00') {
        return '';
    }
    $t = strtotime($d);
    return $t ? date('d M Y', $t) : e($d);
}
function fmt_datetime(?string $d): string
{
    if (!$d) {
        return '';
    }
    $t = strtotime($d);
    return $t ? date('d M Y, h:i A', $t) : e($d);
}
function now_sql(): string
{
    return date('Y-m-d H:i:s');
}

/* ---------- Pagination ---------- */
function paginate(int $total, int $perPage = 20): array
{
    $perPage = max(5, min(100, $perPage));
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = max(1, min($pages, get_int('page', 1) ?? 1));
    return [
        'page' => $page, 'per_page' => $perPage, 'pages' => $pages, 'total' => $total,
        'offset' => ($page - 1) * $perPage,
    ];
}
/** Render pagination links preserving current query string. */
function pagination_html(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $q = $_GET;
    $link = function (int $n) use ($q): string {
        $q['page'] = $n;
        return '?' . http_build_query($q);
    };
    $h = '<nav aria-label="Pages"><ul class="pagination pagination-sm mb-0">';
    $h .= '<li class="page-item' . ($p['page'] <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . e($link(max(1, $p['page'] - 1))) . '">Previous</a></li>';
    $start = max(1, $p['page'] - 2);
    $end   = min($p['pages'], $p['page'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        $h .= '<li class="page-item' . ($i === $p['page'] ? ' active' : '') . '"><a class="page-link" href="' . e($link($i)) . '">' . $i . '</a></li>';
    }
    $h .= '<li class="page-item' . ($p['page'] >= $p['pages'] ? ' disabled' : '') . '"><a class="page-link" href="' . e($link(min($p['pages'], $p['page'] + 1))) . '">Next</a></li>';
    $h .= '</ul></nav>';
    return $h;
}

/** Link to the current page with some query values changed (keeps filters). */
function query_link(array $changes): string
{
    $q = array_merge($_GET, $changes);
    unset($q['page']);
    return '?' . http_build_query($q);
}

/* ---------- CSV export (spreadsheet formula injection safe) ---------- */
function csv_cell($v): string
{
    $s = (string) ($v ?? '');
    if ($s !== '' && preg_match('/^[=+\-@\t\r]/', $s)) {
        $s = "'" . $s;
    }
    return $s;
}
function csv_download(string $filename, array $header, array $rows): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header);
    foreach ($rows as $r) {
        fputcsv($out, array_map('csv_cell', $r));
    }
    fclose($out);
    exit;
}

function json_response($data, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Badge markup for a status word. */
function status_badge(?string $status): string
{
    $map = [
        'open' => 'primary', 'under investigation' => 'warning', 'closed' => 'success', 'archived' => 'secondary',
        'pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'withdrawn' => 'secondary',
        'scheduled' => 'primary', 'completed' => 'success', 'cancelled' => 'secondary',
        'active' => 'success', 'inactive' => 'secondary', 'sent' => 'success', 'failed' => 'danger',
        'yes' => 'success', 'no' => 'secondary',
    ];
    $k = strtolower((string) $status);
    return '<span class="badge text-bg-' . ($map[$k] ?? 'light') . '">' . e(ucwords($k)) . '</span>';
}

/** Human label for a role key. */
function role_label(string $role): string
{
    return ['admin' => 'Head Office Admin', 'admin station' => 'Station Admin', 'staff' => 'Staff'][$role] ?? ucfirst($role);
}
