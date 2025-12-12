-- SQL script to add author-editor communication settings
-- Date: 2025-12-09
-- Description: Adds two new settings to journal configuration:
--   1. authorsCanContactEditors: Allow authors to contact editors
--   2. discloseEditorNamesToAuthors: Disclose editor names to authors

-- Note: These settings are disabled by default (value 0)
-- They can be enabled individually for each journal via the administration interface

INSERT INTO REVIEW_SETTING (RVID, SETTING, VALUE)
VALUES (3, 'authorsCanContactEditors', 0);


INSERT INTO REVIEW_SETTING (RVID, SETTING, VALUE)
VALUES (3, 'discloseEditorNamesToAuthors', 0);
