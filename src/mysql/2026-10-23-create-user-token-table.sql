-- Account link tokens (account activation, password reset, account setup)
-- Replaces the tokens formerly stored in the CAS table T_UTILISATEURS_TOKENS (left untouched: shared table).
-- Only the SHA-256 of a link token is stored. Expired tokens are deleted by: php scripts/console.php user:purge-expired-tokens
-- Tokens sent before this change are imported once with: php scripts/console.php user:import-legacy-tokens
-- Created: 2026-10-23

CREATE TABLE IF NOT EXISTS `user_token` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL COMMENT 'Account UID',
  `email` varchar(320) NOT NULL COMMENT 'E-mail address the link was sent to',
  `purpose` varchar(30) NOT NULL COMMENT 'ACCOUNT_ACTIVATION, PASSWORD_RESET, ACCOUNT_SETUP',
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA-256 of the link token, never the token itself',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token_hash` (`token_hash`),
  KEY `idx_uid_purpose` (`uid`, `purpose`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Single-use account link tokens with an expiry date';
