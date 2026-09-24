<?php
/**
 * Record-level permissions. See docs/PERMISSIONS.md for the matrix.
 * Every function takes the record row (already loaded) and decides for the current user.
 */
declare(strict_types=1);

/* ---------- Stations ---------- */
function can_manage_station(?int $stationId): bool
{
    if (is_admin()) {
        return true;
    }
    return is_station_admin() && $stationId !== null && $stationId === user_station_id();
}

/** WHERE fragment that limits rows of a table to the stations the user may see. */
function station_scope(string $column): array
{
    if (is_admin()) {
        return ['1=1', '', []];
    }
    $sid = user_station_id();
    if ($sid === null) {
        return ['1=0', '', []];
    }
    return [$column . ' = ?', 'i', [$sid]];
}

/* ---------- Staff records ---------- */
function can_view_staff(array $target): bool
{
    if (is_admin()) {
        return true;
    }
    if (is_station_admin()) {
        return (int) ($target['police_station_id'] ?? 0) === user_station_id();
    }
    return (int) $target['id'] === (int) current_user()['id'];
}
function can_edit_staff(array $target): bool
{
    if (is_admin()) {
        return true;
    }
    if (is_station_admin()) {
        // Same station, and never a head-office admin account.
        return (int) ($target['police_station_id'] ?? 0) === user_station_id()
            && $target['role'] !== ROLE_ADMIN;
    }
    return false;
}
/** Roles the current user may assign to someone else. */
function assignable_roles(): array
{
    if (is_admin()) {
        return ROLES;
    }
    if (is_station_admin()) {
        return [ROLE_STAFF, ROLE_STATION];
    }
    return [];
}
function can_deactivate_staff(array $target): bool
{
    if ((int) $target['id'] === (int) current_user()['id']) {
        return false; // never lock yourself out
    }
    return can_edit_staff($target);
}

/* ---------- Reports ---------- */
function can_view_report(array $r): bool
{
    if (is_admin()) {
        return true;
    }
    return (int) ($r['police_station_id'] ?? 0) === user_station_id();
}
function can_create_report(): bool
{
    return is_logged_in() && (is_admin() || user_station_id() !== null);
}
function can_edit_report(array $r): bool
{
    if (($r['status'] ?? '') === 'Archived') {
        return false;
    }
    if (is_admin()) {
        return true;
    }
    if (is_station_admin()) {
        return can_view_report($r);
    }
    // Staff: only their own report while it is still Open.
    return can_view_report($r) && (int) ($r['created_by'] ?? 0) === (int) current_user()['id'] && $r['status'] === 'Open';
}
function can_change_report_status(array $r): bool
{
    return is_admin() || (is_station_admin() && can_view_report($r));
}
function can_archive_report(array $r): bool
{
    return is_admin() || (is_station_admin() && can_view_report($r));
}
function can_restore_report(array $r): bool
{
    return is_admin();
}
function can_upload_evidence(array $r): bool
{
    return can_view_report($r) && ($r['status'] ?? '') !== 'Archived' && !is_staff_only_viewer($r);
}
function is_staff_only_viewer(array $r): bool
{
    return is_staff() && (int) ($r['created_by'] ?? 0) !== (int) current_user()['id'];
}
function can_download_evidence(array $r): bool
{
    return can_view_report($r);
}

/* ---------- Duties ---------- */
function can_manage_duties(): bool
{
    return is_admin() || is_station_admin();
}
function can_view_duty(array $d): bool
{
    if (is_admin()) {
        return true;
    }
    if (is_station_admin()) {
        return (int) ($d['police_station_id'] ?? 0) === user_station_id();
    }
    return (int) $d['staff_id'] === (int) current_user()['id'];
}
function can_edit_duty(array $d): bool
{
    return is_admin() || (is_station_admin() && (int) ($d['police_station_id'] ?? 0) === user_station_id());
}

/* ---------- Leave ---------- */
function can_review_leave(array $lr): bool
{
    if (is_admin()) {
        return true;
    }
    return is_station_admin() && (int) ($lr['police_station_id'] ?? 0) === user_station_id();
}
function can_view_leave(array $lr): bool
{
    return can_review_leave($lr) || (int) $lr['staff_id'] === (int) current_user()['id'];
}

/* ---------- Alerts ---------- */
function can_manage_alerts(): bool
{
    return is_admin() || is_station_admin();
}
function can_edit_alert(array $a): bool
{
    if (is_admin()) {
        return true;
    }
    return is_station_admin() && $a['target_type'] === 'station' && (int) $a['target_station_id'] === user_station_id();
}

/* ---------- Audit ---------- */
function can_view_audit(): bool
{
    return is_admin();
}
