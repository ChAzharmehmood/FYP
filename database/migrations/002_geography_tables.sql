-- 002: districts and tehsils tables.
-- The seed list is the one that was hard-coded in generate_report.php; it is
-- preserved as the application's working data, not presented as an official dataset.
-- Reversible: yes (drop the two tables) as long as 004 has not been applied.

CREATE TABLE IF NOT EXISTS districts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_district_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tehsils (
    id INT AUTO_INCREMENT PRIMARY KEY,
    district_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tehsil (district_id, name),
    CONSTRAINT fk_tehsil_district FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO districts (name) VALUES
    ('Kotli'), ('Bhimber'), ('Bagh'), ('Muzaffarabad'), ('Haveli');

INSERT IGNORE INTO tehsils (district_id, name)
SELECT d.id, t.name FROM (
    SELECT 'Kotli' AS district, 'Kotli' AS name UNION ALL
    SELECT 'Kotli', 'Khuiratta' UNION ALL
    SELECT 'Kotli', 'Fatehpur Thakiala' UNION ALL
    SELECT 'Kotli', 'Sehnsa' UNION ALL
    SELECT 'Kotli', 'Charhoi' UNION ALL
    SELECT 'Kotli', 'Duliah Jattan' UNION ALL
    SELECT 'Bhimber', 'Bhimber' UNION ALL
    SELECT 'Bhimber', 'Barnala' UNION ALL
    SELECT 'Bhimber', 'Samahni' UNION ALL
    SELECT 'Bagh', 'Bagh' UNION ALL
    SELECT 'Bagh', 'Dhirkot' UNION ALL
    SELECT 'Bagh', 'Hari Ghel' UNION ALL
    SELECT 'Bagh', 'Rera' UNION ALL
    SELECT 'Bagh', 'Birpani' UNION ALL
    SELECT 'Muzaffarabad', 'Okra' UNION ALL
    SELECT 'Muzaffarabad', 'Muzaffarabad' UNION ALL
    SELECT 'Muzaffarabad', 'Nasirabad' UNION ALL
    SELECT 'Haveli', 'Haveli Kahuta' UNION ALL
    SELECT 'Haveli', 'Mumtazabad' UNION ALL
    SELECT 'Haveli', 'Khursidabad'
) t JOIN districts d ON d.name = t.district;
