-- Automatic reminders limited to a review request type (#319): a reminder to the reviewers (unanswered invitation,
-- before or after the reviewing deadline, and their copies to the editorial team) can concern a single request type,
-- e.g. a quick opinion with its own delays and text. NULL: every type, except the types that have a reminder of their
-- own of the same reminder type and recipient.
-- Requires 2026-11-12-review-request-type.sql.
-- Created: 2026-11-21

ALTER TABLE `REMINDERS`
    ADD COLUMN `REQUEST_TYPE_ID` int unsigned DEFAULT NULL COMMENT 'Review request type of the reminder (reviewer reminders only); NULL = every type' AFTER `RECIPIENT`,
    ADD KEY `idx_request_type` (`REQUEST_TYPE_ID`);
