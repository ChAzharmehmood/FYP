-- Database schema for the Police Management System (db_pms).
-- Reconstructed from the queries in the PHP source files.
-- Import in phpMyAdmin or run:  mysql -u root < db_pms.sql

CREATE DATABASE IF NOT EXISTS db_pms CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE db_pms;

CREATE TABLE IF NOT EXISTS police_stations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    police_station_name VARCHAR(150) NOT NULL,
    district VARCHAR(100) NOT NULL,
    tehsil VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    designation VARCHAR(100) DEFAULT NULL,
    role ENUM('admin', 'admin station', 'staff') NOT NULL DEFAULT 'staff',
    police_station_name VARCHAR(150) DEFAULT NULL,
    id_card_no VARCHAR(50) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    police_station_name VARCHAR(150) NOT NULL,
    under_section VARCHAR(100) DEFAULT NULL,
    accused_name VARCHAR(150) DEFAULT NULL,
    accused_address VARCHAR(255) DEFAULT NULL,
    complainant VARCHAR(150) DEFAULT NULL,
    investigation_officer VARCHAR(150) DEFAULT NULL,
    report_description TEXT,
    district VARCHAR(100) DEFAULT NULL,
    tehsil VARCHAR(100) DEFAULT NULL,
    id_card_no VARCHAR(50) DEFAULT NULL,
    report_date DATE DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message TEXT,
    alert_message TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS duties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    duty_description TEXT,
    start_time DATETIME DEFAULT NULL,
    end_time DATETIME DEFAULT NULL,
    police_station_name VARCHAR(150) DEFAULT NULL,
    shift_type VARCHAR(50) DEFAULT NULL,
    Duty_location VARCHAR(255) DEFAULT NULL,
    checkpoint_id INT DEFAULT NULL,
    assigned_date DATE DEFAULT NULL,
    CONSTRAINT fk_duties_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    leave_type VARCHAR(50) NOT NULL,
    leave_start_date DATE NOT NULL,
    leave_end_date DATE NOT NULL,
    reason TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    approved_days INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- Sample data so you can log in right away (passwords are stored in plain text by the app).
INSERT INTO police_stations (police_station_name, district, tehsil) VALUES
    ('City Police Station Muzaffarabad', 'Muzaffarabad', 'Muzaffarabad'),
    ('Police Station Mirpur',            'Mirpur',        'Mirpur');

INSERT INTO staff (name, email, password, designation, role, police_station_name, id_card_no) VALUES
    ('admin',        'admin@example.com',        'admin123',   'IG',        'admin',         'City Police Station Muzaffarabad', '00000-0000000-0'),
    ('stationadmin', 'stationadmin@example.com', 'station123', 'SHO',       'admin station', 'City Police Station Muzaffarabad', '00000-0000000-1'),
    ('staff1',       'staff1@example.com',       'staff123',   'Constable', 'staff',         'City Police Station Muzaffarabad', '00000-0000000-2');
