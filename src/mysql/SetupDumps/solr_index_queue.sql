SET
SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET
time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `SOLR_INDEX`
--

-- --------------------------------------------------------

--
-- Structure de la table `INDEX_QUEUE`
--

CREATE TABLE `INDEX_QUEUE`
(
    `ID`          int(10) UNSIGNED NOT NULL,
    `DOCID`       varchar(50) NOT NULL,
    `UPDATED`     timestamp   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `APPLICATION` varchar(50) NOT NULL COMMENT 'Nom de l''application qui demande l''indexation',
    `ORIGIN` set('UPDATE','DELETE','') NOT NULL COMMENT 'Origine de la mise à jour',
    `CORE`        varchar(50) NOT NULL COMMENT 'Nom du core dans solr',
    `PRIORITY`    tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Priorité de l''indexation',
    `PID`         int(10) UNSIGNED NOT NULL DEFAULT '0',
    `HOSTNAME`    varchar(255)         DEFAULT NULL,
    `STATUS` set('locked','error','ok','') NOT NULL DEFAULT 'ok' COMMENT 'Etat de la ligne',
    `MESSAGE`     varchar(255)         DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `INDEX_QUEUE`
--
ALTER TABLE `INDEX_QUEUE`
    ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `DOCID` (`DOCID`,`ORIGIN`,`CORE`),
  ADD KEY `STATUS` (`STATUS`),
  ADD KEY `PRIORITY` (`PRIORITY`),
  ADD KEY `ORIGIN` (`ORIGIN`),
  ADD KEY `PID` (`PID`),
  ADD KEY `HOSTNAME` (`HOSTNAME`);
ALTER TABLE `INDEX_QUEUE`
    ADD FULLTEXT KEY `CORE` (`CORE`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `INDEX_QUEUE`
--
ALTER TABLE `INDEX_QUEUE`
    MODIFY `ID` int (10) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;