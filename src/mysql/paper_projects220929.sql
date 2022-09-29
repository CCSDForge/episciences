-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- Généré le : jeu. 29 sep. 2022 à 15:17
-- Version du serveur : 8.0.29
-- Version de PHP : 7.2.24-0ubuntu0.18.04.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `episciences_dev`
--

-- --------------------------------------------------------

--
-- Structure de la table `paper_projects`
--

CREATE TABLE `paper_projects` (
  `idproject` int UNSIGNED NOT NULL,
  `funding` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Json of funding',
  `paperid` int UNSIGNED NOT NULL,
  `source_id` int UNSIGNED NOT NULL,
  `date_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `paper_projects`
--
ALTER TABLE `paper_projects`
  ADD PRIMARY KEY (`idproject`),
  ADD UNIQUE KEY `paperid` (`paperid`),
  ADD UNIQUE KEY `paperid_src_uniq` (`paperid`,`source_id`) USING BTREE,
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
  ADD CONSTRAINT `paper_projects_ibfk_2` FOREIGN KEY (`source_id`) REFERENCES `metadata_sources` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
