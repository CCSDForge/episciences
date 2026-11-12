-- Rating reports by review request type (#319): a reviewer may hold one request of each type on a paper version,
-- each with its own report. The unique key (UID, DOCID) becomes (UID, DOCID, REQUEST_TYPE_ID).
-- Requires 2026-11-12-review-request-type.sql.
-- Created: 2026-11-13

SET NAMES utf8mb4;

ALTER TABLE `REVIEWER_REPORT`
    ADD COLUMN `REQUEST_TYPE_ID` int unsigned DEFAULT NULL COMMENT 'Review request type of the report' AFTER `DOCID`;

-- Existing reports are review reports
UPDATE `REVIEWER_REPORT` r
    INNER JOIN `PAPERS` p ON p.`DOCID` = r.`DOCID`
    INNER JOIN `review_request_type` t ON t.`rvid` = p.`RVID` AND t.`code` = 'full_report'
SET r.`REQUEST_TYPE_ID` = t.`id`
WHERE r.`REQUEST_TYPE_ID` IS NULL;

-- Reports of a paper that no longer exists: no type (0), they are never displayed
UPDATE `REVIEWER_REPORT` SET `REQUEST_TYPE_ID` = 0 WHERE `REQUEST_TYPE_ID` IS NULL;

-- NOT NULL: a unique key accepts duplicates on NULL values
ALTER TABLE `REVIEWER_REPORT`
    MODIFY `REQUEST_TYPE_ID` int unsigned NOT NULL COMMENT 'Review request type of the report (0: paper deleted before the types)',
    DROP INDEX `UID`,
    ADD UNIQUE KEY `uniq_uid_docid_request_type` (`UID`, `DOCID`, `REQUEST_TYPE_ID`),
    ADD KEY `idx_request_type` (`REQUEST_TYPE_ID`);
