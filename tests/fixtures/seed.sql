-- Minimal data the application bootstrap needs to come up.
--
-- Bootstrap::_initModule() looks the journal up by its code and exits when it finds nothing,
-- so a REVIEW row matching RVCODE has to exist before the very first test can run.
-- Everything else is left empty on purpose: the test suite must not depend on fixture content.

INSERT INTO `REVIEW` (`RVID`, `CODE`, `NAME`, `STATUS`, `CREATION`, `PIWIKID`)
VALUES (1, 'dev', 'Continuous integration journal', 1, NOW(), 0);
