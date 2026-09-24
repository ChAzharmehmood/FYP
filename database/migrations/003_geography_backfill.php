<?php
/**
 * 003: add district_id / tehsil_id to police_stations and link the existing
 * free-text district/tehsil values. Text columns are kept untouched.
 *
 * Matching rules (deterministic, no guessing):
 *   - trim, case-insensitive
 *   - the suffix " District" / " Tehsil" and underscores are ignored
 *   - two spelling aliases that the old form itself used: "Muzafarabad" -> Muzaffarabad, "Bimber" -> Bhimber
 * A district or tehsil text that still has no match is INSERTED as a new
 * district/tehsil (so existing data keeps working) and listed in the log.
 * Ambiguous matches are left NULL and listed.
 */
declare(strict_types=1);

return function (mysqli $db, callable $log): void {
    $db->query("ALTER TABLE police_stations
        ADD COLUMN IF NOT EXISTS district_id INT NULL AFTER tehsil,
        ADD COLUMN IF NOT EXISTS tehsil_id INT NULL AFTER district_id,
        ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER tehsil_id,
        ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_active,
        ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    $db->query("ALTER TABLE police_stations ADD UNIQUE INDEX IF NOT EXISTS uq_station_name (police_station_name)");

    $norm = function (string $s): string {
        $s = strtolower(trim(str_replace('_', ' ', $s)));
        $s = preg_replace('/\s+(district|tehsil)$/', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        $alias = ['muzafarabad' => 'muzaffarabad', 'bimber' => 'bhimber'];
        return $alias[$s] ?? $s;
    };

    $districts = [];
    foreach ($db->query('SELECT id, name FROM districts') as $r) {
        $districts[$norm($r['name'])][] = (int) $r['id'];
    }
    $tehsils = [];
    foreach ($db->query('SELECT id, district_id, name FROM tehsils') as $r) {
        $tehsils[(int) $r['district_id']][$norm($r['name'])][] = (int) $r['id'];
    }

    $insD = $db->prepare('INSERT INTO districts (name) VALUES (?)');
    $insT = $db->prepare('INSERT INTO tehsils (district_id, name) VALUES (?, ?)');
    $upd  = $db->prepare('UPDATE police_stations SET district_id = ?, tehsil_id = ? WHERE id = ?');

    $rows = $db->query('SELECT id, police_station_name, district, tehsil FROM police_stations WHERE district_id IS NULL OR tehsil_id IS NULL')->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $r) {
        $dn = $norm((string) $r['district']);
        $tn = $norm((string) $r['tehsil']);
        $did = null;
        if ($dn !== '') {
            $cands = $districts[$dn] ?? [];
            if (count($cands) === 1) {
                $did = $cands[0];
            } elseif (count($cands) > 1) {
                $log("  AMBIGUOUS district '{$r['district']}' for station #{$r['id']} - left unlinked");
                continue;
            } else {
                $name = trim((string) $r['district']);
                $insD->bind_param('s', $name);
                $insD->execute();
                $did = (int) $insD->insert_id;
                $districts[$dn] = [$did];
                $log("  added district '$name' from station #{$r['id']} (was not in the seed list)");
            }
        }
        $tid = null;
        if ($did !== null && $tn !== '') {
            $cands = $tehsils[$did][$tn] ?? [];
            if (count($cands) === 1) {
                $tid = $cands[0];
            } elseif (count($cands) > 1) {
                $log("  AMBIGUOUS tehsil '{$r['tehsil']}' for station #{$r['id']} - left unlinked");
            } else {
                $name = trim((string) $r['tehsil']);
                $insT->bind_param('is', $did, $name);
                $insT->execute();
                $tid = (int) $insT->insert_id;
                $tehsils[$did][$tn] = [$tid];
                $log("  added tehsil '$name' under district id $did from station #{$r['id']}");
            }
        }
        $sid = (int) $r['id'];
        $upd->bind_param('iii', $did, $tid, $sid);
        $upd->execute();
    }

    $db->query('ALTER TABLE police_stations ADD INDEX IF NOT EXISTS idx_station_district (district_id)');
    $db->query('ALTER TABLE police_stations ADD INDEX IF NOT EXISTS idx_station_tehsil (tehsil_id)');
    // Foreign keys (NULL values are allowed, so unlinked rows do not block this).
    $fkExists = fn(string $n) => (bool) $db->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '$n'")->num_rows;
    if (!$fkExists('fk_station_district')) {
        $db->query('ALTER TABLE police_stations ADD CONSTRAINT fk_station_district FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE RESTRICT');
    }
    if (!$fkExists('fk_station_tehsil')) {
        $db->query('ALTER TABLE police_stations ADD CONSTRAINT fk_station_tehsil FOREIGN KEY (tehsil_id) REFERENCES tehsils(id) ON DELETE RESTRICT');
    }
};
