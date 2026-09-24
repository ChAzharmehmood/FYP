<?php
/** Leave requests: types, balances, overlap checks, review with double-approval protection. */
declare(strict_types=1);

function leave_types_active(): array
{
    return db_all('SELECT * FROM leave_types WHERE is_active = 1 ORDER BY name');
}

function leave_type_find(int $id): ?array
{
    return db_one('SELECT * FROM leave_types WHERE id = ?', 'i', [$id]);
}

/** Approved days used in a calendar year for one type. */
function leave_used_days(int $staffId, int $typeId, int $year): int
{
    return (int) db_value(
        'SELECT COALESCE(SUM(approved_days), 0) FROM leave_requests
         WHERE staff_id = ? AND leave_type_id = ? AND status = "approved" AND YEAR(leave_start_date) = ?',
        'iii',
        [$staffId, $typeId, $year],
        0
    );
}

/** [allowance|null, used, remaining|null] */
function leave_balance(int $staffId, array $type, int $year): array
{
    $used = leave_used_days($staffId, (int) $type['id'], $year);
    $allow = $type['annual_allowance'] !== null ? (int) $type['annual_allowance'] : null;
    return [$allow, $used, $allow === null ? null : max(0, $allow - $used)];
}

function leave_find(int $id): ?array
{
    return db_one(
        'SELECT lr.*, s.name AS staff_name, s.police_station_id, s.designation, lt.name AS type_name, lt.annual_allowance,
                rv.name AS reviewed_by_name
         FROM leave_requests lr
         JOIN staff s ON s.id = lr.staff_id
         LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
         LEFT JOIN staff rv ON rv.id = lr.reviewed_by
         WHERE lr.id = ?',
        'i',
        [$id]
    );
}

function leave_overlaps(int $staffId, string $start, string $end, ?int $excludeId = null): array
{
    return db_all(
        'SELECT id, leave_start_date, leave_end_date, status FROM leave_requests
         WHERE staff_id = ? AND id <> ? AND status IN ("pending", "approved") AND leave_start_date <= ? AND leave_end_date >= ?',
        'iiss',
        [$staffId, (int) $excludeId, $end, $start]
    );
}

function leave_days_between(string $start, string $end): int
{
    return (int) ((strtotime($end) - strtotime($start)) / 86400) + 1;
}

/**
 * Approve or reject. The UPDATE is guarded by "status = pending" so two admins
 * clicking approve at the same moment cannot deduct the balance twice.
 * Returns an error string or null.
 */
function leave_review(array $lr, string $action, ?int $approvedDays, string $reason): ?string
{
    if (!can_review_leave($lr)) {
        return 'You may not review this request.';
    }
    if ($lr['status'] !== 'pending') {
        return 'This request has already been ' . $lr['status'] . '.';
    }
    $reason = mb_substr(trim($reason), 0, 500);
    if ($action === 'approve') {
        $max = (int) $lr['requested_days'];
        if ($approvedDays === null || $approvedDays < 1 || $approvedDays > $max) {
            return "Approved days must be between 1 and $max.";
        }
        if ($lr['annual_allowance'] !== null && $lr['leave_type_id'] !== null) {
            $used = leave_used_days((int) $lr['staff_id'], (int) $lr['leave_type_id'], (int) date('Y', strtotime($lr['leave_start_date'])));
            $remaining = (int) $lr['annual_allowance'] - $used;
            if ($approvedDays > $remaining) {
                return "Only $remaining day(s) of {$lr['type_name']} leave remain this year for this staff member.";
            }
        }
        $newStatus = 'approved';
    } elseif ($action === 'reject') {
        if ($reason === '') {
            return 'Please give a reason for rejecting.';
        }
        $approvedDays = 0;
        $newStatus = 'rejected';
    } else {
        return 'Unknown action.';
    }
    db_begin();
    $n = db_exec(
        'UPDATE leave_requests SET status = ?, approved_days = ?, reviewed_by = ?, reviewed_at = NOW(), review_reason = ? WHERE id = ? AND status = "pending"',
        'siisi',
        [$newStatus, $approvedDays, (int) current_user()['id'], $reason !== '' ? $reason : null, (int) $lr['id']]
    );
    if ($n !== 1) {
        db_rollback();
        return 'This request was just reviewed by someone else.';
    }
    db_exec('INSERT INTO leave_request_history (leave_request_id, action, actor_id, approved_days, reason) VALUES (?, ?, ?, ?, ?)',
        'isiis', [(int) $lr['id'], $newStatus, (int) current_user()['id'], $approvedDays, $reason !== '' ? $reason : null]);
    db_commit();
    audit_log('leave.' . $newStatus, 'leave_request', (int) $lr['id'], ['staff_id' => $lr['staff_id'], 'days' => $approvedDays, 'reason' => $reason]);
    return null;
}

/** Staff may withdraw their own pending request before its start date. */
function leave_withdraw(array $lr): ?string
{
    if ((int) $lr['staff_id'] !== (int) current_user()['id']) {
        return 'You may only withdraw your own requests.';
    }
    if ($lr['status'] !== 'pending') {
        return 'Only pending requests can be withdrawn.';
    }
    if (strtotime((string) $lr['leave_start_date']) < strtotime(date('Y-m-d'))) {
        return 'A request whose start date has passed cannot be withdrawn.';
    }
    $n = db_exec('UPDATE leave_requests SET status = "withdrawn", withdrawn_at = NOW() WHERE id = ? AND status = "pending"', 'i', [(int) $lr['id']]);
    if ($n !== 1) {
        return 'The request could not be withdrawn.';
    }
    db_exec('INSERT INTO leave_request_history (leave_request_id, action, actor_id) VALUES (?, "withdrawn", ?)', 'ii', [(int) $lr['id'], (int) current_user()['id']]);
    audit_log('leave.withdrawn', 'leave_request', (int) $lr['id']);
    return null;
}
