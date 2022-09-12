-- phpMyAdmin SQL Dump
-- version 4.9.5deb2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : ven. 26 août 2022 à 14:17
-- Version du serveur :  8.0.30-0ubuntu0.20.04.2
-- Version de PHP : 7.4.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
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
-- Structure de la table `paper_classifications`
--

CREATE TABLE `paper_classifications` (
                                         `id` int UNSIGNED NOT NULL,
                                         `paperid` int UNSIGNED NOT NULL,
                                         `classification` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                                         `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                                         `source_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `paper_classifications`
--
ALTER TABLE `paper_classifications`
    ADD PRIMARY KEY (`id`),
  ADD KEY `paperid` (`paperid`),
  ADD KEY `type` (`type`),
  ADD KEY `source_id` (`source_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `paper_classifications`
--
ALTER TABLE `paper_classifications`
    MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `paper_classifications`
--
ALTER TABLE `paper_classifications`
    ADD CONSTRAINT `paper_classifications_ibfk_1` FOREIGN KEY (`paperid`) REFERENCES `PAPERS` (`PAPERID`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `paper_classifications_ibfk_2` FOREIGN KEY (`source_id`) REFERENCES `metadata_sources` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `paper_classifications` ADD UNIQUE (`classification`, `paperid`, `source_id`, `type`);

COMMIT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


