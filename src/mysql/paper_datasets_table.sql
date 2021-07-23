--
-- Table structure
--
CREATE TABLE `paper_datasets`(
                                 `id` INT(11) NOT NULL,
                                 `doc_id` INT(11) NOT NULL,
                                 `code` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
                                 `name` VARCHAR(200) COLLATE utf8mb4_unicode_ci NOT NULL,
                                 `value` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
                                 `link` VARCHAR(750) COLLATE utf8mb4_unicode_ci NOT NULL,
                                 `source_id` INT(11) NOT NULL,
                                 `time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
--
-- Index
--
ALTER TABLE
    `paper_datasets` ADD PRIMARY KEY(`id`),
    ADD UNIQUE KEY `unique`(
        `doc_id`,
        `code`(15),
        `name`(35),
        `value`(47),
        `source_id`
    ),
    ADD KEY `doc_id`(`doc_id`),
    ADD KEY `source_id`(`source_id`),
    ADD KEY `code`(`code`(15)),
    ADD KEY `name`(`name`(35));