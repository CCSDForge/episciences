-- Journal configuration the Playwright E2E suite needs, loaded by .github/workflows/e2e.yml on top of
-- seed.sql. seed.sql stays minimal for the PHPUnit suite; locally, the dev journal of the Docker
-- database already holds these rows.

-- Repositories, as in src/mysql/docker/episciences/dev-episciences.sql (submissions are fetched from arXiv)
-- Row 0 is the temporary version source: keep its id instead of taking the next auto-increment value
SET SESSION sql_mode = CONCAT(@@SESSION.sql_mode, ',NO_AUTO_VALUE_ON_ZERO');

INSERT INTO `metadata_sources` (`id`, `name`, `type`, `status`, `identifier`, `base_url`, `doi_prefix`, `api_url`, `doc_url`, `paper_url`) VALUES
(0, 'Episciences(Temporary Version)', 'repository', 1, '', '', '', '', '/tmp_files/%%ID', '/tmp_files/%%ID'),
(1, 'HAL', 'repository', 1, 'oai:HAL:%%IDv%%VERSION', 'https://api.archives-ouvertes.fr/oai/hal/', '', 'https://api.archives-ouvertes.fr', 'https://hal.science/%%IDv%%VERSION', 'https://hal.science/%%IDv%%VERSION/document'),
(2, 'arXiv', 'repository', 1, 'oai:arXiv.org:%%ID', 'https://oaipmh.arxiv.org/oai', '10.48550', '', 'https://arxiv.org/abs/%%IDv%%VERSION', 'https://arxiv.org/pdf/%%IDv%%VERSION'),
(3, 'CWI', 'repository', 0, 'oai:cwi.nl:%%ID', 'https://ir.cwi.nl/oai/', '', '', 'https://persistent-identifier.org/?identifier=urn:nbn:nl:ui:18-%%ID', 'https://ir.cwi.nl/pub/%%ID/%%ID.pdf'),
(4, 'Zenodo', 'repository', 1, 'oai:zenodo.org:%%ID', NULL, '10.5281', 'https://zenodo.org/api/', 'https://zenodo.org/record/%%ID', ''),
(5, 'ScholeXplorer', 'metadataRepository', 1, NULL, NULL, '', '', '', ''),
(6, 'Crossref', 'metadataRepository', 1, NULL, NULL, '', '', '', ''),
(7, 'Datacite', 'metadataRepository', 1, NULL, NULL, '', '', '', ''),
(8, 'OpenAIRE Graph', 'metadataRepository', 1, NULL, NULL, '', '', '', ''),
(9, 'Software Heritage', 'repository', 1, NULL, NULL, '', '', '', ''),
(10, 'bioRxiv', 'repository', 1, 'oai:bioRxiv.org:%%ID', NULL, '10.1101', 'https://api.biorxiv.org/details/biorxiv/', 'https://www.biorxiv.org/content/%%IDv%%VERSION', 'https://www.biorxiv.org/content/%%IDv%%VERSION.full.pdf'),
(11, 'medRxiv', 'repository', 1, 'oai:bioRxiv.org:%%ID', NULL, '10.1101', 'https://api.biorxiv.org/details/medrxiv/', 'https://www.medrxiv.org/content/%%IDv%%VERSION', 'https://www.medrxiv.org/content/%%IDv%%VERSION.full.pdf'),
(12, 'Episciences User', 'user', 1, NULL, NULL, '', '', '', ''),
(13, 'OpenCitations', 'metadataRepository', 1, NULL, NULL, '', '', '', ''),
(14, 'DaRUS (University of Stuttgart)', 'dataverse', 1, NULL, NULL, '10.18419', 'https://darus.uni-stuttgart.de/api/v1/', 'https://darus.uni-stuttgart.de/dataset.xhtml?persistentId=%%ID&version=%%VERSION.%%V_MINOR_NUMBER', ''),
(15, 'Recherche Data Gouv', 'dataverse', 1, NULL, NULL, '10.15454', 'https://entrepot.recherche.data.gouv.fr/api/v1/', 'https://entrepot.recherche.data.gouv.fr/dataset.xhtml?persistentId=%%ID&version=%%VERSION.%%V_MINOR_NUMBER', ''),
(16, 'HAL-PREPROD', 'repository', 1, 'oai-preprod:HAL:%%IDv%%VERSION', 'https://api-preprod.archives-ouvertes.fr/oai/hal/', '', 'https://api-preprod.archives-ouvertes.fr', 'https://hal.halpreprod.archives-ouvertes.fr/%%IDv%%VERSION', 'https://hal.halpreprod.archives-ouvertes.fr/%%IDv%%VERSION/document'),
(17, 'zbMATH Open', 'metadataRepository', 1, NULL, NULL, '', '', '', ''),
(18, 'ARCHE', 'repository', 1, 'https://hdl.handle.net/%%ID', 'https://arche.acdh.oeaw.ac.at/oaipmh/', '', 'https://arche.acdh.oeaw.ac.at/api/', 'https://hdl.handle.net/%%ID', ''),
(19, 'Cryptology ePrint', 'repository', 1, 'oai:eprint.iacr.org:%%ID', 'https://eprint.iacr.org/oai', '', '', 'https://eprint.iacr.org/archive/%%ID', 'https://eprint.iacr.org/archive/%%ID.pdf'),
(20, 'RepositóriUM', 'dspace', 1, 'oai:repositorium.uminho.pt:%%ID', 'https://repositorium.uminho.pt/server/oai/openaire4', '', '', 'https://hdl.handle.net/%%ID', ''),
(22, 'BAOBAB', 'repository', 1, 'oai:baobab.wacren.net:%%ID', 'https://baobab.wacren.net/oai2d', '', 'https://baobab.wacren.net/api', 'https://baobab.wacren.net/records/%%ID', '');

-- Journal settings the editorial workflow specs rely on (tests/fixtures/seed.sql creates the journal, RVID 1)
INSERT INTO `REVIEW_SETTING` (`RVID`, `SETTING`, `VALUE`) VALUES
(1, 'repositories', '["1","2"]'),
(1, 'canPickSEction', '1'),
(1, 'coverLetterRequirement', '1'),
(1, 'disableAutomaticTransfer', '1'),
(1, 'invitation_deadline', '1 month'),
(1, 'rating_deadline', '2 month'),
(1, 'rating_deadline_min', '2 month'),
(1, 'rating_deadline_max', '6 month');
