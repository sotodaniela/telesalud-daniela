-- LiveKit Videoconsultations table
CREATE TABLE IF NOT EXISTS `livekit_videoconsultations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `room_name` varchar(100) NOT NULL,
  `patient_name` varchar(255) NOT NULL,
  `patient_identity` varchar(100) NOT NULL,
  `medic_name` varchar(255) NOT NULL,
  `medic_identity` varchar(100) NOT NULL,
  `patient_token` text NOT NULL,
  `medic_token` text NOT NULL,
  `appointment_date` date DEFAULT NULL,
  `appointment_time` time DEFAULT NULL,
  `pc_eid` int DEFAULT 0,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `room_name` (`room_name`),
  KEY `pc_eid` (`pc_eid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
