-- SQL script to add author-editor communication settings
-- Date: 2025-12-09
-- Description: Adds two new settings to journal configuration:
--   1. authorsCanContactEditors: Allow authors to contact editors
--   2. discloseEditorNamesToAuthors: Disclose editor names to authors

-- Note: These settings are disabled by default (value 0)
-- They can be enabled individually for each journal via the administration interface

INSERT INTO REVIEW_SETTING (RVID, SETTING, VALUE)
   SELECT RVID, 'authorsCanContactEditors', '0'
   FROM REVIEW
   WHERE RVID NOT IN (
       SELECT RVID FROM REVIEW_SETTING WHERE SETTING = 'authorsCanContactEditors'
   );

INSERT INTO REVIEW_SETTING (RVID, SETTING, VALUE)
   SELECT RVID, 'discloseEditorNamesToAuthors', '0'
   FROM REVIEW
   WHERE RVID NOT IN (
       SELECT RVID FROM REVIEW_SETTING WHERE SETTING = 'discloseEditorNamesToAuthors'
   );
