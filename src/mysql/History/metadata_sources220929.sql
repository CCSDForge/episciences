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
-- Structure de la table `metadata_sources`
--

CREATE TABLE `metadata_sources` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('repository','metadataRepository','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `metadata_sources`
--

INSERT INTO `metadata_sources` (`id`, `name`, `type`) VALUES
(1, 'HAL', 'repository'),
(2, 'arXiv', 'repository'),
(3, 'CWI', 'repository'),
(4, 'Zenodo', 'repository'),
(5, 'ScholeXplorer', 'metadataRepository'),
(6, 'Crossref', 'metadataRepository'),
(7, 'Datacite', 'metadataRepository'),
(8, 'OpenAIRE Research Graph', 'metadataRepository'),
(9, 'Software Heritage', 'repository'),
(10, 'bioRxiv', 'repository'),
(11, 'medRxiv', 'repository'),
(12, 'Episciences User', 'user'),
(13, 'OpenCitations', 'metadataRepository');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `metadata_sources`
--
ALTER TABLE `metadata_sources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `type` (`type`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `metadata_sources`
--
ALTER TABLE `metadata_sources`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
