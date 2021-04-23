--
-- Structure de la table `paper_files`
--

CREATE TABLE `episciences`.`paper_files`
(
    `id`            int UNSIGNED NOT NULL,
    `doc_id`        int UNSIGNED NOT NULL,
    `file_name`     varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `checksum`      char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci     NOT NULL,
    `checksum_type` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci     NOT NULL,
    `self_link`     varchar(750) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `file_size`     bigint UNSIGNED NOT NULL,
    `file_type`     varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
    `time_modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Index pour la table `paper_files`
--
ALTER TABLE `episciences`.`paper_files`
    ADD PRIMARY KEY (`id`),
    ADD KEY `file_name` (`file_name`),
    ADD KEY `doc_id` (`doc_id`) USING BTREE;

--
-- AUTO_INCREMENT pour la table `paper_files`
--
ALTER TABLE `episciences`.`paper_files`
    MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;






