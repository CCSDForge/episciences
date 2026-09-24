-- Mail template for contributor change notification.
-- When the contributor of a paper is changed, the new contributor receives a notification.
-- Date: 2026-09-22

INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'paper_contributor_changed', 'paper_submission', 1
WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'paper_contributor_changed');

-- Mail template for former contributor notification.
-- When the contributor of a paper is changed, the former contributor receives a notification.

INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'paper_former_contributor_notification', 'paper_submission', 1
    WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'paper_former_contributor_notification');
