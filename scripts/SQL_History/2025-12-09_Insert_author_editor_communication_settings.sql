-- SQL script to add author-editor communication settings
-- Date: 2025-12-09
-- Description: Adds two new settings to journal configuration:
--   1. authorEditorCommunication: Allow authors to contact editors
--   2. discloseEditorNamesToAuthors: Disclose editor names to authors

-- Note: These settings are disabled by default (value 0)
-- They can be enabled individually for each journal via the administration interface

-- before update
INSERT INTO REVIEW_SETTING (RVID, SETTING, VALUE)
   SELECT RVID, 'authorsCanContactEditors', '0'
   FROM REVIEW
   WHERE RVID NOT IN (
       SELECT RVID FROM REVIEW_SETTING WHERE SETTING = 'authorsCanContactEditors'
   );

-- update
-- Rename setting 'authorsCanContactEditors' to 'authorEditorCommunication'
-- This reflects the bidirectional nature of the feature (authors <-> editors)

UPDATE REVIEW_SETTING
SET SETTING = 'authorEditorCommunication'
WHERE SETTING = 'authorsCanContactEditors';

--after update
INSERT INTO REVIEW_SETTING (RVID, SETTING, VALUE)
SELECT RVID, 'authorEditorCommunication', '0'
FROM REVIEW
WHERE RVID NOT IN (
    SELECT RVID FROM REVIEW_SETTING WHERE SETTING = 'authorEditorCommunication'
);

INSERT INTO REVIEW_SETTING (RVID, SETTING, VALUE)
   SELECT RVID, 'discloseEditorNamesToAuthors', '0'
   FROM REVIEW
   WHERE RVID NOT IN (
       SELECT RVID FROM REVIEW_SETTING WHERE SETTING = 'discloseEditorNamesToAuthors'
   );
