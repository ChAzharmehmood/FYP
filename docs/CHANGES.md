# Change summary and remaining limitations

Branch `secure-rewrite`, work done 2026-09-23/24. The original 32-page application was kept as the basis: every original URL still exists (some redirect to the new filtered lists), the database was migrated in place, and PHP + MySQL + Bootstrap remain the stack.

## Security and access control

- Central bootstrap (`includes/bootstrap.php`): configuration, prepared-statement DB layer, hardened session (HttpOnly, SameSite=Lax, Secure on HTTPS, 30-minute inactivity timeout, id regeneration on login), CSRF tokens, audit log, authentication and permissions. Every page not on the public allowlist requires a login automatically, including AJAX endpoints (JSON 401/403).
- Role checks plus record-level ownership checks for stations, staff, reports, evidence, duties, leave and alerts (`docs/PERMISSIONS.md`). Station admins cannot escalate roles, move staff, or touch head office accounts. Posted role/station/staff ids are never trusted.
- Passwords hashed (bcrypt via `password_hash`), transparent rehash, migration script for legacy rows, legacy password cookie removed and expired, login throttling (5 failures / 15 min), generic failure messages.
- All SQL through prepared statements; allowlisted sort columns; validated ids, dates, statuses, roles, pagination, filters.
- Output escaping everywhere; JSON for script data; CSV export protected against formula injection.
- Destructive actions are POST-only with CSRF tokens; legacy GET delete URLs show a confirmation or return 405.
- Secrets live in git-ignored `config.local.php` (example file shipped); old SMTP-credential files removed. **The previously committed Gmail app passwords still need owner-side revocation.**
- Deactivated accounts lose access on their next request; password reset/change invalidates other sessions.

## Bugs repaired

Alerts column mismatch; staff dashboard session; dashboard naming; experiment pages removed; dead code in config; alert expiry evaluated in queries (no cleanup job, no page-view side effects); consistent navigation with logout on every page; Post/Redirect/Get with flash messages and double-submit lock; complainant vs crime-type separation; station relationships by id.

## Database

Six versioned migrations (`docs/MIGRATIONS.md`): account state and audit tables; districts/tehsils; station ids on staff/reports/duties with restrictive FKs; report reference numbers, status history, evidence; duty lifecycle and notification state; leave types/balances/history; alert targeting and windows. Fresh-install `schema.sql`, backup/recovery guide, data checks (`migrate.php --check`), optional manual migration to drop legacy columns. Timezone: Asia/Karachi end to end.

## Features added

- Accounts: profile page, change password, forgot/reset password with hashed single-use 30-minute tokens and rate limit, secure CLI admin creation (no default credentials).
- Stations: list/search/sort/export, add, edit (rename keeps links), deactivate, delete only when empty.
- Staff: head office and station-scoped lists with filters, add/edit/deactivate, duplicate checks, CNIC validation and masking.
- Reports: reference numbers, Open → Under Investigation → Closed workflow with reasons and history, reopen, archive/restore, officer assignment, evidence upload (extension + MIME sniffing + size + count, random names, outside web root, authenticated download, soft delete), print view (browser Save as PDF), CNIC search, list with filters/sort/pagination/CSV.
- Duties: create/edit/cancel/complete, list and calendar views, overlap and approved-leave conflict detection with explicit override, overnight support, email sent after save with failure recorded and manual retry (no duplicate duties).
- Leave: configurable types and yearly allowances (demo defaults), inclusive day count, overlap and balance validation, approve with day count / reject with reason, guarded update so concurrent approvals deduct once, history, withdrawal of pending requests.
- Alerts: everyone / district / station targeting enforced server-side, start and expiry, edit, deactivate, archive.
- Audit log: logins (success/failure/blocked), password events, create/update/status/archive/restore/approval/deactivation/export events with actor, entity, safe metadata, IP; viewer and CSV for head office. It is a normal table, not tamper-proof.
- Dashboards: role-scoped counts and Chart.js charts (month, status, crime type, district, station); analysis page with date range and export.

## UI

Shared layout (sidebar navigation per role, header with role and station, footer), one Bootstrap 5.3.3, one stylesheet, one JS file; responsive tables, labelled form fields, visible focus, confirmation dialogs (in addition to server checks), empty states, print stylesheet.

## Verification

`tests/run_tests.php`: 127 automated checks over HTTP with synthetic data, including fresh install and upgrade in temporary databases; all passing (`docs/TEST_RESULTS.md`). `php -l` clean for every file.

## Remaining limitations (honest list)

- **Email delivery was not exercised** against a real SMTP server here (disabled in the local configuration); only the failure path is covered by tests.
- **HTTPS**: the Secure cookie flag and HSTS apply only when the site is served over HTTPS; local XAMPP is HTTP. Deploying publicly needs TLS and a non-root MySQL user.
- **No persistent "remember me"**: deliberately removed rather than shipped insecurely.
- **Legacy text columns** (`police_station_name`, `district`, `tehsil`, `complainant`, `leave_type`, `alerts.is_active`) are still present and kept in sync; dropping them is the optional manual migration 900.
- **Districts/tehsils** are the five districts the old form knew (plus any found in data); the remaining AJK districts must be added by an admin — there is no UI for that yet (insert into `districts`/`tehsils`).
- **Leave allowances** (Casual 10, Sick 8, Annual 15) are demonstration defaults, not police policy; balances are per calendar year with no carry-over.
- **Audit log** is append-only by convention, not cryptographically protected; someone with database access can edit it.
- **Report print view** is a plain layout, not an official FIR format; no server-side PDF generation.
- **Evidence** is not virus-scanned; files are validated by extension, sniffed MIME type and size only.
- **Accessibility** was reviewed by inspection (labels, focus styles, contrast, keyboard-reachable controls), not with a screen reader or an automated audit.
- **Concurrency** is handled for leave approval and report status changes (guarded updates); simultaneous edits of the same form overwrite each other last-write-wins.
- **Station admin registration of head office admins** is impossible by design; a second head office admin is created by an existing one or via `create_admin.php`.
