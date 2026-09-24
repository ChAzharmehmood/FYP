<?php
/**
 * 005: reports - reference numbers, crime type separated from complainant,
 * status history, evidence attachments, ownership columns, district/tehsil ids.
 *
 * Legacy data handling (nothing is deleted):
 *   - The old form stored the CRIME TYPE in the column named `complainant`.
 *     crime_type is filled from it only where the value is one of the 20 options
 *     the old dropdown offered; anything else is left for a human to classify.
 *     The legacy `complainant` column is kept untouched; new records use complainant_name.
 *   - Status words are normalised: Pending -> Open, In Progress -> Under Investigation,
 *     Completed -> Closed (reverse mapping is in docs/MIGRATIONS.md).
 *   - reference_no = PMS-<year of report_date or created_at>-<id padded to 6>; ids never change.
 */
declare(strict_types=1);

return function (mysqli $db, callable $log): void {
    $db->query("ALTER TABLE reports
        ADD COLUMN IF NOT EXISTS reference_no VARCHAR(30) NULL AFTER id,
        ADD COLUMN IF NOT EXISTS crime_type VARCHAR(100) NULL AFTER complainant,
        ADD COLUMN IF NOT EXISTS complainant_name VARCHAR(150) NULL AFTER crime_type,
        ADD COLUMN IF NOT EXISTS complainant_contact VARCHAR(50) NULL AFTER complainant_name,
        ADD COLUMN IF NOT EXISTS district_id INT NULL AFTER district,
        ADD COLUMN IF NOT EXISTS tehsil_id INT NULL AFTER tehsil,
        ADD COLUMN IF NOT EXISTS created_by INT NULL,
        ADD COLUMN IF NOT EXISTS assigned_to INT NULL,
        ADD COLUMN IF NOT EXISTS closed_at DATETIME NULL,
        ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL,
        ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    $known = ['Murder', 'Robbery', 'Kidnapping', 'Assault', 'Theft', 'Burglary', 'Fraud', 'Cyber Crime', 'Drug Trafficking',
        'Human Trafficking', 'Extortion', 'Domestic Violence', 'Rape', 'Homicide', 'Terrorism', 'Corruption', 'Bribery',
        'Arson', 'Blackmail', 'Harassment'];
    $in = implode(',', array_map(fn($k) => "'" . $db->real_escape_string($k) . "'", $known));
    $db->query("UPDATE reports SET crime_type = complainant WHERE crime_type IS NULL AND complainant IN ($in)");
    $log('  crime_type filled from legacy complainant column on ' . $db->affected_rows . ' row(s)');
    $left = (int) $db->query("SELECT COUNT(*) FROM reports WHERE crime_type IS NULL AND complainant IS NOT NULL AND complainant <> ''")->fetch_row()[0];
    if ($left) {
        $log("  $left report(s) have an unrecognised legacy complainant value; crime_type left empty for manual review");
    }

    $db->query("UPDATE reports SET status = CASE status
        WHEN 'Pending' THEN 'Open'
        WHEN 'In Progress' THEN 'Under Investigation'
        WHEN 'Completed' THEN 'Closed'
        ELSE status END");
    $log('  status words normalised on ' . $db->affected_rows . ' row(s)');

    $db->query("UPDATE reports SET reference_no = CONCAT('PMS-', YEAR(COALESCE(report_date, created_at)), '-', LPAD(id, 6, '0')) WHERE reference_no IS NULL OR reference_no = ''");
    $log('  reference numbers assigned to ' . $db->affected_rows . ' row(s)');
    $db->query('ALTER TABLE reports ADD UNIQUE INDEX IF NOT EXISTS uq_report_ref (reference_no)');
    $db->query('ALTER TABLE reports ADD INDEX IF NOT EXISTS idx_reports_status (status)');
    $db->query('ALTER TABLE reports ADD INDEX IF NOT EXISTS idx_reports_cnic (id_card_no)');
    $db->query('ALTER TABLE reports ADD INDEX IF NOT EXISTS idx_reports_date (report_date)');
    $db->query('ALTER TABLE reports ADD INDEX IF NOT EXISTS idx_reports_crime (crime_type)');
    $db->query('ALTER TABLE reports ADD INDEX IF NOT EXISTS idx_reports_district (district_id)');

    // District / tehsil text -> ids (same rules as migration 003).
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
    $upd = $db->prepare('UPDATE reports SET district_id = ?, tehsil_id = ? WHERE id = ?');
    $linked = 0;
    $unmatched = [];
    foreach ($db->query('SELECT id, district, tehsil FROM reports WHERE district_id IS NULL AND district IS NOT NULL AND district <> \'\'')->fetch_all(MYSQLI_ASSOC) as $r) {
        $dc = $districts[$norm((string) $r['district'])] ?? [];
        if (count($dc) !== 1) {
            $unmatched[(string) $r['district']] = true;
            continue;
        }
        $did = $dc[0];
        $tc  = $tehsils[$did][$norm((string) $r['tehsil'])] ?? [];
        $tid = count($tc) === 1 ? $tc[0] : null;
        $rid = (int) $r['id'];
        $upd->bind_param('iii', $did, $tid, $rid);
        $upd->execute();
        $linked++;
    }
    $log("  reports: linked $linked row(s) to district ids");
    foreach (array_keys($unmatched) as $u) {
        $log("  reports: UNMATCHED district text '$u' left unlinked");
    }

    $fkExists = fn(string $n) => (bool) $db->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '$n'")->num_rows;
    if (!$fkExists('fk_reports_district')) {
        $db->query('ALTER TABLE reports ADD CONSTRAINT fk_reports_district FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE RESTRICT');
    }
    if (!$fkExists('fk_reports_tehsil')) {
        $db->query('ALTER TABLE reports ADD CONSTRAINT fk_reports_tehsil FOREIGN KEY (tehsil_id) REFERENCES tehsils(id) ON DELETE RESTRICT');
    }
    if (!$fkExists('fk_reports_created_by')) {
        $db->query('ALTER TABLE reports ADD CONSTRAINT fk_reports_created_by FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE SET NULL');
    }
    if (!$fkExists('fk_reports_assigned_to')) {
        $db->query('ALTER TABLE reports ADD CONSTRAINT fk_reports_assigned_to FOREIGN KEY (assigned_to) REFERENCES staff(id) ON DELETE SET NULL');
    }

    $db->query("CREATE TABLE IF NOT EXISTS report_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        report_id INT NOT NULL,
        old_status VARCHAR(50) NULL,
        new_status VARCHAR(50) NOT NULL,
        actor_id INT NULL,
        reason VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_rsh_report (report_id),
        CONSTRAINT fk_rsh_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE RESTRICT,
        CONSTRAINT fk_rsh_actor FOREIGN KEY (actor_id) REFERENCES staff(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $db->query("CREATE TABLE IF NOT EXISTS report_evidence (
        id INT AUTO_INCREMENT PRIMARY KEY,
        report_id INT NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        stored_name VARCHAR(80) NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        size_bytes INT NOT NULL,
        sha256 CHAR(64) NULL,
        uploaded_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        deleted_by INT NULL,
        UNIQUE KEY uq_evidence_stored (stored_name),
        INDEX idx_evidence_report (report_id),
        CONSTRAINT fk_evidence_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE RESTRICT,
        CONSTRAINT fk_evidence_user FOREIGN KEY (uploaded_by) REFERENCES staff(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
};
