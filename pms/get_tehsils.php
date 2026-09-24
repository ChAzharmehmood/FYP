<?php
/** AJAX: tehsils of a district (JSON). Requires a login like every other endpoint. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$district = get_int('district_id', 0) ?? 0;
$rows = $district > 0
    ? db_all('SELECT id, name FROM tehsils WHERE district_id = ? AND is_active = 1 ORDER BY name', 'i', [$district])
    : [];
json_response(['ok' => true, 'tehsils' => $rows]);
