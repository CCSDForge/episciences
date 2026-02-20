ALTER TABLE `metadata_sources` CHANGE `type` `type` ENUM('repository','metadataRepository','dataverse','dspace','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL;
INSERT INTO `metadata_sources` (`id`, `name`, `type`, `status`, `identifier`, `base_url`, `doi_prefix`, `api_url`, `doc_url`, `paper_url`) VALUES
(19, 'Cryptology ePrint', 'repository', 1, 'oai:eprint.iacr.org:%%ID', 'https://eprint.iacr.org/oai', '', '', 'https://eprint.iacr.org/archive/%%ID', 'https://eprint.iacr.org/archive/%%ID.pdf'),
(20, 'RepositóriUM', 'dspace', 1, 'oai:repositorium.uminho.pt:%%ID', 'https://repositorium.uminho.pt/server/oai/openaire4', '', '', 'https://hdl.handle.net/%%ID', '')
;