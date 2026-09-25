-- Mail templates for contributor change notifications.
-- paper_new_contributor_notification: When the contributor of a paper is changed, the new contributor receives a notification.
-- paper_former_contributor_notification: When the contributor of a paper is changed, the former contributor receives a notification.
-- Date: 2026-09-22

INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'paper_new_contributor_notification', 'paper_submission', 1
WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'paper_new_contributor_notification');


INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'paper_former_contributor_notification', 'paper_submission', 1
    WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'paper_former_contributor_notification');
