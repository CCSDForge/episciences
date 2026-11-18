-- Default mail templates of the review request types (#319): one invitation template per type, a base for the
-- types created by a journal, and the texts of the %%INVITATION_ACCOUNT_HINT%% tag (fragments, without subject).
-- Created: 2026-11-12

SET NAMES utf8mb4;

INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'paper_reviewer_invitation_quick_opinion', 'paper_review', NULL
WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'paper_reviewer_invitation_quick_opinion');

INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'paper_reviewer_invitation_generic', 'paper_review', NULL
WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'paper_reviewer_invitation_generic');

INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'reviewer_invitation_account_hint_known_reviewer', 'paper_review', NULL
WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'reviewer_invitation_account_hint_known_reviewer');

INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'reviewer_invitation_account_hint_known_user', 'paper_review', NULL
WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'reviewer_invitation_account_hint_known_user');

INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'reviewer_invitation_account_hint_new_user', 'paper_review', NULL
WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'reviewer_invitation_account_hint_new_user');

INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'reviewer_invitation_account_hint_light_answer', 'paper_review', NULL
WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'reviewer_invitation_account_hint_light_answer');
