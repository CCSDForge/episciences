-- Review request types (review report, quick opinion, and the types created by a journal), defined per journal.
-- Each reviewer invitation is made for one type; a reviewer may hold several requests of different types
-- on the same paper version.
-- Created: 2026-11-12

-- The labels hold non-ASCII characters: whatever the default character set of the client
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `review_request_type` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `rvid` int unsigned NOT NULL COMMENT 'Journal',
  `default_type` tinyint unsigned DEFAULT NULL COMMENT 'Default type (Episciences\\Reviewer\\DefaultRequestType): 1 = review report, 2 = quick opinion; NULL = created by the journal',
  `labels` json NOT NULL COMMENT 'Label per language, e.g. {"fr": "...", "en": "..."}',
  `light_answer` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '1 = answered from the invitation link, without an account',
  `default_deadline` varchar(20) DEFAULT NULL COMMENT 'Default rating deadline, e.g. "2 week"; NULL = journal setting',
  `position` smallint unsigned NOT NULL DEFAULT '0',
  `enabled` tinyint unsigned NOT NULL DEFAULT '1',
  `counts_as_report` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '1 = its reports count like review reports (reviewed status, required number, average rating)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rvid_default_type` (`rvid`, `default_type`),
  KEY `idx_rvid_position` (`rvid`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Review request types, per journal';

-- Default types for every existing journal (new journals get them when they are created)
INSERT IGNORE INTO `review_request_type` (`rvid`, `default_type`, `labels`, `default_deadline`, `position`, `counts_as_report`)
SELECT `RVID`, 1, JSON_OBJECT('fr', 'Rapport de relecture', 'en', 'Review report'), NULL, 1, 1 FROM `REVIEW`
UNION ALL
SELECT `RVID`, 2, JSON_OBJECT('fr', 'Avis rapide', 'en', 'Quick opinion'), '2 week', 2, 0 FROM `REVIEW`;

-- Type of each reviewer request. The state of a reviewer on a paper is read on their latest assignment row
-- for each type, so every reviewer row carries a type; other roles leave it NULL.
ALTER TABLE `USER_ASSIGNMENT`
    ADD COLUMN `REQUEST_TYPE_ID` int unsigned DEFAULT NULL COMMENT 'Review request type (reviewer rows only)' AFTER `ROLEID`,
    ADD KEY `idx_request_type` (`REQUEST_TYPE_ID`);

-- Existing reviewer requests are review reports
UPDATE `USER_ASSIGNMENT` a
    INNER JOIN `review_request_type` t ON t.`rvid` = a.`RVID` AND t.`default_type` = 1
SET a.`REQUEST_TYPE_ID` = t.`id`
WHERE a.`ROLEID` = 'reviewer'
  AND a.`REQUEST_TYPE_ID` IS NULL;
