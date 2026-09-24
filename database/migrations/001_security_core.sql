-- 001: account state, session invalidation, login throttling, password reset, audit log.
-- Reversible: yes (drops of the new tables/columns lose only new data).

ALTER TABLE staff
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER id_card_no,
    ADD COLUMN IF NOT EXISTS session_version INT NOT NULL DEFAULT 1 AFTER is_active,
    ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER session_version,
    ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER last_login_at,
    ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER password_changed_at,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    MODIFY password VARCHAR(255) NOT NULL;

-- Login name must be unique (the runner aborts earlier if duplicates exist).
ALTER TABLE staff ADD UNIQUE INDEX IF NOT EXISTS uq_staff_name (name);
ALTER TABLE staff ADD INDEX IF NOT EXISTS idx_staff_email (email);
ALTER TABLE staff ADD INDEX IF NOT EXISTS idx_staff_role (role);

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_user_time (username, attempted_at),
    INDEX idx_login_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    requested_ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reset_token (token_hash),
    INDEX idx_reset_staff (staff_id),
    CONSTRAINT fk_reset_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NULL,
    actor_name VARCHAR(100) NULL,
    actor_role VARCHAR(30) NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT NULL,
    meta TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_time (created_at),
    INDEX idx_audit_actor (actor_id),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
