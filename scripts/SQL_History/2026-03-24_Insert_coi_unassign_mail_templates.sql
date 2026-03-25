-- Mail templates for a positive conflict-of-interest (COI) answer on a paper.
-- When an assigned editor declares a COI, the application unassigns them and may send:
--   - paper_coi_unassign_chief_editor_copy: one message per editor-in-chief;
--   - paper_coi_unassign_other_editors_copy: one message per other editor still assigned (if any).
-- Date: 2026-03-24

INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'paper_coi_unassign_chief_editor_copy', 'paper_editor_assign', 4
WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'paper_coi_unassign_chief_editor_copy');

INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'paper_coi_unassign_other_editors_copy', 'paper_editor_assign', 5
WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'paper_coi_unassign_other_editors_copy');
