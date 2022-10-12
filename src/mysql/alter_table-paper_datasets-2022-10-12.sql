ALTER TABLE `paper_datasets-test` ADD `relationship` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `source_id`, ADD `id_paper_datasets_meta` INT UNSIGNED NULL AFTER `relationship`;
ALTER TABLE `paper_datasets-test` ADD INDEX(`id_paper_datasets_meta`);
