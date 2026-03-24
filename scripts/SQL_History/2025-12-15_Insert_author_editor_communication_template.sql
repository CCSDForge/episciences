
-- SQL script to add author-editor communication mail templates
-- Date: 2025-12-15
-- Description: Adds three new mail templates for author-editor communication:
--   1. paper_comment_from_editor_to_author_author_copy: Email template sent to the author when an editor sends them a message
--   2. paper_comment_from_author_to_editor_editor_copy: Email template sent to assigned editors when an author sends them a message
--   3. paper_comment_from_author_to_editor_coauthor_copy: Email template sent to co-authors when a co-author sends a message to assigned editors

-- Note: These templates are global (NULL RVID) and apply to all journals
-- They are used in conjunction with the authorEditorCommunication setting

-- Update existing template key if it was inserted with old name
UPDATE MAIL_TEMPLATE
SET `KEY` = 'paper_comment_from_editor_to_author_author_copy'
WHERE `KEY` = 'paper_editor_response_to_author_author_copy';

-- Template for notifying authors of editor messages
INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'paper_comment_from_editor_to_author_author_copy', 'paper_comment', NULL
WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'paper_comment_from_editor_to_author_author_copy');

-- Template for notifying assigned editors of author messages
INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'paper_comment_from_author_to_editor_editor_copy', 'paper_comment', NULL
WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'paper_comment_from_author_to_editor_editor_copy');

-- Template for notifying co-authors of author messages to editors
INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'paper_comment_from_author_to_editor_coauthor_copy', 'paper_comment', NULL
WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'paper_comment_from_author_to_editor_coauthor_copy');