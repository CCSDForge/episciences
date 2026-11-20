-- Account linked to an answer given without an account (#319): the invited person, signed in, associates the answer
-- with their account, which then lists it and opens it without the invitation link. The answer stays an answer without
-- an account (no rating report, the assignment is unchanged): it is only shown with the account.
-- Requires 2026-11-13-review-light-answer.sql.
-- Created: 2026-11-20

ALTER TABLE `review_light_answer`
    ADD COLUMN `linked_uid` int unsigned DEFAULT NULL COMMENT 'Account the invited person associated the answer with' AFTER `tmp_user`,
    ADD COLUMN `linked_at` datetime DEFAULT NULL AFTER `linked_uid`,
    ADD KEY `idx_linked_uid` (`linked_uid`);
