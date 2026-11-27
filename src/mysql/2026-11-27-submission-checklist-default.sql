-- Default submission checklist of all journals (feature "submission checklist").
-- One row for the portal (RVID = 0) in REVIEW_SETTING, SETTING = 'submissionChecklist'.
-- Journals that have not customised their checklist read this row; nothing is inserted for them.
-- Content: the two checkboxes shown until now (disclaimer1 / disclaimer2), EN + FR (every checkbox is
-- required), keys c_000001 / c_000002. JSON produced by JournalChecklist::toJson() (non-ASCII as \uXXXX).
-- In a MySQL string literal a backslash is an escape character: every backslash of the JSON is
-- doubled below, otherwise \u00ea would be stored as u00ea.
-- Inserted only if the portal (RVID = 0) exists in REVIEW: REVIEW_SETTING has no foreign key and
-- would accept the row anyway. Re-runnable: inserted only if the row does not exist yet (an edited
-- default list is kept). 0 rows affected = portal missing or row already there.
-- Created: 2026-11-27

INSERT INTO REVIEW_SETTING (RVID, SETTING, VALUE)
SELECT 0, 'submissionChecklist', '{"version":1,"items":[{"key":"c_000001","labels":{"en":"I guarantee to be one of this article''s authors, or have the permission to submit in their name","fr":"Je certifie \\u00eatre l''auteur de cet article, ou \\u00eatre mandat\\u00e9 par l''un des auteurs"}},{"key":"c_000002","labels":{"en":"I guarantee not to have published or submitted this article anywhere else","fr":"Je certifie ne pas avoir publi\\u00e9\\/soumis cet article ailleurs"}}]}'
WHERE EXISTS (
    SELECT 1 FROM REVIEW WHERE RVID = 0
)
AND NOT EXISTS (
    SELECT 1 FROM REVIEW_SETTING WHERE RVID = 0 AND SETTING = 'submissionChecklist'
);
