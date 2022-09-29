-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- Généré le : jeu. 29 sep. 2022 à 15:16
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
-- Structure de la table `paper_datasets`
--

CREATE TABLE `paper_datasets` (
  `id` int UNSIGNED NOT NULL,
  `doc_id` int UNSIGNED NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(750) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` int UNSIGNED NOT NULL,
  `relationship` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_paper_datasets_meta` int UNSIGNED DEFAULT NULL COMMENT 'Link to JSON text',
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `paper_datasets`
--
ALTER TABLE `paper_datasets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique` (`doc_id`,`code`(15),`name`(35),`value`(47),`source_id`),
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `source_id` (`source_id`),
  ADD KEY `code` (`code`(15)),
  ADD KEY `name` (`name`(35)),
  ADD KEY `id_paper_datasets_meta` (`id_paper_datasets_meta`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `paper_datasets`
--
ALTER TABLE `paper_datasets`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `paper_datasets`
--
ALTER TABLE `paper_datasets`
  ADD CONSTRAINT `deleteAssocMeta` FOREIGN KEY (`id_paper_datasets_meta`) REFERENCES `paper_datasets_meta` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
