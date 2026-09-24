# Progress record

Last updated: 2026-09-24. Branch: `secure-rewrite` (main is untouched).

## Completed

| Phase | Status | Where to look |
|---|---|---|
| 1 Verify current application | Done — findings table, baseline flows | `docs/FINDINGS.md` |
| 2 Security and access control | Done (A–F) | `pms/includes/*.php`, `docs/PERMISSIONS.md` |
| 3 Repair reported bugs | Done (1–7) | `docs/FINDINGS.md` bugs table |
| 4 Database integrity | Done — 6 migrations applied locally, checks pass; legacy column drop left optional (900) | `docs/MIGRATIONS.md` |
| 5 Features | Done (accounts, stations, staff, reports, duties, leave, alerts, audit, dashboards/search) | `docs/CHANGES.md` |
| 6 UI/UX and maintainability | Done — shared layout, one Bootstrap, shared CSS/JS | `pms/includes/layout_*.php`, `pms/assets/` |
| 7 Verification | Done — 127/127 automated checks pass | `tests/run_tests.php`, `docs/TEST_RESULTS.md` |
| 8 Documentation and handover | Done | `README.md`, `docs/` |

## Migrations applied on the development database

001–006 (see `php database/migrate.php --status`). Backup taken before migrating: `backups/db_pms_backup_2026-09-23_pre-migration.sql` (git-ignored, local only). Legacy passwords hashed. `migrate.php --check`: all checks passed.

## Not done / needs the owner

1. Revoke the two Gmail app passwords that exist in git history (Google Account → Security → App passwords) and create a new one if email is wanted. Then set `mail_enabled` in `config.local.php` and test a duty notification.
2. Decide whether to run `database/migrations/900_drop_legacy_station_names.sql.manual` (irreversible; only after a backup).
3. Add the remaining AJK districts/tehsils if needed (no admin UI yet; insert rows into `districts` / `tehsils`).
4. Merge `secure-rewrite` into `main` after review.

## Exact next steps for a new session

```bat
cd "<project folder>"
git checkout secure-rewrite
C:\xampp\php\php.exe database\migrate.php --status
C:\xampp\php\php.exe tests\run_tests.php
```

If the tests fail because Apache/MySQL are not running, start them from the XAMPP Control Panel (they are not installed as Windows services on this machine). The local site is served from the junction `C:\xampp\htdocs\pms` → the project's `pms/` folder.
