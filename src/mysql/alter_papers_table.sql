ALTER TABLE
    `PAPERS` CHANGE `DESCRIPTION` `CONCEPT_IDENTIFIER` VARCHAR(500) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'This identifier represents all versions';