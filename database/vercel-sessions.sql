CREATE TABLE IF NOT EXISTS app_sessions (
    id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    data MEDIUMBLOB NOT NULL,
    expires_at BIGINT NOT NULL,
    INDEX sessions_expiry (expires_at)
) ENGINE=InnoDB;
