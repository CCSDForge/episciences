-- How a review request is answered (#319): with an account, or without one from the invitation link. The mode is
-- the option of the request type when the request is sent, kept on the original assignment of the invitation
-- (USER_INVITATION.AID): changing the option of the type no longer changes the requests already sent.
-- Requires 2026-11-12-review-request-type.sql.
-- Created: 2026-11-20

ALTER TABLE `USER_ASSIGNMENT`
    ADD COLUMN `LIGHT_ANSWER` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '1 = answered without an account, from the invitation link (reviewer rows only, set when the row is written)' AFTER `REQUEST_TYPE_ID`;

-- Requests sent before: the current option of their type, which is the one the answer page reads until now
UPDATE `USER_ASSIGNMENT` a
    INNER JOIN `review_request_type` t ON t.`id` = a.`REQUEST_TYPE_ID`
SET a.`LIGHT_ANSWER` = t.`light_answer`
WHERE a.`ROLEID` = 'reviewer';
