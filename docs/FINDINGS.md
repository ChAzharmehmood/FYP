# Phase 1 findings — verified against the source and the live database

Baseline: original code base (commit `7b6682d`, 32 PHP pages) inspected file by file on 2026-09-23/24 on a Windows/XAMPP install with the reconstructed `db_pms` schema. Every item below was **reproduced in the code** unless marked otherwise. The earlier project report was used as a lead only.

Status legend: **Verified → Fixed** (defect confirmed and repaired in this branch), **Already fixed** (repaired before this work), **Not reproduced** (report claim could not be confirmed).

## Security

| # | Issue | Severity | Affected files (original) | Evidence from the original code | Fix | Verified by |
|---|---|---|---|---|---|---|
| S1 | No authentication on 10 pages | Critical | `delete_report.php`, `delete_staff.php`, `edit_report.php`, `generate_report.php`, `alerts.php`, `report_analysis.php`, `search_criminal.php`, `view_policestation.php`, `staff_dashboard.php`, `get_tehsils.php` | None of these files called `session_start()` or read `$_SESSION`; `delete_report.php` ran `DELETE FROM reports WHERE id = $id` on a plain GET | `includes/bootstrap.php` protects every page not in `PUBLIC_ROUTES`; role and record checks in `includes/auth.php` + `includes/permissions.php` | Tests [2], [3], [4] |
| S2 | SQL injection by string interpolation | Critical | `delete_report.php`, `edit_report.php`, `search_by_*.php`, `get_tehsils.php`, `alerts.php`, `admin_dashboard.php`, `dashboard.php`, `staff_dashboard.php`, `admin_leave_requests.php`, `search_criminal.php` | e.g. `"UPDATE reports SET police_station_name = '$police_station_name' … WHERE id = $id"`; `"SELECT * FROM reports WHERE district = '$district'"` | All queries go through `db_stmt()/db_all()/db_exec()` prepared statements; sort columns via `db_order_by()` allowlist; ids/dates/enums validated | Test [9]; `grep` for `\$_(GET|POST)` inside SQL strings returns nothing |
| S3 | Plain-text passwords stored and compared | Critical | `add_staff.php`, `index.php`, `admin_login.php`, `login_station_admin.php` | `if ($user && $password == $user['password'])` | `password_hash/verify/needs_rehash`; `database/scripts/hash_passwords.php` (re-runnable); a legacy plaintext row is hashed on first successful login; no permanent plaintext fallback once the script has run | Test [11] |
| S4 | Password stored in a browser cookie ("remember me") | High | `admin_login.php` lines 26-27 | `setcookie("password", $password, time() + 86400*30)` | Removed; `includes/session.php` actively expires legacy `username`/`password` cookies; persistent login not offered | Test [11] cookie check |
| S5 | SMTP credentials committed in source | High | `send_email.php`, `assign_duties.php` | Gmail address + app password literals | Moved to git-ignored `config.local.php`; both files removed. **Revocation is owner-side**: the two app passwords in git history must be regenerated in the Google accounts; this was *not* done here and is not claimed | Manual |
| S6 | Output not escaped (stored XSS) | Medium | most list pages | `echo $row['accused_name']` etc. | `e()` on every dynamic value; JSON via `json_for_script()` | Test [10] |
| S7 | No CSRF protection; destructive GET links | Medium | all forms; `delete_report.php`, `delete_staff.php`, `logout.php` | No tokens anywhere; `<a href="delete_report.php?id=…">` | `csrf_field()/csrf_verify()` on every POST; destructive actions POST-only; GET on legacy delete URLs shows a confirmation (report) or 405 (staff) | Tests [7], [8] |
| S8 | Station admins could edit/delete other stations' records | Medium | `edit_report.php`, `delete_report.php` | Only `$_SESSION['role']` checked, never the record's station | Record-level checks (`can_view_report`, `can_edit_staff`, …); staff/report/duty/leave scoped by `police_station_id` | Tests [3], [5], [14], [15] |
| S9 | Role/station taken from posted fields | Medium | `add_staff.php`, `manage_staff.php`, `assign_duties.php` | `$role = $_POST['role']` inserted as-is; station admin could set `role=admin` | `assignable_roles()`; station admins cannot promote to admin, move staff across stations, edit admin accounts or change own role; posted station ids ignored for station users | Test [5] |
| S10 | `display_errors` on; DB errors echoed | Low | `generate_report.php`, `config.php` | `ini_set('display_errors', 1)`, `die("Connection failed: " . $conn->connect_error)` | Errors logged to `storage/logs/php-error.log`; generic message unless `app_debug` | Manual |
| S11 | Deactivation not possible; deleting staff cascaded duties/leave | Medium | `delete_staff.php`, schema `ON DELETE CASCADE` | `DELETE FROM staff WHERE id = ?` | `is_active` flag + `session_version`; FKs changed to `RESTRICT`; deactivated users are cut off on the next request | Test [6] |
| S12 | No login throttling, no session hardening | Medium | login pages | unlimited attempts; default cookie flags; no regeneration | `login_attempts` table (5 per 15 min per user/IP), `session_regenerate_id` on login, HttpOnly/SameSite=Lax/Secure-on-HTTPS, 30-min inactivity timeout | Tests [1], [21] |

## Bugs (reported by the project report, each verified)

| # | Reported issue | Result | Evidence | Fix |
|---|---|---|---|---|
| B1 | `alerts.php` writes `alert_message`, dashboards read `message` | **Verified → Fixed** | `INSERT INTO alerts (alert_message)` vs `SELECT * FROM alerts … $alert['message']` | Migration 006 merges into `message`, keeps conflicting text in `legacy_alert_message`, drops the old column |
| B2 | `staff_dashboard.php` has no session and reads unset `staff_name` | **Verified → Fixed** | no `session_start()`; `$_SESSION['staff_name']` never set anywhere | Rewritten on the shared auth; shows only live, targeted alerts |
| B3 | `dashboard.php` vs `admin_dashboard.php` role confusion | **Verified → Fixed** | admin → `dashboard.php`, station admin → `admin_dashboard.php` | URLs kept; titles now "Head Office Dashboard" / "Station Dashboard" / "My Dashboard"; role-specific menus |
| B4 | `test.php` / `text.php` leftovers | **Verified → Fixed** | `text.php` did not even parse (`php -l` error); `test.php` had an unauthenticated insert form | Both removed, plus `send_email.php` (unused helper with credentials) |
| B5 | Unreachable code in `config.php` | **Verified → Fixed** | insert/redirect after `die()` | `config.php` rewritten as a config loader |
| B6 | Alert expiry inconsistent across dashboards | **Verified → Fixed** | `dashboard.php` filtered by 3 hours, `staff_dashboard.php` showed everything, `alerts.php` ran an `UPDATE` on page view | Expiry is a `starts_at/expires_at` window evaluated inside the SELECT; no cleanup job needed |
| B7 | Broken links / missing logout / duplicate submissions | **Verified → Fixed** | `admin_dashboard.php` had no logout; nav differed per page; forms re-inserted on refresh | Shared sidebar with logout on every page; Post/Redirect/Get with flash messages; submit buttons locked client-side |
| B8 | "Complainant" field held the crime type | **Verified → Fixed** | `<select name="complainant"><option>Murder…` | `crime_type` column backfilled from the 20 known values; new `complainant_name`/`complainant_contact`; legacy column untouched |
| B9 | Stations linked by name text | **Verified → Fixed** | `staff.police_station_name`, `reports.police_station_name`, `duties.police_station_name` | `police_station_id` on all three with FKs; legacy text kept in sync until the optional 900 migration |
| B10 | Two Bootstrap versions, CSS copied into 28 pages | **Verified → Fixed** | 5.3.0-alpha3 and 5.3.2 | One Bootstrap 5.3.3, `assets/css/app.css`, shared layout |
| B11 | Report claim: `send_email.php` "sends alerts" | **Not reproduced** | The file was a standalone PHPMailer sample with hard-coded recipient; nothing included it | Removed |

## Baseline of working flows (before changes)

Logged in and exercised all three roles with curl on 2026-09-23: login for each role, filing a report, submitting leave, assigning a duty (email failed: credentials), creating an alert. All pages rendered without PHP errors. Those flows still work after the rewrite (see `docs/TEST_RESULTS.md`).
