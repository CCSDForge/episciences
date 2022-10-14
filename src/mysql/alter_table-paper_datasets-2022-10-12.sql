ALTER TABLE `paper_datasets` ADD `relationship` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `source_id`, ADD `id_paper_datasets_meta` INT UNSIGNED NULL AFTER `relationship`;
ALTER TABLE `paper_datasets` ADD INDEX(`id_paper_datasets_meta`);
ALTER TABLE `paper_datasets` ADD CONSTRAINT `deleteAssocMeta` FOREIGN KEY (`id_paper_datasets_meta`) REFERENCES `paper_datasets_meta` (`id`) ON DELETE CASCADE;
