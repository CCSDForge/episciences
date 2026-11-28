-- Mail templates of the user import (journal:import-users).
-- user_imported_account: account created (or found not activated yet) by the import, with a link to choose the password and activate it.
-- user_added_to_journal: existing account added to the journal by the import.
-- Date: 2026-11-16

INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'user_imported_account', 'user', 4
WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'user_imported_account');

INSERT INTO MAIL_TEMPLATE (PARENTID, RVID, RVCODE, `KEY`, `TYPE`, POSITION)
SELECT NULL, NULL, NULL, 'user_added_to_journal', 'user', 5
WHERE NOT EXISTS (SELECT 1 FROM MAIL_TEMPLATE WHERE `KEY` = 'user_added_to_journal');
