<?php
/** Duties: lookups, conflict detection, email notification with retry. */
declare(strict_types=1);

const SHIFT_TYPES = ['morning', 'evening', 'night'];

function duty_find(int $id): ?array
{
    return db_one(
        'SELECT d.*, s.name AS staff_name, s.email AS staff_email, s.designation, ps.police_station_name AS station_name
         FROM duties d JOIN staff s ON s.id = d.staff_id
         LEFT JOIN police_stations ps ON ps.id = d.police_station_id
         WHERE d.id = ?',
        'i',
        [$id]
    );
}

function duty_load_or_404(int $id): array
{
    $d = $id > 0 ? duty_find($id) : null;
    if (!$d || !can_view_duty($d)) {
        not_found('Duty not found.');
    }
    return $d;
}

/**
 * Conflicts for a staff member in [start, end): other scheduled duties that
 * overlap, and approved leave covering any day of the duty. Overnight duties
 * (end after midnight) are ordinary datetime ranges so they compare correctly.
 */
function duty_conflicts(int $staffId, string $start, string $end, ?int $excludeId = null): array
{
    $problems = [];
    $duties = db_all(
        'SELECT id, duty_description, start_time, end_time FROM duties
         WHERE staff_id = ? AND status = "scheduled" AND id <> ? AND start_time < ? AND end_time > ?',
        'iiss',
        [$staffId, (int) $excludeId, $end, $start]
    );
    foreach ($duties as $d) {
        $problems[] = 'Overlaps duty #' . $d['id'] . ' (' . fmt_datetime($d['start_time']) . ' to ' . fmt_datetime($d['end_time']) . ')';
    }
    $leaves = db_all(
        'SELECT id, leave_start_date, leave_end_date FROM leave_requests
         WHERE staff_id = ? AND status = "approved" AND leave_start_date <= DATE(?) AND leave_end_date >= DATE(?)',
        'iss',
        [$staffId, $end, $start]
    );
    foreach ($leaves as $l) {
        $problems[] = 'Staff is on approved leave ' . fmt_date($l['leave_start_date']) . ' to ' . fmt_date($l['leave_end_date']);
    }
    return $problems;
}

/** Send (or resend) the assignment email. Never affects the saved duty row except its notify_* columns. */
function duty_notify(array $d): array
{
    $email = (string) ($d['staff_email'] ?? '');
    $html  = '<h3>Duty assignment</h3><p>Dear ' . e($d['staff_name']) . ',</p><p>You have been assigned a duty:</p><ul>'
        . '<li><strong>Description:</strong> ' . e($d['duty_description']) . '</li>'
        . '<li><strong>Start:</strong> ' . fmt_datetime($d['start_time']) . '</li>'
        . '<li><strong>End:</strong> ' . fmt_datetime($d['end_time']) . '</li>'
        . '<li><strong>Shift:</strong> ' . e(ucfirst((string) $d['shift_type'])) . '</li>'
        . '<li><strong>Location:</strong> ' . e($d['Duty_location']) . '</li>'
        . '</ul><p>Police Management System</p>';
    [$ok, $err] = send_mail($email, (string) $d['staff_name'], 'New duty assigned', $html);
    db_exec(
        'UPDATE duties SET notify_status = ?, notify_error = ?, notify_attempts = notify_attempts + 1, notified_at = IF(?, NOW(), notified_at) WHERE id = ?',
        'ssii',
        [$ok ? 'sent' : 'failed', $ok ? null : mb_substr($err, 0, 255), $ok ? 1 : 0, (int) $d['id']]
    );
    audit_log($ok ? 'duty.notified' : 'duty.notify_failed', 'duty', (int) $d['id'], $ok ? [] : ['error' => $err]);
    return [$ok, $err];
}

function duty_status_badge(array $d): string
{
    if ($d['status'] === 'scheduled' && strtotime((string) $d['end_time']) < time()) {
        return '<span class="badge text-bg-light border">Past (not marked)</span>';
    }
    return status_badge((string) $d['status']);
}
