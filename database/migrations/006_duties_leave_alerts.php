<?php
/**
 * 006: duties lifecycle + notification state, leave types/balances/history,
 * alerts targeting/expiry with the message column unified.
 *
 * Alerts: the old code wrote to `alert_message` on one page and `message` on the
 * others. Rows are merged into `message`. Where BOTH columns held different text,
 * the alert_message text is preserved in `legacy_alert_message` (nothing lost),
 * then the `alert_message` column is dropped. Dropping is the only irreversible
 * step; the data itself is retained.
 */
declare(strict_types=1);

return function (mysqli $db, callable $log): void {
    /* ---------- Duties ---------- */
    $db->query("ALTER TABLE duties
        ADD COLUMN IF NOT EXISTS status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled' AFTER assigned_date,
        ADD COLUMN IF NOT EXISTS notify_status ENUM('not_sent','sent','failed') NOT NULL DEFAULT 'not_sent' AFTER status,
        ADD COLUMN IF NOT EXISTS notify_error VARCHAR(255) NULL AFTER notify_status,
        ADD COLUMN IF NOT EXISTS notify_attempts TINYINT NOT NULL DEFAULT 0 AFTER notify_error,
        ADD COLUMN IF NOT EXISTS notified_at DATETIME NULL AFTER notify_attempts,
        ADD COLUMN IF NOT EXISTS cancel_reason VARCHAR(255) NULL AFTER notified_at,
        ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL AFTER cancel_reason,
        ADD COLUMN IF NOT EXISTS created_by INT NULL AFTER completed_at,
        ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_by,
        ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    $db->query('ALTER TABLE duties ADD INDEX IF NOT EXISTS idx_duties_staff_time (staff_id, start_time, end_time)');
    $db->query('ALTER TABLE duties ADD INDEX IF NOT EXISTS idx_duties_status (status)');
    $log('  duties: lifecycle and notification columns added');

    /* ---------- Leave ---------- */
    $db->query("CREATE TABLE IF NOT EXISTS leave_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        annual_allowance INT NULL COMMENT 'days per calendar year; NULL = no limit enforced',
        requires_reason TINYINT(1) NOT NULL DEFAULT 1,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        UNIQUE KEY uq_leave_type (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    // Demonstration defaults, not an official policy. Editable by head office.
    $db->query("INSERT IGNORE INTO leave_types (name, annual_allowance) VALUES ('Casual', 10), ('Sick', 8), ('Annual', 15)");
    // Preserve any other leave type names already used in the data.
    $db->query("INSERT IGNORE INTO leave_types (name, annual_allowance) SELECT DISTINCT TRIM(leave_type), NULL FROM leave_requests WHERE leave_type IS NOT NULL AND TRIM(leave_type) <> ''");

    $db->query("ALTER TABLE leave_requests
        ADD COLUMN IF NOT EXISTS leave_type_id INT NULL AFTER leave_type,
        ADD COLUMN IF NOT EXISTS requested_days INT NOT NULL DEFAULT 0 AFTER leave_end_date,
        ADD COLUMN IF NOT EXISTS reviewed_by INT NULL AFTER approved_days,
        ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL AFTER reviewed_by,
        ADD COLUMN IF NOT EXISTS review_reason VARCHAR(500) NULL AFTER reviewed_at,
        ADD COLUMN IF NOT EXISTS withdrawn_at DATETIME NULL AFTER review_reason");
    $db->query('UPDATE leave_requests lr JOIN leave_types lt ON lt.name = TRIM(lr.leave_type) SET lr.leave_type_id = lt.id WHERE lr.leave_type_id IS NULL');
    $db->query('UPDATE leave_requests SET requested_days = DATEDIFF(leave_end_date, leave_start_date) + 1 WHERE requested_days = 0 AND leave_end_date >= leave_start_date');
    $db->query("UPDATE leave_requests SET status = LOWER(TRIM(status))");
    $db->query('ALTER TABLE leave_requests ADD INDEX IF NOT EXISTS idx_leave_staff_dates (staff_id, leave_start_date, leave_end_date)');
    $db->query('ALTER TABLE leave_requests ADD INDEX IF NOT EXISTS idx_leave_status (status)');
    $fkExists = fn(string $n) => (bool) $db->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '$n'")->num_rows;
    if (!$fkExists('fk_leave_type')) {
        $db->query('ALTER TABLE leave_requests ADD CONSTRAINT fk_leave_type FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE RESTRICT');
    }
    if (!$fkExists('fk_leave_reviewer')) {
        $db->query('ALTER TABLE leave_requests ADD CONSTRAINT fk_leave_reviewer FOREIGN KEY (reviewed_by) REFERENCES staff(id) ON DELETE SET NULL');
    }
    $db->query("CREATE TABLE IF NOT EXISTS leave_request_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        leave_request_id INT NOT NULL,
        action VARCHAR(30) NOT NULL,
        actor_id INT NULL,
        approved_days INT NULL,
        reason VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_lrh_request (leave_request_id),
        CONSTRAINT fk_lrh_request FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id) ON DELETE RESTRICT,
        CONSTRAINT fk_lrh_actor FOREIGN KEY (actor_id) REFERENCES staff(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $log('  leave: types, balances columns and history table ready');

    /* ---------- Alerts ---------- */
    $hasLegacy = (bool) $db->query("SHOW COLUMNS FROM alerts LIKE 'alert_message'")->num_rows;
    if ($hasLegacy) {
        $db->query('ALTER TABLE alerts ADD COLUMN IF NOT EXISTS legacy_alert_message TEXT NULL');
        $db->query("UPDATE alerts SET legacy_alert_message = alert_message
                    WHERE alert_message IS NOT NULL AND alert_message <> '' AND message IS NOT NULL AND message <> '' AND message <> alert_message");
        $conflicts = $db->affected_rows;
        $db->query("UPDATE alerts SET message = alert_message WHERE (message IS NULL OR message = '') AND alert_message IS NOT NULL AND alert_message <> ''");
        $merged = $db->affected_rows;
        $db->query('ALTER TABLE alerts DROP COLUMN alert_message');
        $log("  alerts: merged $merged legacy message(s); $conflicts conflicting row(s) kept in legacy_alert_message; alert_message column dropped");
    }
    $db->query("ALTER TABLE alerts
        ADD COLUMN IF NOT EXISTS title VARCHAR(150) NULL AFTER id,
        ADD COLUMN IF NOT EXISTS target_type ENUM('all','district','station') NOT NULL DEFAULT 'all' AFTER message,
        ADD COLUMN IF NOT EXISTS target_district_id INT NULL AFTER target_type,
        ADD COLUMN IF NOT EXISTS target_station_id INT NULL AFTER target_district_id,
        ADD COLUMN IF NOT EXISTS starts_at DATETIME NULL AFTER target_station_id,
        ADD COLUMN IF NOT EXISTS expires_at DATETIME NULL AFTER starts_at,
        ADD COLUMN IF NOT EXISTS status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active' AFTER expires_at,
        ADD COLUMN IF NOT EXISTS created_by INT NULL AFTER status,
        ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    // Old behaviour was "alerts last 3 hours": keep that for existing rows.
    $db->query("UPDATE alerts SET starts_at = created_at WHERE starts_at IS NULL");
    $db->query("UPDATE alerts SET expires_at = DATE_ADD(created_at, INTERVAL 3 HOUR) WHERE expires_at IS NULL");
    $db->query("UPDATE alerts SET status = IF(is_active = 1, 'active', 'inactive')");
    $db->query('ALTER TABLE alerts ADD INDEX IF NOT EXISTS idx_alerts_window (status, starts_at, expires_at)');
    if (!$fkExists('fk_alert_district')) {
        $db->query('ALTER TABLE alerts ADD CONSTRAINT fk_alert_district FOREIGN KEY (target_district_id) REFERENCES districts(id) ON DELETE RESTRICT');
    }
    if (!$fkExists('fk_alert_station')) {
        $db->query('ALTER TABLE alerts ADD CONSTRAINT fk_alert_station FOREIGN KEY (target_station_id) REFERENCES police_stations(id) ON DELETE RESTRICT');
    }
    $log('  alerts: targeting, start/expiry window and status added');
};
