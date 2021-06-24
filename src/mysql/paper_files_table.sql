--
-- Structure de la table `paper_files`
--

CREATE TABLE IF NOT EXISTS `paper_files` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `doc_id` int(10) UNSIGNED NOT NULL,
    `file_name` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
    `checksum` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
    `checksum_type` char(10) COLLATE utf8mb4_unicode_ci NOT NULL,
    `self_link` varchar(750) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `file_size` bigint(20) UNSIGNED NOT NULL,
    `file_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
    `time_modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `doc_id` (`doc_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
COMMIT;

ALTER TABLE `paper_files` ADD UNIQUE `unique_doc_id_file_name` (`doc_id`, `file_name`);