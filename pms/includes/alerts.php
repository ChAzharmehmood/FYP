<?php
/** Alerts: targeting (all / district / station), start and expiry window. */
declare(strict_types=1);

/**
 * Alerts the current user should see right now. Expiry is evaluated in the
 * query, so nothing needs a cleanup job and nobody has to open a dashboard
 * for an alert to disappear.
 */
function alerts_visible(): array
{
    $u = current_user();
    if (!$u) {
        return [];
    }
    $sid = isset($u['police_station_id']) ? (int) $u['police_station_id'] : 0;
    $did = isset($u['district_id']) ? (int) $u['district_id'] : 0;
    $sql = 'SELECT a.*, s.name AS created_by_name, d.name AS district_name, ps.police_station_name AS station_name
            FROM alerts a
            LEFT JOIN staff s ON s.id = a.created_by
            LEFT JOIN districts d ON d.id = a.target_district_id
            LEFT JOIN police_stations ps ON ps.id = a.target_station_id
            WHERE a.status = "active"
              AND (a.starts_at IS NULL OR a.starts_at <= NOW())
              AND (a.expires_at IS NULL OR a.expires_at > NOW())
              AND (a.target_type = "all"';
    $types = '';
    $params = [];
    if (is_admin()) {
        $sql .= ' OR 1=1';
    } else {
        $sql .= ' OR (a.target_type = "district" AND a.target_district_id = ?) OR (a.target_type = "station" AND a.target_station_id = ?)';
        $types = 'ii';
        $params = [$did, $sid];
    }
    $sql .= ') ORDER BY a.created_at DESC LIMIT 50';
    return db_all($sql, $types, $params);
}

function alert_find(int $id): ?array
{
    return db_one(
        'SELECT a.*, s.name AS created_by_name, d.name AS district_name, ps.police_station_name AS station_name
         FROM alerts a LEFT JOIN staff s ON s.id = a.created_by
         LEFT JOIN districts d ON d.id = a.target_district_id
         LEFT JOIN police_stations ps ON ps.id = a.target_station_id
         WHERE a.id = ?',
        'i',
        [$id]
    );
}

/** Human description of where an alert goes. */
function alert_target_label(array $a): string
{
    switch ($a['target_type']) {
        case 'district': return 'District: ' . ($a['district_name'] ?? '?');
        case 'station':  return 'Station: ' . ($a['station_name'] ?? '?');
        default:         return 'Everyone';
    }
}

/** Is the alert live at this moment? */
function alert_is_live(array $a): bool
{
    if ($a['status'] !== 'active') {
        return false;
    }
    $now = time();
    if (!empty($a['starts_at']) && strtotime($a['starts_at']) > $now) {
        return false;
    }
    if (!empty($a['expires_at']) && strtotime($a['expires_at']) <= $now) {
        return false;
    }
    return true;
}

/** Shared alerts panel for the dashboards. */
function alerts_panel_html(): string
{
    $alerts = alerts_visible();
    $h = '<div class="card h-100"><div class="card-body"><h2 class="h6 text-uppercase text-muted mb-3"><i class="fa-solid fa-bell"></i> Active alerts</h2>';
    if (!$alerts) {
        $h .= '<div class="empty-state py-3"><i class="fa-regular fa-bell-slash"></i><div>No active alerts.</div></div>';
    } else {
        $h .= '<ul class="list-group list-group-flush">';
        foreach ($alerts as $a) {
            $h .= '<li class="list-group-item px-0">';
            if (!empty($a['title'])) {
                $h .= '<div class="fw-semibold">' . e($a['title']) . '</div>';
            }
            $h .= '<div>' . nl2br(e($a['message'])) . '</div>';
            $h .= '<div class="small text-muted">' . e(alert_target_label($a)) . ' · ' . fmt_datetime($a['starts_at'] ?: $a['created_at']);
            if (!empty($a['expires_at'])) {
                $h .= ' · expires ' . fmt_datetime($a['expires_at']);
            }
            $h .= '</div></li>';
        }
        $h .= '</ul>';
    }
    $h .= '</div></div>';
    return $h;
}
