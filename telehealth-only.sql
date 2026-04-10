-- Telehealth tables for OpenEMR Telesalud integration
-- This file only creates telehealth tables, it does NOT modify existing tables

-- Table for telehealth configuration
CREATE TABLE IF NOT EXISTS `telehealth_vc_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `smtp_server` varchar(1024) DEFAULT NULL,
  `smtp_user` varchar(1024) DEFAULT NULL,
  `smtp_password` varchar(1024) DEFAULT NULL,
  `smtp_port` varchar(1024) DEFAULT NULL,
  `smtp_ssl_verify_peer` varchar(1024) DEFAULT NULL,
  `smtp_ssl_verify_peer_name` varchar(1024) DEFAULT NULL,
  `vc_api_url` varchar(1024) DEFAULT NULL,
  `vc_api_token` varchar(1024) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for telehealth appointments
CREATE TABLE IF NOT EXISTS `telehealth_vc` (
  `pc_eid` int(10) unsigned NOT NULL,
  `success` tinyint(1) DEFAULT NULL,
  `message` varchar(1024) DEFAULT NULL,
  `data_id` varchar(1024) DEFAULT NULL,
  `valid_from` varchar(1024) DEFAULT NULL,
  `valid_to` varchar(1024) DEFAULT NULL,
  `patient_url` varchar(1024) DEFAULT NULL,
  `medic_url` varchar(1024) DEFAULT NULL,
  `url` varchar(1024) DEFAULT NULL,
  `medic_secret` varchar(1024) DEFAULT NULL,
  `evolution` longtext,
  `encounter` bigint(20) DEFAULT '0',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `active` longtext,
  PRIMARY KEY (`pc_eid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for telehealth topics
CREATE TABLE IF NOT EXISTS `telehealth_vc_topic` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `active` int DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for telehealth logs
CREATE TABLE IF NOT EXISTS `telehealth_vc_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pc_eid` int DEFAULT NULL,
  `user` varchar(50) DEFAULT NULL,
  `datetime` datetime DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `comment` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default telehealth category if not exists
INSERT IGNORE INTO `openemr_postcalendar_categories` 
(`pc_catid`, `pc_constant_id`, `pc_catname`, `pc_catcolor`, `pc_catdesc`, `pc_recurrtype`, `pc_duration`, `pc_end_date_flag`, `pc_end_date_type`, `pc_end_date_freq`, `pc_end_all_day`, `pc_dailylimit`, `pc_cattype`, `pc_active`, `pc_seq`, `aco_spec`)
VALUES 
(16, 'Telehealth', 'Telehealth visit', '#f6ffb3', 'Telehealth appointments', 0, 1200, 0, 0, 0, 0, 0, 0, 1, 16, 'encounters|notes');

-- Insert telehealth configuration
INSERT IGNORE INTO `telehealth_vc_config` 
(`smtp_server`, `smtp_user`, `smtp_password`, `smtp_port`, `smtp_ssl_verify_peer`, `smtp_ssl_verify_peer_name`, `vc_api_url`, `vc_api_token`)
VALUES 
(NULL, NULL, NULL, NULL, NULL, NULL, 'https://localhost:8444', '1|R8QkYdSjjb0yANqS8WMa9J0jNnTVSDmSnFY8qusM');
