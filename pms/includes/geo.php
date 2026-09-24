<?php
/** Districts, tehsils and stations lookups (cached per request). */
declare(strict_types=1);

function districts_all(bool $activeOnly = true): array
{
    static $cache = [];
    $k = $activeOnly ? 'a' : 'all';
    if (!isset($cache[$k])) {
        $cache[$k] = db_all('SELECT id, name, is_active FROM districts ' . ($activeOnly ? 'WHERE is_active = 1 ' : '') . 'ORDER BY name');
    }
    return $cache[$k];
}

function tehsils_all(): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = db_all('SELECT t.id, t.name, t.district_id, d.name AS district_name FROM tehsils t JOIN districts d ON d.id = t.district_id WHERE t.is_active = 1 ORDER BY d.name, t.name');
    }
    return $rows;
}

/** district_id => [ [id, name], ... ] for the report/station forms. */
function tehsils_by_district(): array
{
    $out = [];
    foreach (tehsils_all() as $t) {
        $out[(int) $t['district_id']][] = ['id' => (int) $t['id'], 'name' => $t['name']];
    }
    return $out;
}

function stations_all(bool $activeOnly = true): array
{
    static $cache = [];
    $k = $activeOnly ? 'a' : 'all';
    if (!isset($cache[$k])) {
        $cache[$k] = db_all(
            'SELECT ps.id, ps.police_station_name, ps.district_id, ps.tehsil_id, ps.is_active,
                    d.name AS district_name, t.name AS tehsil_name
             FROM police_stations ps
             LEFT JOIN districts d ON d.id = ps.district_id
             LEFT JOIN tehsils t ON t.id = ps.tehsil_id '
            . ($activeOnly ? 'WHERE ps.is_active = 1 ' : '') .
            'ORDER BY ps.police_station_name'
        );
    }
    return $cache[$k];
}

/** Stations the current user may pick in a form. */
function stations_for_user(): array
{
    if (is_admin()) {
        return stations_all();
    }
    $sid = user_station_id();
    return array_values(array_filter(stations_all(false), fn($s) => (int) $s['id'] === $sid));
}

function station_find(int $id): ?array
{
    return db_one(
        'SELECT ps.*, d.name AS district_name, t.name AS tehsil_name
         FROM police_stations ps
         LEFT JOIN districts d ON d.id = ps.district_id
         LEFT JOIN tehsils t ON t.id = ps.tehsil_id
         WHERE ps.id = ?',
        'i',
        [$id]
    );
}
