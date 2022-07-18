--
-- Structure de la table `paper_projects`
--

CREATE TABLE `paper_projects` (
  `idproject` int UNSIGNED NOT NULL,
  `funding` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Json of funding',
  `paperid` int UNSIGNED NOT NULL,
  `source_id` int UNSIGNED NOT NULL,
  `date_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `paper_projects`
--
ALTER TABLE `paper_projects`
  ADD PRIMARY KEY (`idproject`),
  ADD UNIQUE KEY `paperid` (`paperid`),
  ADD KEY `idx_paperid_project` (`paperid`),
  ADD KEY `idx_source_id` (`source_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `paper_projects`
--
ALTER TABLE `paper_projects`
  MODIFY `idproject` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `paper_projects`
--
ALTER TABLE `paper_projects`
  ADD CONSTRAINT `paper_projects_ibfk_1` FOREIGN KEY (`paperid`) REFERENCES `PAPERS` (`PAPERID`),
  ADD CONSTRAINT `paper_projects_ibfk_2` FOREIGN KEY (`source_id`) REFERENCES `metadata_sources` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `paper_projects` DROP INDEX `paperid`, ADD UNIQUE `unique_idx_paper_source` (`paperid`, `source_id`) USING BTREE;