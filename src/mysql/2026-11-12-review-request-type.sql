-- Review request types (quick opinion, review report, revised version check...), defined per journal.
-- Each reviewer invitation is made for one type; a reviewer may hold several requests of different types
-- on the same paper version.
-- Created: 2026-11-12

-- The labels hold non-ASCII characters: whatever the default character set of the client
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `review_request_type` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `rvid` int unsigned NOT NULL COMMENT 'Journal',
  `code` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'Stable identifier, unique per journal (mail template key suffix)',
  `labels` json NOT NULL COMMENT 'Label per language, e.g. {"fr": "...", "en": "..."}',
  `light_answer` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '1 = answered from the invitation link, without an account',
  `default_deadline` varchar(20) DEFAULT NULL COMMENT 'Default rating deadline, e.g. "2 week"; NULL = journal setting',
  `position` smallint unsigned NOT NULL DEFAULT '0',
  `enabled` tinyint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rvid_code` (`rvid`, `code`),
  KEY `idx_rvid_position` (`rvid`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Review request types, per journal';

-- Default types for every existing journal (new journals get them when they are created)
INSERT IGNORE INTO `review_request_type` (`rvid`, `code`, `labels`, `default_deadline`, `position`)
SELECT `RVID`, 'full_report', JSON_OBJECT('fr', 'Rapport de relecture', 'en', 'Review report'), NULL, 1 FROM `REVIEW`
UNION ALL
SELECT `RVID`, 'quick_opinion', JSON_OBJECT('fr', 'Avis rapide', 'en', 'Quick opinion'), '2 week', 2 FROM `REVIEW`
UNION ALL
SELECT `RVID`, 'revision_check', JSON_OBJECT('fr', 'Relecture d''une version révisée', 'en', 'Revised version check'), '3 week', 3 FROM `REVIEW`;

-- Type of each reviewer request. The state of a reviewer on a paper is read on their latest assignment row
-- for each type, so every reviewer row carries a type; other roles leave it NULL.
ALTER TABLE `USER_ASSIGNMENT`
    ADD COLUMN `REQUEST_TYPE_ID` int unsigned DEFAULT NULL COMMENT 'Review request type (reviewer rows only)' AFTER `ROLEID`,
    ADD KEY `idx_request_type` (`REQUEST_TYPE_ID`);

-- Existing reviewer requests are review reports
UPDATE `USER_ASSIGNMENT` a
    INNER JOIN `review_request_type` t ON t.`rvid` = a.`RVID` AND t.`code` = 'full_report'
SET a.`REQUEST_TYPE_ID` = t.`id`
WHERE a.`ROLEID` = 'reviewer'
  AND a.`REQUEST_TYPE_ID` IS NULL;
