<?php
/**
 * Versioned migration runner (CLI only).
 *
 *   php database/migrate.php            apply pending migrations
 *   php database/migrate.php --status   list applied / pending
 *   php database/migrate.php --check    run data checks only (duplicates, unmatched names)
 *   php database/migrate.php --dry-run  show what would run
 *
 * Migrations live in database/migrations/ and run in filename order.
 *   NNN_name.sql  plain SQL (several statements)
 *   NNN_name.php  returns function (mysqli $db, callable $log): void
 *
 * Each applied migration is recorded in schema_migrations. MySQL/MariaDB DDL
 * auto-commits, so a failed migration can leave partial changes: always take a
 * backup first (see docs/BACKUP_AND_RECOVERY.md). Migrations use
 * IF NOT EXISTS where MariaDB supports it so a re-run after a partial failure is safe.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once dirname(__DIR__) . '/pms/config.php';
require_once dirname(__DIR__) . '/pms/includes/helpers.php';

$args    = array_slice($argv, 1);
$status  = in_array('--status', $args, true);
$check   = in_array('--check', $args, true);
$dryRun  = in_array('--dry-run', $args, true);

$db  = db();
$log = function (string $m): void { echo $m, PHP_EOL; };

$db->query('CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(190) NOT NULL UNIQUE,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$applied = [];
foreach ($db->query('SELECT migration, applied_at FROM schema_migrations ORDER BY migration') as $r) {
    $applied[$r['migration']] = $r['applied_at'];
}

$files = glob(__DIR__ . '/migrations/*.{sql,php}', GLOB_BRACE) ?: [];
sort($files, SORT_STRING);

/* ---------------- Data checks ---------------- */
function run_data_checks(mysqli $db, callable $log): int
{
    $problems = 0;
    $log('== Data checks ==');

    $rows = $db->query("SELECT LOWER(TRIM(police_station_name)) AS n, COUNT(*) c, GROUP_CONCAT(id) ids
                        FROM police_stations GROUP BY n HAVING c > 1")->fetch_all(MYSQLI_ASSOC);
    if ($rows) {
        $problems += count($rows);
        $log('DUPLICATE station names (must be resolved manually before migration 004):');
        foreach ($rows as $r) {
            $log("  '{$r['n']}' appears {$r['c']} times (ids {$r['ids']})");
        }
    } else {
        $log('OK  no duplicate station names');
    }

    $rows = $db->query("SELECT TRIM(name) AS n, COUNT(*) c, GROUP_CONCAT(id) ids FROM staff GROUP BY n HAVING c > 1")->fetch_all(MYSQLI_ASSOC);
    if ($rows) {
        $problems += count($rows);
        $log('DUPLICATE staff login names (must be unique; rename before migration 001):');
        foreach ($rows as $r) {
            $log("  '{$r['n']}' appears {$r['c']} times (ids {$r['ids']})");
        }
    } else {
        $log('OK  no duplicate staff names');
    }

    $cols = fn(string $t, string $c) => (bool) $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'")->num_rows;
    foreach (['staff', 'reports', 'duties'] as $t) {
        if ($cols($t, 'police_station_id')) {
            $rows = $db->query("SELECT police_station_name AS n, COUNT(*) c FROM `$t`
                                WHERE police_station_id IS NULL AND police_station_name IS NOT NULL AND TRIM(police_station_name) <> ''
                                GROUP BY n")->fetch_all(MYSQLI_ASSOC);
            if ($rows) {
                $problems += count($rows);
                $log("UNMATCHED station names in $t (rows keep the legacy text; fix in Police Stations and re-run --check):");
                foreach ($rows as $r) {
                    $log("  '{$r['n']}' on {$r['c']} row(s)");
                }
            } else {
                $log("OK  every $t row with a station name is linked to a station id");
            }
        }
    }
    if ($cols('police_stations', 'district_id')) {
        $rows = $db->query("SELECT district, tehsil, COUNT(*) c FROM police_stations WHERE district_id IS NULL OR tehsil_id IS NULL GROUP BY district, tehsil")->fetch_all(MYSQLI_ASSOC);
        if ($rows) {
            $problems += count($rows);
            $log('UNMATCHED district/tehsil text on police_stations (edit the station to pick a district):');
            foreach ($rows as $r) {
                $log("  district='{$r['district']}' tehsil='{$r['tehsil']}' on {$r['c']} row(s)");
            }
        } else {
            $log('OK  every station is linked to a district and tehsil');
        }
    }
    if ($cols('reports', 'crime_type')) {
        $n = (int) $db->query("SELECT COUNT(*) FROM reports WHERE crime_type IS NULL AND complainant IS NOT NULL AND complainant <> ''")->fetch_row()[0];
        if ($n) {
            $problems++;
            $log("NOTE $n report(s) have a legacy 'complainant' value that is not a known crime type; crime_type left empty (see migration 007).");
        } else {
            $log('OK  every legacy report has a crime_type');
        }
    }
    $n = (int) $db->query("SELECT COUNT(*) FROM staff WHERE password NOT LIKE '\$2y\$%' AND password NOT LIKE '\$argon2%'")->fetch_row()[0];
    if ($n) {
        $problems++;
        $log("NOTE $n staff row(s) still have a plaintext password: run php database/scripts/hash_passwords.php");
    } else {
        $log('OK  all passwords are hashed');
    }
    $log('== ' . ($problems ? "$problems item(s) need attention" : 'all checks passed') . ' ==');
    return $problems;
}

if ($check) {
    exit(run_data_checks($db, $log) ? 1 : 0);
}

if ($status) {
    foreach ($files as $f) {
        $name = basename($f);
        $log((isset($applied[$name]) ? '[applied ' . $applied[$name] . '] ' : '[pending]            ') . $name);
    }
    exit(0);
}

/* ---------------- Apply ---------------- */
$pending = array_values(array_filter($files, fn($f) => !isset($applied[basename($f)])));
if (!$pending) {
    $log('Nothing to migrate. Database is up to date.');
    run_data_checks($db, $log);
    exit(0);
}

// Hard pre-conditions that must never be guessed around.
$dupStations = (int) $db->query("SELECT COUNT(*) FROM (SELECT 1 FROM police_stations GROUP BY LOWER(TRIM(police_station_name)) HAVING COUNT(*) > 1) x")->fetch_row()[0];
$dupStaff    = (int) $db->query("SELECT COUNT(*) FROM (SELECT 1 FROM staff GROUP BY TRIM(name) HAVING COUNT(*) > 1) x")->fetch_row()[0];
if ($dupStaff || $dupStations) {
    run_data_checks($db, $log);
    $log('ABORTED: resolve the duplicates above first (rename the records), then re-run.');
    exit(1);
}

foreach ($pending as $f) {
    $name = basename($f);
    $log(($dryRun ? '[dry-run] would apply ' : 'Applying ') . $name . ' ...');
    if ($dryRun) {
        continue;
    }
    try {
        if (str_ends_with($f, '.sql')) {
            $sql = file_get_contents($f);
            if ($sql === false) {
                throw new RuntimeException("cannot read $f");
            }
            if (!$db->multi_query($sql)) {
                throw new RuntimeException($db->error);
            }
            do {
                if ($res = $db->store_result()) {
                    $res->free();
                }
                if ($db->errno) {
                    throw new RuntimeException($db->error);
                }
            } while ($db->more_results() && $db->next_result());
            if ($db->errno) {
                throw new RuntimeException($db->error);
            }
        } else {
            $fn = require $f;
            if (!is_callable($fn)) {
                throw new RuntimeException("$name must return a callable");
            }
            $fn($db, $log);
        }
        $stmt = $db->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $log("  done.");
    } catch (Throwable $t) {
        $log('  FAILED: ' . $t->getMessage());
        $log('  The migration was NOT recorded. Fix the cause and re-run; statements use IF NOT EXISTS so re-running is safe.');
        $log('  To roll back fully, restore the backup taken before migrating (docs/BACKUP_AND_RECOVERY.md).');
        exit(1);
    }
}
$log('All migrations applied.');
run_data_checks($db, $log);
