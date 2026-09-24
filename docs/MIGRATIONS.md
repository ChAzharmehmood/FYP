# Database migrations

Migrations live in `database/migrations/` and are applied in file-name order by `database/migrate.php`, which records each one in `schema_migrations`. A fresh install uses `database/schema.sql` instead (it already contains the `schema_migrations` rows).

```bat
php database\migrate.php --status     list applied / pending
php database\migrate.php --check      data checks only (duplicates, unmatched names, plaintext passwords)
php database\migrate.php --dry-run    show what would run
php database\migrate.php              apply pending migrations, then run the checks
```

Set `PMS_DB_NAME=<db>` in the environment to run the migrator against another database (used by the tests).

## Before you migrate

1. **Back up**: `mysqldump -u root --databases db_pms > backups\before.sql` (see BACKUP_AND_RECOVERY.md).
2. Run `--check`. The migrator refuses to start while station names or staff login names are duplicated, because those must become unique and the mapping must not be guessed. Rename the duplicates in phpMyAdmin and re-run.
3. MySQL/MariaDB **auto-commits DDL**, so a migration that fails half-way leaves its completed statements in place. Statements use `IF NOT EXISTS`, so fixing the cause and re-running is safe; restoring the backup is the full rollback.

## The migrations

| File | What it does | Data handling | Reversible? |
|---|---|---|---|
| `001_security_core.sql` | `staff.is_active`, `session_version`, `last_login_at`, `password_changed_at`, timestamps; unique index on `staff.name`; tables `login_attempts`, `password_resets`, `audit_logs` | none | Yes: drop the new columns/tables |
| `002_geography_tables.sql` | `districts`, `tehsils` seeded with the list that was hard-coded in the old report form (5 districts, 20 tehsils). Not an official dataset. | none | Yes, until 003/005 link to them |
| `003_geography_backfill.php` | `police_stations.district_id/tehsil_id/is_active`; links each station's free-text district/tehsil; unique station name; FKs | Matching is trim + case-insensitive, ignores a trailing " District"/" Tehsil", maps the two spellings the old form used (Muzafarabad→Muzaffarabad, Bimber→Bhimber). Text with no match is **added** as a new district/tehsil and logged. Ambiguous matches are left NULL and logged. Text columns untouched. | Yes: drop the id columns |
| `004_station_ids.php` | `police_station_id` on `staff`, `reports`, `duties`, backfilled by exact (trimmed, case-insensitive) station-name match; FKs; `duties`/`leave_requests` FKs changed from `ON DELETE CASCADE` to `RESTRICT` | Unmatched names are logged and left NULL, never guessed. Text columns kept and maintained by the app. | Yes: drop the id columns and re-create the old FKs |
| `005_reports.php` | `reference_no` (`PMS-<year>-<id padded>`), `crime_type`, `complainant_name`, `complainant_contact`, `district_id/tehsil_id`, `created_by`, `assigned_to`, `closed_at`, `archived_at`; tables `report_status_history`, `report_evidence`; indexes | `crime_type` copied from legacy `complainant` only where it equals one of the 20 dropdown values; other values left for manual review. Status words normalised: `Pending→Open`, `In Progress→Under Investigation`, `Completed→Closed` (reverse mapping is exactly that). Ids never change. | Yes for columns; the status rename is reversible with the mapping above |
| `006_duties_leave_alerts.php` | duties: `status`, `notify_status/error/attempts`, `notified_at`, `cancel_reason`, `completed_at`, `created_by`; `leave_types` (Casual 10 / Sick 8 / Annual 15 — demo defaults, plus any type names found in data), `leave_requests.leave_type_id/requested_days/reviewed_*`, `leave_request_history`; alerts: `title`, targeting, `starts_at/expires_at`, `status`, `created_by` | **Alerts**: `message = COALESCE(message, alert_message)`; where both differ the old value is kept in `legacy_alert_message`; then **`alert_message` is dropped** — the only irreversible DDL step, with the data preserved. Existing alerts get `expires_at = created_at + 3 h` (the old behaviour). Leave status lower-cased. | Column drop is irreversible (data retained); everything else reversible |
| `900_drop_legacy_station_names.sql.manual` | Drops the legacy text columns (`police_station_name` etc.) | **Optional and irreversible.** Only after `--check` reports zero unmatched names and with a fresh backup. Not applied automatically. | No |

## After migrating

```bat
php database\scripts\hash_passwords.php      hash every remaining plaintext password (re-runnable, never double-hashes)
php database\migrate.php --check             should print "all checks passed"
```

Any legacy row that could not be linked is listed by `--check`; fix it through the Police Stations / Staff pages and re-run the check.

## Upgrading a database created by the original project

The original project shipped no SQL file. `pms/db_pms.sql` (reconstructed earlier) or the backup in `backups/` is what the migrations were tested against; `tests/run_tests.php` re-imports that legacy dump into a temporary database and upgrades it on every run.
