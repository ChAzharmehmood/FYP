<?php
/**
 * Database access helpers (mysqli, prepared statements only).
 */
declare(strict_types=1);

function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = new mysqli(
            (string) config('db_host'),
            (string) config('db_user'),
            (string) config('db_pass'),
            (string) config('db_name')
        );
        $conn->set_charset('utf8mb4');
        // Keep MySQL and PHP on the same clock (Asia/Karachi is UTC+5, no DST).
        $conn->query("SET time_zone = '+05:00'");
    } catch (mysqli_sql_exception $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        if (config('app_debug')) {
            die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
        }
        die('The system is temporarily unavailable. Please contact the administrator.');
    }
    return $conn;
}

/**
 * Run a prepared statement. $types is the bind_param type string ("isd...").
 * Returns the mysqli_stmt so callers can read insert_id / affected_rows.
 */
function db_stmt(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

/** SELECT: all rows as associative arrays. */
function db_all(string $sql, string $types = '', array $params = []): array
{
    $stmt = db_stmt($sql, $types, $params);
    $res  = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/** SELECT: first row or null. */
function db_one(string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_all($sql, $types, $params);
    return $rows[0] ?? null;
}

/** SELECT: first column of the first row. */
function db_value(string $sql, string $types = '', array $params = [], $default = null)
{
    $row = db_one($sql, $types, $params);
    if ($row === null) {
        return $default;
    }
    return array_values($row)[0];
}

/** INSERT/UPDATE/DELETE: returns affected rows. Use db_insert_id() for new ids. */
function db_exec(string $sql, string $types = '', array $params = []): int
{
    $stmt = db_stmt($sql, $types, $params);
    $affected = $stmt->affected_rows;
    $GLOBALS['PMS_LAST_INSERT_ID'] = $stmt->insert_id;
    $stmt->close();
    return $affected;
}

function db_insert_id(): int
{
    return (int) ($GLOBALS['PMS_LAST_INSERT_ID'] ?? 0);
}

function db_begin(): void   { db()->begin_transaction(); }
function db_commit(): void  { db()->commit(); }
function db_rollback(): void { db()->rollback(); }

/** Allowlisted ORDER BY builder. $allowed maps request key => real column expression. */
function db_order_by(?string $requested, array $allowed, string $defaultKey, string $dir = 'asc'): array
{
    $key = ($requested !== null && isset($allowed[$requested])) ? $requested : $defaultKey;
    $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
    return [$key, $dir, $allowed[$key] . ' ' . $dir];
}
