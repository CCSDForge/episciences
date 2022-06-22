-- Généré le : lun. 20 juin 2022 à 10:34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Structure de la table `paper_projects`
--

CREATE TABLE `paper_projects` (
                                  `idproject` int UNSIGNED NOT NULL,
                                  `funding` mediumtext COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Json of funding',
                                  `paperid` int UNSIGNED NOT NULL,
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
  ADD KEY `idx_paperid_project` (`paperid`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `paper_projects`
--
ALTER TABLE `paper_projects`
    MODIFY `idproject` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `paper_projects`
--
ALTER TABLE `paper_projects`
    ADD CONSTRAINT `paper_projects_ibfk_1` FOREIGN KEY (`paperid`) REFERENCES `PAPERS` (`PAPERID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

ALTER TABLE `paper_projects` ADD `source_id` INT NOT NULL AFTER `paperid`, ADD INDEX `idx_source_id` (`source_id`);
ALTER TABLE `paper_projects` CHANGE `source_id` `source_id` INT UNSIGNED NOT NULL;
ALTER TABLE `paper_projects` ADD CONSTRAINT `paper_projects_ibfk_2` FOREIGN KEY (`source_id`) REFERENCES `metadata_sources`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;