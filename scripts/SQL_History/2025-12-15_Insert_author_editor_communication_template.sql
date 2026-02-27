
-- SQL script to add author-editor communication mail templates
-- Date: 2025-12-15
-- Description: Adds two new mail templates for author-editor communication:
--   1. paper_editor_response_to_author_author_copy: Email template sent to the author when an editor responds to their message
--   2. paper_comment_from_author_to_editor_editor_copy: Email template sent to assigned editors when an author sends them a message

-- Note: These templates are global (NULL RVID) and apply to all journals
-- They are used in conjunction with the authorsCanContactEditors setting

-- Template for notifying authors of editor responses
INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
VALUES (NULL, NULL, NULL, 'paper_editor_response_to_author_author_copy', 'paper_comment', NULL);

-- Template for notifying assigned editors of author messages
INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
VALUES (NULL, NULL, NULL, 'paper_comment_from_author_to_editor_editor_copy', 'paper_comment', NULL);
