<?php
/** Crime reports: lookups, status workflow, evidence files. */
declare(strict_types=1);

const CRIME_TYPES = ['Murder', 'Robbery', 'Kidnapping', 'Assault', 'Theft', 'Burglary', 'Fraud', 'Cyber Crime', 'Drug Trafficking',
    'Human Trafficking', 'Extortion', 'Domestic Violence', 'Rape', 'Homicide', 'Terrorism', 'Corruption', 'Bribery',
    'Arson', 'Blackmail', 'Harassment', 'Other'];
const CRIME_SECTIONS = ['302', '307', '376', '354', '420', '498A', '408', '279', '304A', '366', '506', '379', '409', 'other'];
const REPORT_STATUSES = ['Open', 'Under Investigation', 'Closed', 'Archived'];

/** Status changes allowed from each status. Archive/restore are separate actions. */
const REPORT_TRANSITIONS = [
    'Open'                => ['Under Investigation', 'Closed'],
    'Under Investigation' => ['Open', 'Closed'],
    'Closed'              => ['Open'],   // reopen
    'Archived'            => [],
];

const EVIDENCE_TYPES = [
    // extension => allowed detected MIME types
    'jpg'  => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'],
    'pdf'  => ['application/pdf'],
    'txt'  => ['text/plain'],
    'doc'  => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'mp3'  => ['audio/mpeg'], 'mp4' => ['video/mp4'],
];

function report_find(int $id): ?array
{
    return db_one(
        'SELECT r.*, ps.police_station_name AS station_name, d.name AS district_name, t.name AS tehsil_name,
                c.name AS created_by_name, a.name AS assigned_to_name
         FROM reports r
         LEFT JOIN police_stations ps ON ps.id = r.police_station_id
         LEFT JOIN districts d ON d.id = r.district_id
         LEFT JOIN tehsils t ON t.id = r.tehsil_id
         LEFT JOIN staff c ON c.id = r.created_by
         LEFT JOIN staff a ON a.id = r.assigned_to
         WHERE r.id = ?',
        'i',
        [$id]
    );
}

/** Load a report the current user may see, or stop with 404. */
function report_load_or_404(int $id): array
{
    $r = $id > 0 ? report_find($id) : null;
    if (!$r || !can_view_report($r)) {
        not_found('Report not found.');
    }
    return $r;
}

function report_display_station(array $r): string
{
    return (string) ($r['station_name'] ?? $r['police_station_name'] ?? '');
}
function report_display_district(array $r): string
{
    return (string) ($r['district_name'] ?? $r['district'] ?? '');
}
function report_display_tehsil(array $r): string
{
    return (string) ($r['tehsil_name'] ?? $r['tehsil'] ?? '');
}
function report_display_crime_type(array $r): string
{
    return (string) ($r['crime_type'] ?? '');
}

function report_next_reference(): string
{
    // Ids are assigned by the database; the reference is derived from the id after insert.
    return '';
}
function report_reference_for(int $id, ?string $reportDate): string
{
    $year = $reportDate ? date('Y', strtotime($reportDate)) : date('Y');
    return sprintf('PMS-%s-%06d', $year, $id);
}

function report_status_options(array $r): array
{
    if (!can_change_report_status($r)) {
        return [];
    }
    return REPORT_TRANSITIONS[$r['status']] ?? [];
}

/** Change status with history + audit. Returns error string or null. */
function report_change_status(array $r, string $new, string $reason): ?string
{
    if (!in_array($new, report_status_options($r), true)) {
        return 'That status change is not allowed.';
    }
    $reason = mb_substr(trim($reason), 0, 500);
    if ($new === 'Closed' && $reason === '') {
        return 'Please give a reason when closing a report.';
    }
    db_begin();
    $n = db_exec(
        'UPDATE reports SET status = ?, closed_at = IF(? = "Closed", NOW(), NULL) WHERE id = ? AND status = ?',
        'ssis',
        [$new, $new, (int) $r['id'], $r['status']]
    );
    if ($n !== 1) {
        db_rollback();
        return 'The report was changed by someone else. Reload and try again.';
    }
    db_exec('INSERT INTO report_status_history (report_id, old_status, new_status, actor_id, reason) VALUES (?, ?, ?, ?, ?)',
        'issis', [(int) $r['id'], $r['status'], $new, (int) current_user()['id'], $reason !== '' ? $reason : null]);
    db_commit();
    audit_log('report.status', 'report', (int) $r['id'], ['from' => $r['status'], 'to' => $new, 'reason' => $reason]);
    return null;
}

function report_archive(array $r, string $reason, bool $restore = false): ?string
{
    if ($restore ? !can_restore_report($r) : !can_archive_report($r)) {
        return 'You may not do that.';
    }
    if ($restore && $r['status'] !== 'Archived') {
        return 'Only archived reports can be restored.';
    }
    if (!$restore && $r['status'] === 'Archived') {
        return 'This report is already archived.';
    }
    $reason = mb_substr(trim($reason), 0, 500);
    if (!$restore && $reason === '') {
        return 'Please give a reason for archiving.';
    }
    // Restoring returns the report to its status before archiving (from history), else Open.
    $new = 'Archived';
    if ($restore) {
        $prev = db_value('SELECT old_status FROM report_status_history WHERE report_id = ? AND new_status = "Archived" ORDER BY id DESC LIMIT 1', 'i', [(int) $r['id']], 'Open');
        $new  = in_array($prev, ['Open', 'Under Investigation', 'Closed'], true) ? $prev : 'Open';
    }
    db_begin();
    $n = db_exec('UPDATE reports SET status = ?, archived_at = IF(? = "Archived", NOW(), NULL) WHERE id = ? AND status = ?',
        'ssis', [$new, $new, (int) $r['id'], $r['status']]);
    if ($n !== 1) {
        db_rollback();
        return 'The report was changed by someone else. Reload and try again.';
    }
    db_exec('INSERT INTO report_status_history (report_id, old_status, new_status, actor_id, reason) VALUES (?, ?, ?, ?, ?)',
        'issis', [(int) $r['id'], $r['status'], $new, (int) current_user()['id'], $reason !== '' ? $reason : null]);
    db_commit();
    audit_log($restore ? 'report.restore' : 'report.archive', 'report', (int) $r['id'], ['reason' => $reason]);
    return null;
}

/* ---------------- Evidence ---------------- */
function evidence_dir(): string
{
    $dir = rtrim((string) config('storage_path'), '/\\') . '/evidence';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return $dir;
}

/** Validate and store one uploaded file. Returns [ok, message]. */
function evidence_store(array $r, array $file): array
{
    if (!can_upload_evidence($r)) {
        return [false, 'You may not add evidence to this report.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msgs = [UPLOAD_ERR_INI_SIZE => 'File is too large.', UPLOAD_ERR_FORM_SIZE => 'File is too large.', UPLOAD_ERR_PARTIAL => 'Upload was interrupted.', UPLOAD_ERR_NO_FILE => 'No file was selected.'];
        return [false, $msgs[$file['error']] ?? 'Upload failed.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [false, 'Invalid upload.'];
    }
    $max = (int) config('upload_max_bytes', 5 * 1024 * 1024);
    if ((int) $file['size'] > $max || (int) $file['size'] <= 0) {
        return [false, 'Each file must be between 1 byte and ' . round($max / 1048576, 1) . ' MB.'];
    }
    $original = mb_substr(basename((string) $file['name']), 0, 255);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!isset(EVIDENCE_TYPES[$ext])) {
        return [false, 'File type .' . $ext . ' is not allowed. Allowed: ' . implode(', ', array_keys(EVIDENCE_TYPES)) . '.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($file['tmp_name']);
    if (!in_array($mime, EVIDENCE_TYPES[$ext], true)) {
        return [false, 'The content of "' . $original . '" does not match its extension (' . $mime . ').'];
    }
    if ($ext === 'txt' && preg_match('/<\?php|<script/i', (string) file_get_contents($file['tmp_name'], false, null, 0, 65536))) {
        return [false, 'Text files containing code are not accepted.'];
    }
    $stored = bin2hex(random_bytes(20)) . '.' . $ext;
    $dest   = evidence_dir() . '/' . $stored;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return [false, 'Could not save the file on the server.'];
    }
    @chmod($dest, 0640);
    db_exec(
        'INSERT INTO report_evidence (report_id, original_name, stored_name, mime_type, size_bytes, sha256, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
        'isssisi',
        [(int) $r['id'], $original, $stored, $mime, (int) $file['size'], hash_file('sha256', $dest), (int) current_user()['id']]
    );
    audit_log('evidence.upload', 'report', (int) $r['id'], ['evidence_id' => db_insert_id(), 'name' => $original, 'size' => (int) $file['size']]);
    return [true, $original];
}

function evidence_list(int $reportId): array
{
    return db_all('SELECT ev.*, s.name AS uploaded_by_name FROM report_evidence ev LEFT JOIN staff s ON s.id = ev.uploaded_by WHERE ev.report_id = ? AND ev.deleted_at IS NULL ORDER BY ev.id', 'i', [$reportId]);
}

/** Normalise $_FILES['evidence'] (multiple) into a list of single-file arrays. */
function uploaded_files(string $field): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
        return [];
    }
    $out = [];
    foreach ($_FILES[$field]['name'] as $i => $name) {
        if ($_FILES[$field]['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = ['name' => $name, 'type' => $_FILES[$field]['type'][$i], 'tmp_name' => $_FILES[$field]['tmp_name'][$i], 'error' => $_FILES[$field]['error'][$i], 'size' => $_FILES[$field]['size'][$i]];
    }
    return $out;
}
