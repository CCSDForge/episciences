-- Whether the reports of a review request type are passed on to the author (#319): the review report always is,
-- the quick opinion and the types created by a journal are not until the journal says so. A report that is passed on
-- follows the visibility of its criteria, as before (author's page, decision e-mails, attachments, public page of a
-- published paper when the journal shows the reports).
-- Requires 2026-11-12-review-request-type.sql.
-- Created: 2026-11-20

ALTER TABLE `review_request_type`
    ADD COLUMN `visible_to_author` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '1 = its reports are passed on to the author, according to the visibility of their criteria' AFTER `counts_as_report`;

UPDATE `review_request_type`
SET `visible_to_author` = 1
WHERE `default_type` = 1;
