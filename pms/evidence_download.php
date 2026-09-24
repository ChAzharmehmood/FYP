<?php
/** Authenticated, authorised evidence download. Files live outside the web root. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/reports.php';
$me = require_login();
$ev = db_one('SELECT * FROM report_evidence WHERE id = ? AND deleted_at IS NULL', 'i', [get_int('id', 0) ?? 0]);
if (!$ev) {
    not_found('File not found.');
}
$report = report_load_or_404((int) $ev['report_id']);
if (!can_download_evidence($report)) {
    deny('You may not download this file.');
}
$path = evidence_dir() . '/' . basename((string) $ev['stored_name']);
if (!is_file($path)) {
    error_log('Evidence file missing on disk: ' . $path);
    not_found('The file is missing on the server. Contact the administrator.');
}
audit_log('evidence.download', 'report', (int) $report['id'], ['evidence_id' => (int) $ev['id']]);
while (ob_get_level() > 0) {
    ob_end_clean();
}
$inline = in_array($ev['mime_type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'], true) && get_str('view') === '1';
header('Content-Type: ' . $ev['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $ev['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; style-src \'unsafe-inline\'');
header('Cache-Control: private, no-store');
readfile($path);
exit;
