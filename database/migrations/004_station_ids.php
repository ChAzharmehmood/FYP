<?php
/**
 * 004: police_station_id on staff, reports and duties, backfilled from the
 * legacy police_station_name text (exact match after trim, case-insensitive).
 * Unmatched names are reported and left NULL (never guessed). Legacy name
 * columns are kept in sync by the application and can be dropped later with
 * migrations/900_drop_legacy_station_names.sql.manual after validation.
 * Also replaces ON DELETE CASCADE on duties/leave_requests with RESTRICT so
 * deleting a staff row can never silently erase history.
 */
declare(strict_types=1);

return function (mysqli $db, callable $log): void {
    foreach (['staff', 'reports', 'duties'] as $t) {
        $db->query("ALTER TABLE `$t` ADD COLUMN IF NOT EXISTS police_station_id INT NULL AFTER police_station_name");
        $db->query("ALTER TABLE `$t` ADD INDEX IF NOT EXISTS idx_{$t}_station (police_station_id)");
        $n = $db->query("UPDATE `$t` x JOIN police_stations ps ON LOWER(TRIM(ps.police_station_name)) = LOWER(TRIM(x.police_station_name))
                          SET x.police_station_id = ps.id
                          WHERE x.police_station_id IS NULL AND x.police_station_name IS NOT NULL AND TRIM(x.police_station_name) <> ''");
        $log("  $t: linked " . $db->affected_rows . " row(s) to a station id");
        $un = $db->query("SELECT police_station_name n, COUNT(*) c FROM `$t` WHERE police_station_id IS NULL AND police_station_name IS NOT NULL AND TRIM(police_station_name) <> '' GROUP BY n")->fetch_all(MYSQLI_ASSOC);
        foreach ($un as $u) {
            $log("  $t: UNMATCHED station name '{$u['n']}' on {$u['c']} row(s) - left unlinked, fix via Police Stations page");
        }
    }

    $fkExists = fn(string $n) => (bool) $db->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '$n'")->num_rows;
    if (!$fkExists('fk_staff_station')) {
        $db->query('ALTER TABLE staff ADD CONSTRAINT fk_staff_station FOREIGN KEY (police_station_id) REFERENCES police_stations(id) ON DELETE RESTRICT');
    }
    if (!$fkExists('fk_reports_station')) {
        $db->query('ALTER TABLE reports ADD CONSTRAINT fk_reports_station FOREIGN KEY (police_station_id) REFERENCES police_stations(id) ON DELETE RESTRICT');
    }
    if (!$fkExists('fk_duties_station')) {
        $db->query('ALTER TABLE duties ADD CONSTRAINT fk_duties_station FOREIGN KEY (police_station_id) REFERENCES police_stations(id) ON DELETE RESTRICT');
    }
    // No cascading deletes of history.
    if ($fkExists('fk_duties_staff')) {
        $db->query('ALTER TABLE duties DROP FOREIGN KEY fk_duties_staff');
    }
    $db->query('ALTER TABLE duties ADD CONSTRAINT fk_duties_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE RESTRICT');
    if ($fkExists('fk_leave_staff')) {
        $db->query('ALTER TABLE leave_requests DROP FOREIGN KEY fk_leave_staff');
    }
    $db->query('ALTER TABLE leave_requests ADD CONSTRAINT fk_leave_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE RESTRICT');
};
