# Police Management System (PMS)

A web application for Azad Jammu & Kashmir police stations: crime reports with evidence, staff, duties, leave, alerts and audit logging. Plain PHP 8 + MySQL/MariaDB + Bootstrap 5, built to run on Windows/XAMPP.

Final Year Project — secured and completed rewrite of the original code base. See [docs/CHANGES.md](docs/CHANGES.md) for what changed and what is still missing.

## Requirements

- XAMPP 8.2 (PHP 8.2, MariaDB 10.4, Apache) or any PHP 8.1+ with the `mysqli`, `curl`, `fileinfo` and `mbstring` extensions
- MariaDB 10.3+ (migrations use `ADD COLUMN IF NOT EXISTS`)

## Quick start (Windows / XAMPP)

1. Install XAMPP and start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Put this project somewhere and expose the `pms/` folder as `C:\xampp\htdocs\pms`. Either copy the folder there or create a junction so edits stay in one place:

   ```bat
   mklink /J C:\xampp\htdocs\pms "C:\path\to\project\pms"
   ```

   Only `pms/` is served. `database/`, `docs/`, `storage/` and `tests/` stay outside the web root on purpose.
3. Create the local configuration:

   ```bat
   copy pms\config.local.example.php pms\config.local.php
   ```

   Edit `pms/config.local.php`: database user/password, `app_url` (default `http://localhost/pms`). Leave `mail_enabled` off until you have set up email (see [docs/EMAIL_SETUP.md](docs/EMAIL_SETUP.md)).
4. Create the database and schema:

   ```bat
   C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
   ```

5. Create your administrator account (no default credentials are shipped):

   ```bat
   C:\xampp\php\php.exe database\scripts\create_admin.php --name=admin --email=you@example.com
   ```

   You are asked for a password (typing is hidden). For unattended setup set the `PMS_ADMIN_PASSWORD` environment variable instead.
6. Open <http://localhost/pms/> and sign in.

Optional demonstration data (synthetic, local only, random passwords printed once):

```bat
C:\xampp\php\php.exe database\scripts\load_demo_data.php
C:\xampp\php\php.exe database\scripts\load_demo_data.php --remove-only
```

## Upgrading an existing installation

If you already have a `db_pms` database from the original project, **do not** import `schema.sql`. Take a backup, then run the versioned migrations and hash the legacy passwords:

```bat
C:\xampp\mysql\bin\mysqldump.exe -u root --databases db_pms > backups\db_pms_before_upgrade.sql
C:\xampp\php\php.exe database\migrate.php --check
C:\xampp\php\php.exe database\migrate.php
C:\xampp\php\php.exe database\scripts\hash_passwords.php
```

Details, reversibility and recovery steps: [docs/MIGRATIONS.md](docs/MIGRATIONS.md) and [docs/BACKUP_AND_RECOVERY.md](docs/BACKUP_AND_RECOVERY.md).

## Project layout

| Path | Purpose |
| --- | --- |
| `pms/` | Web root: pages, `includes/` (auth, CSRF, DB, permissions, layout), `assets/` |
| `pms/config.php`, `pms/config.local.php` | Configuration (local file is git-ignored) |
| `database/schema.sql` | Fresh-install schema with reference data |
| `database/migrations/` | Versioned migrations run by `database/migrate.php` |
| `database/scripts/` | `create_admin.php`, `hash_passwords.php`, `load_demo_data.php` |
| `storage/` | Evidence uploads and logs (outside the web root, git-ignored) |
| `tests/run_tests.php` | End-to-end verification suite (results in `docs/TEST_RESULTS.md`) |
| `docs/` | Findings, permissions matrix, ER diagram, user guide, change log |

## Roles

| Role | Login page | Scope |
| --- | --- | --- |
| Head office admin (`admin`) | `admin_login.php` | Everything, all stations |
| Station admin (`admin station`) | `login_station_admin.php` | Own station only |
| Staff (`staff`) | `index.php` | Own duties/leave/profile; files and views reports of own station |

Any account can sign in from any of the three pages; each is sent to the dashboard of its own role. Full matrix: [docs/PERMISSIONS.md](docs/PERMISSIONS.md).

## Running the tests

With Apache and MySQL running and `app_url` correct:

```bat
C:\xampp\php\php.exe tests\run_tests.php
```

The suite creates and removes its own synthetic records (prefixed `zzt_` / `ZZTEST`) and also verifies a fresh install and an upgrade in temporary databases. Latest results: [docs/TEST_RESULTS.md](docs/TEST_RESULTS.md).

## Documentation

- [docs/FINDINGS.md](docs/FINDINGS.md) — verified security and bug findings with evidence and fixes
- [docs/PERMISSIONS.md](docs/PERMISSIONS.md) — role / permission matrix
- [docs/ER_DIAGRAM.md](docs/ER_DIAGRAM.md) — database diagram
- [docs/MIGRATIONS.md](docs/MIGRATIONS.md), [docs/BACKUP_AND_RECOVERY.md](docs/BACKUP_AND_RECOVERY.md)
- [docs/EMAIL_SETUP.md](docs/EMAIL_SETUP.md)
- [docs/USER_GUIDE.md](docs/USER_GUIDE.md) — short guide per role
- [docs/CHANGES.md](docs/CHANGES.md) — change summary and remaining limitations
- [docs/TEST_RESULTS.md](docs/TEST_RESULTS.md)
