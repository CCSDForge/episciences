ALTER TABLE
    `metadata_sources` CHANGE `type` `type` ENUM(
        'repository',
        'metadataRepository',
        'data',
        'user'
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL;


ALTER TABLE
    `metadata_sources` ADD `status` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'enabled by default' AFTER `type`,
    ADD `identifier` VARCHAR(50) NULL COMMENT 'OAI identifier' AFTER `status`,
    ADD `base_url` VARCHAR(100) NULL COMMENT 'OAI base url' AFTER `identifier`,
    ADD `doi_prefix` VARCHAR(10) NOT NULL AFTER `base_url`,
    ADD `api_url` VARCHAR(100) NOT NULL AFTER `doi_prefix`,
    ADD `doc_url` VARCHAR(100) NOT NULL AFTER `api_url`,
    ADD `paper_url` VARCHAR(100) NOT NULL AFTER `doc_url`;


UPDATE
    `metadata_sources`
SET
    `status` = '0'
WHERE
    `metadata_sources`.`id` = 3;


INSERT INTO `metadata_sources`(
    `id`,
    `name`,
    `type`,
    `identifier`,
    `base_url`,
    `doi_prefix`,
    `api_url`,
    `doc_url`,
    `paper_url`
)
VALUES(
    '0',
    'Episciences',
    'repository',
    '',
    '',
    '',
    '',
    '/tmp_files/%%ID',
    '/tmp_files/%%ID'
);


UPDATE
    `metadata_sources`
SET
    `identifier` = 'oai:HAL:%%IDv%%VERSION',
    `base_url` = 'https://api.archives-ouvertes.fr/oai/hal/',
    `api_url` = 'https://api.archives-ouvertes.fr',
    `doc_url` = 'https://hal.science/%%IDv%%VERSION',
    `paper_url` = 'https://hal.science/%%IDv%%VERSION/document'
WHERE
    `metadata_sources`.`id` = 1;


UPDATE
    `metadata_sources`
SET
    `identifier` = 'oai:arXiv.org:%%ID',
    `base_url` = 'http://export.arXiv.org/oai2',
    `doi_prefix` = '10.48550',
    `doc_url` = 'https://arxiv.org/abs/%%IDv%%VERSION',
    `paper_url` = 'https://arxiv.org/pdf/%%IDv%%VERSION'
WHERE
    `metadata_sources`.`id` = 2;


UPDATE
    `metadata_sources`
SET
    `identifier` = 'oai:cwi.nl:%%ID',
    `base_url` = 'https://ir.cwi.nl/oai/',
    `doc_url` = 'https://persistent-identifier.org/?identifier=urn:nbn:nl:ui:18-%%ID',
    `paper_url` = 'https://ir.cwi.nl/pub/%%ID/%%ID.pdf'
WHERE
    `metadata_sources`.`id` = 3;


UPDATE
    `metadata_sources`
SET
    `identifier` = 'oai:zenodo.org:%%ID',
    `base_url` = 'https://zenodo.org/oai2d',
    `doi_prefix` = '10.5281',
    `api_url` = 'https://zenodo.org/api/',
    `doc_url` = 'https://zenodo.org/record/%%ID',
    `paper_url` = 'https://zenodo.org/record/files/%%ID'
WHERE
    `metadata_sources`.`id` = 4;


UPDATE
    `metadata_sources`
SET
    `identifier` = 'oai:bioRxiv.org:%%ID',
    `doi_prefix` = '10.1101',
    `api_url` = 'https://api.biorxiv.org/details/biorxiv/',
    `doc_url` = 'https://www.biorxiv.org/content/%%IDv%%VERSION',
    `paper_url` = 'https://www.biorxiv.org/content/%%IDv%%VERSION.full.pdf'
WHERE
    `metadata_sources`.`id` = 10;


UPDATE
    `metadata_sources`
SET
    `identifier` = 'oai:bioRxiv.org:%%ID',
    `doi_prefix` = '10.1101',
    `api_url` = 'https://api.biorxiv.org/details/medrxiv/',
    `doc_url` = 'https://www.medrxiv.org/content/%%IDv%%VERSION',
    `paper_url` = 'https://www.medrxiv.org/content/%%IDv%%VERSION.full.pdf'
WHERE
    `metadata_sources`.`id` = 11;


UPDATE
    `metadata_sources`
SET
    `id` = '0'
WHERE
    `metadata_sources`.`name` = 'Episciences';

    ALTER TABLE
        `metadata_sources` CHANGE `type` `type` ENUM(
            'repository',
            'metadataRepository',
            'dataverse',
            'user'
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL;


UPDATE `metadata_sources` SET `paper_url` = '' WHERE `metadata_sources`.`id` = 4;
UPDATE `metadata_sources` SET `doc_url` = 'https://entrepot.recherche.data.gouv.fr/dataset.xhtml?persistentId=%%ID' WHERE `metadata_sources`.`id` = 15;
UPDATE `metadata_sources` SET `doc_url` = 'https://darus.uni-stuttgart.de/dataset.xhtml?persistentId=%%ID' WHERE `metadata_sources`.`id` = 14;
UPDATE `metadata_sources` SET `name` = 'Recherche Data Gouv' WHERE `metadata_sources`.`id` = 15;

