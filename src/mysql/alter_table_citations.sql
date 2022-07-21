CREATE TABLE `paper_citations` ( `id` INT UNSIGNED NOT NULL AUTO_INCREMENT , `citation` MEDIUMTEXT NOT NULL , `docid` INT UNSIGNED NOT NULL , `updated_at` DATETIME on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP , PRIMARY KEY (`id`), INDEX (`docid`)) ENGINE = InnoDB
ALTER TABLE `paper_citations` ADD FOREIGN KEY (`docid`) REFERENCES `PAPERS`(`DOCID`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `paper_citations` ADD `source_id` INT UNSIGNED NOT NULL AFTER `docid`, ADD INDEX (`source_id`);
ALTER TABLE `paper_citations` ADD FOREIGN KEY (`source_id`) REFERENCES `metadata_sources`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `paper_citations` CHANGE `citation` `citation` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Json Citations.php';
ALTER TABLE `paper_citations` ADD UNIQUE (`source_id`, `docid`);