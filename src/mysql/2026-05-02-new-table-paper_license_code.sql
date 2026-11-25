CREATE TABLE IF NOT EXISTS `paper_license_code`(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `docid` INT UNSIGNED NOT NULL,
    `code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'SPDX identifier',
    PRIMARY KEY(`id`)
) ENGINE = InnoDB;

--
-- Indexes for table `paper_license_code`
--
ALTER TABLE `paper_license_code`
  ADD UNIQUE KEY `uniq_docid` (`docid`) USING BTREE;