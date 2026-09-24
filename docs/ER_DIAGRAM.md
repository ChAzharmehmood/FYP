# Entity–relationship diagram

Generated from the migrated schema (`database/schema.sql`). Legacy text columns that the application still keeps in sync (`police_station_name` on staff/reports/duties, `district`/`tehsil` text, `complainant`, `leave_type`, `alerts.is_active`) are omitted for clarity; see MIGRATIONS.md.

```mermaid
erDiagram
    districts ||--o{ tehsils : has
    districts ||--o{ police_stations : "located in"
    tehsils ||--o{ police_stations : "located in"
    police_stations ||--o{ staff : employs
    police_stations ||--o{ reports : "filed at"
    police_stations ||--o{ duties : "for"
    police_stations o|--o{ alerts : "targets"
    districts o|--o{ alerts : "targets"
    districts ||--o{ reports : "in"
    tehsils ||--o{ reports : "in"
    staff ||--o{ reports : "created_by"
    staff o|--o{ reports : "assigned_to"
    reports ||--o{ report_status_history : has
    reports ||--o{ report_evidence : has
    staff ||--o{ duties : "assigned"
    staff ||--o{ leave_requests : requests
    leave_types ||--o{ leave_requests : "of type"
    leave_requests ||--o{ leave_request_history : has
    staff ||--o{ password_resets : has
    staff o|--o{ audit_logs : "actor"

    districts { int id PK; varchar name UK; tinyint is_active }
    tehsils { int id PK; int district_id FK; varchar name }
    police_stations { int id PK; varchar police_station_name UK; int district_id FK; int tehsil_id FK; tinyint is_active }
    staff { int id PK; varchar name UK; varchar email; varchar password "bcrypt"; varchar designation; enum role "admin|admin station|staff"; int police_station_id FK; varchar id_card_no; tinyint is_active; int session_version; datetime last_login_at }
    reports { int id PK; varchar reference_no UK; int police_station_id FK; varchar under_section; varchar crime_type; varchar accused_name; varchar accused_address; varchar id_card_no; varchar complainant_name; varchar complainant_contact; varchar investigation_officer; text report_description; int district_id FK; int tehsil_id FK; date report_date; varchar status "Open|Under Investigation|Closed|Archived"; int created_by FK; int assigned_to FK; datetime closed_at; datetime archived_at }
    report_status_history { int id PK; int report_id FK; varchar old_status; varchar new_status; int actor_id FK; varchar reason; datetime created_at }
    report_evidence { int id PK; int report_id FK; varchar original_name; varchar stored_name UK; varchar mime_type; int size_bytes; char sha256; int uploaded_by FK; datetime deleted_at }
    duties { int id PK; int staff_id FK; text duty_description; datetime start_time; datetime end_time; int police_station_id FK; varchar shift_type; varchar Duty_location; int checkpoint_id; enum status "scheduled|completed|cancelled"; enum notify_status "not_sent|sent|failed"; tinyint notify_attempts; int created_by }
    leave_types { int id PK; varchar name UK; int annual_allowance "NULL = no limit"; tinyint is_active }
    leave_requests { int id PK; int staff_id FK; int leave_type_id FK; date leave_start_date; date leave_end_date; int requested_days; text reason; varchar status "pending|approved|rejected|withdrawn"; int approved_days; int reviewed_by FK; datetime reviewed_at; varchar review_reason }
    leave_request_history { int id PK; int leave_request_id FK; varchar action; int actor_id FK; int approved_days; varchar reason }
    alerts { int id PK; varchar title; text message; enum target_type "all|district|station"; int target_district_id FK; int target_station_id FK; datetime starts_at; datetime expires_at; enum status "active|inactive|archived"; int created_by }
    audit_logs { bigint id PK; int actor_id; varchar actor_name; varchar actor_role; varchar action; varchar entity_type; int entity_id; text meta "JSON, no secrets"; varchar ip_address; datetime created_at }
    login_attempts { int id PK; varchar username; varchar ip_address; tinyint success; datetime attempted_at }
    password_resets { int id PK; int staff_id FK; char token_hash UK; datetime expires_at; datetime used_at }
    schema_migrations { int id PK; varchar migration UK; datetime applied_at }
```

## Deletion rules

- `staff`, `reports`, `duties`, `leave_requests` and their history are never cascaded away: foreign keys are `ON DELETE RESTRICT`; accounts and stations are deactivated, reports archived.
- `report_evidence` rows are soft-deleted (`deleted_at`); files stay on disk under `storage/evidence/`.
- `created_by` / `assigned_to` / `actor_id` links use `ON DELETE SET NULL` so history survives even if an account were removed directly in the database.

## Time zone

All `DATETIME`/`TIMESTAMP` values are written and read in Pakistan Standard Time (`Asia/Karachi`, UTC+5, no DST): PHP sets `date_default_timezone_set('Asia/Karachi')` and every MySQL session runs `SET time_zone = '+05:00'`.
