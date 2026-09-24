-- Police Management System: fresh-install schema (generated 2026-09-24 from the migrated database).
-- Creates all tables plus reference rows (districts, tehsils, leave types) and marks every migration as applied.
-- Usage: mysql -u root < database/schema.sql   then   php database/scripts/create_admin.php --name=admin
CREATE DATABASE IF NOT EXISTS db_pms CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE db_pms;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `target_type` enum('all','district','station') NOT NULL DEFAULT 'all',
  `target_district_id` int(11) DEFAULT NULL,
  `target_station_id` int(11) DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `legacy_alert_message` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_alerts_window` (`status`,`starts_at`,`expires_at`),
  KEY `fk_alert_district` (`target_district_id`),
  KEY `fk_alert_station` (`target_station_id`),
  CONSTRAINT `fk_alert_district` FOREIGN KEY (`target_district_id`) REFERENCES `districts` (`id`),
  CONSTRAINT `fk_alert_station` FOREIGN KEY (`target_station_id`) REFERENCES `police_stations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `actor_id` int(11) DEFAULT NULL,
  `actor_name` varchar(100) DEFAULT NULL,
  `actor_role` varchar(30) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `meta` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_time` (`created_at`),
  KEY `idx_audit_actor` (`actor_id`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `districts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_district_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `duties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `duty_description` text DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `police_station_name` varchar(150) DEFAULT NULL,
  `police_station_id` int(11) DEFAULT NULL,
  `shift_type` varchar(50) DEFAULT NULL,
  `Duty_location` varchar(255) DEFAULT NULL,
  `checkpoint_id` int(11) DEFAULT NULL,
  `assigned_date` date DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `notify_status` enum('not_sent','sent','failed') NOT NULL DEFAULT 'not_sent',
  `notify_error` varchar(255) DEFAULT NULL,
  `notify_attempts` tinyint(4) NOT NULL DEFAULT 0,
  `notified_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_duties_station` (`police_station_id`),
  KEY `idx_duties_staff_time` (`staff_id`,`start_time`,`end_time`),
  KEY `idx_duties_status` (`status`),
  CONSTRAINT `fk_duties_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`),
  CONSTRAINT `fk_duties_station` FOREIGN KEY (`police_station_id`) REFERENCES `police_stations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_request_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `leave_request_id` int(11) NOT NULL,
  `action` varchar(30) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `approved_days` int(11) DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lrh_request` (`leave_request_id`),
  KEY `fk_lrh_actor` (`actor_id`),
  CONSTRAINT `fk_lrh_actor` FOREIGN KEY (`actor_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lrh_request` FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `leave_type` varchar(50) NOT NULL,
  `leave_type_id` int(11) DEFAULT NULL,
  `leave_start_date` date NOT NULL,
  `leave_end_date` date NOT NULL,
  `requested_days` int(11) NOT NULL DEFAULT 0,
  `reason` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `approved_days` int(11) NOT NULL DEFAULT 0,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_reason` varchar(500) DEFAULT NULL,
  `withdrawn_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leave_staff_dates` (`staff_id`,`leave_start_date`,`leave_end_date`),
  KEY `idx_leave_status` (`status`),
  KEY `fk_leave_type` (`leave_type_id`),
  KEY `fk_leave_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_leave_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_leave_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`),
  CONSTRAINT `fk_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `annual_allowance` int(11) DEFAULT NULL COMMENT 'days per calendar year; NULL = no limit enforced',
  `requires_reason` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leave_type` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_user_time` (`username`,`attempted_at`),
  KEY `idx_login_ip_time` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `requested_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reset_token` (`token_hash`),
  KEY `idx_reset_staff` (`staff_id`),
  CONSTRAINT `fk_reset_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `police_stations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `police_station_name` varchar(150) NOT NULL,
  `district` varchar(100) NOT NULL,
  `tehsil` varchar(100) NOT NULL,
  `district_id` int(11) DEFAULT NULL,
  `tehsil_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_station_name` (`police_station_name`),
  KEY `idx_station_district` (`district_id`),
  KEY `idx_station_tehsil` (`tehsil_id`),
  CONSTRAINT `fk_station_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`),
  CONSTRAINT `fk_station_tehsil` FOREIGN KEY (`tehsil_id`) REFERENCES `tehsils` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_evidence` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(80) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `size_bytes` int(11) NOT NULL,
  `sha256` char(64) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evidence_stored` (`stored_name`),
  KEY `idx_evidence_report` (`report_id`),
  KEY `fk_evidence_user` (`uploaded_by`),
  CONSTRAINT `fk_evidence_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`),
  CONSTRAINT `fk_evidence_user` FOREIGN KEY (`uploaded_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rsh_report` (`report_id`),
  KEY `fk_rsh_actor` (`actor_id`),
  CONSTRAINT `fk_rsh_actor` FOREIGN KEY (`actor_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rsh_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(30) DEFAULT NULL,
  `police_station_name` varchar(150) NOT NULL,
  `police_station_id` int(11) DEFAULT NULL,
  `under_section` varchar(100) DEFAULT NULL,
  `accused_name` varchar(150) DEFAULT NULL,
  `accused_address` varchar(255) DEFAULT NULL,
  `complainant` varchar(150) DEFAULT NULL,
  `crime_type` varchar(100) DEFAULT NULL,
  `complainant_name` varchar(150) DEFAULT NULL,
  `complainant_contact` varchar(50) DEFAULT NULL,
  `investigation_officer` varchar(150) DEFAULT NULL,
  `report_description` text DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `district_id` int(11) DEFAULT NULL,
  `tehsil` varchar(100) DEFAULT NULL,
  `tehsil_id` int(11) DEFAULT NULL,
  `id_card_no` varchar(50) DEFAULT NULL,
  `report_date` date DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_report_ref` (`reference_no`),
  KEY `idx_reports_station` (`police_station_id`),
  KEY `idx_reports_status` (`status`),
  KEY `idx_reports_cnic` (`id_card_no`),
  KEY `idx_reports_date` (`report_date`),
  KEY `idx_reports_crime` (`crime_type`),
  KEY `idx_reports_district` (`district_id`),
  KEY `fk_reports_tehsil` (`tehsil_id`),
  KEY `fk_reports_created_by` (`created_by`),
  KEY `fk_reports_assigned_to` (`assigned_to`),
  CONSTRAINT `fk_reports_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reports_created_by` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reports_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`),
  CONSTRAINT `fk_reports_station` FOREIGN KEY (`police_station_id`) REFERENCES `police_stations` (`id`),
  CONSTRAINT `fk_reports_tehsil` FOREIGN KEY (`tehsil_id`) REFERENCES `tehsils` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schema_migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `migration` varchar(190) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `role` enum('admin','admin station','staff') NOT NULL DEFAULT 'staff',
  `police_station_name` varchar(150) DEFAULT NULL,
  `police_station_id` int(11) DEFAULT NULL,
  `id_card_no` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `session_version` int(11) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_name` (`name`),
  KEY `idx_staff_email` (`email`),
  KEY `idx_staff_role` (`role`),
  KEY `idx_staff_station` (`police_station_id`),
  CONSTRAINT `fk_staff_station` FOREIGN KEY (`police_station_id`) REFERENCES `police_stations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tehsils` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `district_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tehsil` (`district_id`,`name`),
  CONSTRAINT `fk_tehsil_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- Reference data

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `districts` WRITE;
/*!40000 ALTER TABLE `districts` DISABLE KEYS */;
INSERT INTO `districts` VALUES (1,'Kotli',1,'2026-09-23 23:57:02'),(2,'Bhimber',1,'2026-09-23 23:57:02'),(3,'Bagh',1,'2026-09-23 23:57:02'),(4,'Muzaffarabad',1,'2026-09-23 23:57:02'),(5,'Haveli',1,'2026-09-23 23:57:02'),(6,'Mirpur',1,'2026-09-23 23:57:02');
/*!40000 ALTER TABLE `districts` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `tehsils` WRITE;
/*!40000 ALTER TABLE `tehsils` DISABLE KEYS */;
INSERT INTO `tehsils` VALUES (1,3,'Birpani',1,'2026-09-23 23:57:02'),(2,3,'Rera',1,'2026-09-23 23:57:02'),(3,3,'Hari Ghel',1,'2026-09-23 23:57:02'),(4,3,'Dhirkot',1,'2026-09-23 23:57:02'),(5,3,'Bagh',1,'2026-09-23 23:57:02'),(6,2,'Samahni',1,'2026-09-23 23:57:02'),(7,2,'Barnala',1,'2026-09-23 23:57:02'),(8,2,'Bhimber',1,'2026-09-23 23:57:02'),(9,5,'Khursidabad',1,'2026-09-23 23:57:02'),(10,5,'Mumtazabad',1,'2026-09-23 23:57:02'),(11,5,'Haveli Kahuta',1,'2026-09-23 23:57:02'),(12,1,'Duliah Jattan',1,'2026-09-23 23:57:02'),(13,1,'Charhoi',1,'2026-09-23 23:57:02'),(14,1,'Sehnsa',1,'2026-09-23 23:57:02'),(15,1,'Fatehpur Thakiala',1,'2026-09-23 23:57:02'),(16,1,'Khuiratta',1,'2026-09-23 23:57:02'),(17,1,'Kotli',1,'2026-09-23 23:57:02'),(18,4,'Nasirabad',1,'2026-09-23 23:57:02'),(19,4,'Muzaffarabad',1,'2026-09-23 23:57:02'),(20,4,'Okra',1,'2026-09-23 23:57:02'),(32,6,'Mirpur',1,'2026-09-23 23:57:02');
/*!40000 ALTER TABLE `tehsils` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `leave_types` WRITE;
/*!40000 ALTER TABLE `leave_types` DISABLE KEYS */;
INSERT INTO `leave_types` VALUES (1,'Casual',10,1,1),(2,'Sick',8,1,1),(3,'Annual',15,1,1);
/*!40000 ALTER TABLE `leave_types` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `schema_migrations` WRITE;
/*!40000 ALTER TABLE `schema_migrations` DISABLE KEYS */;
INSERT INTO `schema_migrations` VALUES (1,'001_security_core.sql','2026-09-23 23:57:02'),(2,'002_geography_tables.sql','2026-09-23 23:57:02'),(3,'003_geography_backfill.php','2026-09-23 23:57:02'),(4,'004_station_ids.php','2026-09-23 23:57:03'),(5,'005_reports.php','2026-09-23 23:57:03'),(6,'006_duties_leave_alerts.php','2026-09-23 23:57:03');
/*!40000 ALTER TABLE `schema_migrations` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

