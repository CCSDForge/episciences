-- Switch User (su) audit log table
-- Tracks impersonation sessions in the local Episciences database
-- Created: 2026-10-09

CREATE TABLE IF NOT EXISTS `user_su_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `rvid` int unsigned NOT NULL DEFAULT 0 COMMENT 'Review ID, 0 for portal',
  `from_uid` int unsigned NOT NULL COMMENT 'Initiating user UID',
  `to_uid` int unsigned NOT NULL COMMENT 'Target user UID',
  `action` varchar(30) NOT NULL COMMENT 'GRANTED, DENIED',
  `reason` varchar(50) DEFAULT NULL COMMENT 'Reason code when DENIED',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Client IP (IPv4 up to 15, IPv6 up to 45 chars)',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'Client HTTP User-Agent',
  `session_id` varchar(128) DEFAULT NULL COMMENT 'Session identifier',
  `details` json DEFAULT NULL COMMENT 'Snapshot of roles and context',
  `is_anonymized` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 once IP has been anonymized after retention period',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_from_uid` (`from_uid`),
  KEY `idx_to_uid` (`to_uid`),
  KEY `idx_rvid` (`rvid`),
  KEY `idx_action` (`action`),
  KEY `idx_anonymization` (`is_anonymized`, `created_at`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail of Switch User (impersonation) sessions';
