-- BAOBAB's OAI-PMH endpoint was fixed by an InvenioRDM upgrade on the WACREN
-- side (verified 2026-09-08): GetRecord/ListIdentifiers/ListRecords now return
-- HTTP 200 instead of 500. Re-enable the OAI-PMH transport for the DataCite
-- body compiled into PAPERS.RECORD.
UPDATE `metadata_sources`
SET `base_url` = 'https://baobab.wacren.net/oai2d'
WHERE `id` = 22;
