-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le : jeu. 23 mai 2024 à 12:56
-- Version du serveur : 8.0.36-0ubuntu0.20.04.1
-- Version de PHP : 8.2.19

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
-- Structure de la table `volume_proceeding`
--

CREATE TABLE `volume_proceeding` (
  `VID` int UNSIGNED NOT NULL,
  `SETTING` varchar(200) NOT NULL,
  `VALUE` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `volume_proceeding`
--
ALTER TABLE `volume_proceeding`
  ADD PRIMARY KEY (`VID`,`SETTING`),
  ADD KEY `FK_RVID0_idx` (`VID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
