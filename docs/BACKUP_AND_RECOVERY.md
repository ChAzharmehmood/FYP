# Backup, upgrade and recovery

## What to back up

| Item | Where | How |
|---|---|---|
| Database | MySQL `db_pms` | `mysqldump` (below) |
| Evidence files | `storage/evidence/` (outside the web root) | copy the folder; file names are random, the mapping to reports is in the `report_evidence` table |
| Configuration | `pms/config.local.php` | copy; contains the DB and SMTP secrets, keep it out of git |
| Logs | `storage/logs/` | optional |

## Taking a backup (Windows / XAMPP)

```bat
set TS=%DATE:~-4%-%DATE:~3,2%-%DATE:~0,2%
C:\xampp\mysql\bin\mysqldump.exe -u root --databases db_pms --routines --single-transaction > backups\db_pms_%TS%.sql
xcopy /E /I storage\evidence backups\evidence_%TS%
```

`backups/` is git-ignored. Keep copies somewhere else as well (the project folder is inside OneDrive on the development machine, which is not a substitute for a dated dump).

## Upgrade procedure

1. Back up (above).
2. `php database\migrate.php --check` and fix anything it lists (duplicate names block the upgrade by design).
3. `php database\migrate.php`
4. `php database\scripts\hash_passwords.php`
5. `php database\migrate.php --check` → "all checks passed".
6. Log in as each role and open the dashboards.

## Recovery

**Migration failed half-way.** The output names the migration and the error; it is *not* recorded as applied. Fix the cause and re-run — statements use `IF NOT EXISTS`. If you prefer a clean slate, restore the backup:

```bat
C:\xampp\mysql\bin\mysql.exe -u root -e "DROP DATABASE db_pms"
C:\xampp\mysql\bin\mysql.exe -u root < backups\db_pms_<date>.sql
```

**Lost admin password.** `php database\scripts\create_admin.php --name=<existing admin name>` resets that account's password, re-activates it and signs it out everywhere.

**Locked out by throttling.** Wait 15 minutes, or delete the rows for the user in `login_attempts`.

**Evidence file missing on disk.** The download page reports it and logs the path; restore the file from the evidence backup under the same random name (`report_evidence.stored_name`).

**Restoring a deleted station.** Stations with linked records cannot be deleted, only deactivated; reactivate from Police Stations. An empty station that was deleted must be re-created.

## What cannot be undone

- `900_drop_legacy_station_names.sql.manual` (only if you run it yourself).
- Dropping `alerts.alert_message` in migration 006 — the text itself is preserved in `message` / `legacy_alert_message`.
- Password hashing: the original plain-text values are not recoverable (that is the point). Users who forget a password use *Forgot password* or an admin sets a new one.
