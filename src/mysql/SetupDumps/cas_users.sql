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
-- Base de données : `CAS_users`
--

-- --------------------------------------------------------

--
-- Structure de la table `SU_LOG`
--

CREATE TABLE `SU_LOG`
(
    `ID`          int(10) UNSIGNED NOT NULL,
    `FROM_UID`    int(10) UNSIGNED NOT NULL,
    `TO_UID`      int(10) UNSIGNED NOT NULL,
    `APPLICATION` varchar(50) CHARACTER SET utf8 NOT NULL,
    `ACTION`      enum('GRANTED','DENIED') DEFAULT NULL,
    `SU_TIME`     timestamp                      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `T_UTILISATEURS`
--

CREATE TABLE `T_UTILISATEURS`
(
    `UID`             int(11) UNSIGNED NOT NULL,
    `USERNAME`        varchar(100) NOT NULL,
    `PASSWORD`        varchar(128) NOT NULL,
    `EMAIL`           varchar(320) NOT NULL COMMENT 'http://tools.ietf.org/html/rfc3696#section-3',
    `CIV`             varchar(255) DEFAULT NULL,
    `LASTNAME`        varchar(100) NOT NULL,
    `FIRSTNAME`       varchar(100) DEFAULT NULL,
    `MIDDLENAME`      varchar(100) DEFAULT NULL,
    `URL`             varchar(500) DEFAULT NULL,
    `PHONE`           varchar(50)  DEFAULT NULL,
    `FAX`             varchar(50)  DEFAULT NULL,
    `TIME_REGISTERED` timestamp NULL DEFAULT NULL COMMENT 'Date création du compte',
    `TIME_MODIFIED`   timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date modification du compte',
    `PHOTO`           mediumblob,
    `FTP_HOME`        varchar(255) DEFAULT NULL COMMENT 'Chemin du home FTP',
    `FTP_LAST_AUTH`   datetime     DEFAULT NULL COMMENT 'Dernière authentification par FTP',
    `FTP_LAST_USE`    datetime     DEFAULT NULL COMMENT 'Dernière utilisation du FTP',
    `VALID`           tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Comptes utilisateurs pour CAS';

-- --------------------------------------------------------

--
-- Structure de la table `T_UTILISATEURS_TOKENS`
--

CREATE TABLE `T_UTILISATEURS_TOKENS`
(
    `UID`           int(10) UNSIGNED NOT NULL,
    `EMAIL`         varchar(100) CHARACTER SET utf8 NOT NULL COMMENT 'E-mail auquel le jeton est envoyé',
    `TOKEN`         varchar(40) CHARACTER SET utf8  NOT NULL COMMENT 'Jeton à usage unique',
    `TIME_MODIFIED` timestamp                       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `USAGE` set('VALID','PASSWORD') COLLATE utf8_unicode_ci NOT NULL COMMENT 'Jeton pour mot de passe perdu ou validation de compte'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `V_UTILISATEURS_VALIDES`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `V_UTILISATEURS_VALIDES`
(
    `UID`        int(11) unsigned,
    `USERNAME`   varchar(100),
    `PASSWORD`   varchar(128),
    `EMAIL`      varchar(320),
    `CIV`        varchar(255),
    `LASTNAME`   varchar(100),
    `FIRSTNAME`  varchar(100),
    `MIDDLENAME` varchar(100)
);

-- --------------------------------------------------------

--
-- Structure de la vue `V_UTILISATEURS_VALIDES`
--
DROP TABLE IF EXISTS `V_UTILISATEURS_VALIDES`;

CREATE
ALGORITHM=UNDEFINED DEFINER=`root`@`%.in2p3.fr` SQL SECURITY DEFINER VIEW `V_UTILISATEURS_VALIDES`  AS
SELECT `T_UTILISATEURS`.`UID`        AS `UID`,
       `T_UTILISATEURS`.`USERNAME`   AS `USERNAME`,
       `T_UTILISATEURS`.`PASSWORD`   AS `PASSWORD`,
       `T_UTILISATEURS`.`EMAIL`      AS `EMAIL`,
       `T_UTILISATEURS`.`CIV`        AS `CIV`,
       `T_UTILISATEURS`.`LASTNAME`   AS `LASTNAME`,
       `T_UTILISATEURS`.`FIRSTNAME`  AS `FIRSTNAME`,
       `T_UTILISATEURS`.`MIDDLENAME` AS `MIDDLENAME`
FROM `T_UTILISATEURS`
WHERE (`T_UTILISATEURS`.`VALID` = 1);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `SU_LOG`
--
ALTER TABLE `SU_LOG`
    ADD PRIMARY KEY (`ID`),
  ADD KEY `FROM_UID` (`FROM_UID`,`TO_UID`,`APPLICATION`),
  ADD KEY `ACTION` (`ACTION`);

--
-- Index pour la table `T_UTILISATEURS`
--
ALTER TABLE `T_UTILISATEURS`
    ADD PRIMARY KEY (`UID`),
  ADD UNIQUE KEY `U_USERNAME` (`USERNAME`),
  ADD KEY `PASSWORD` (`PASSWORD`),
  ADD KEY `EMAIL` (`EMAIL`),
  ADD KEY `VALID` (`VALID`),
  ADD KEY `FIRSTNAME` (`FIRSTNAME`),
  ADD KEY `LASTNAME` (`LASTNAME`);

--
-- Index pour la table `T_UTILISATEURS_TOKENS`
--
ALTER TABLE `T_UTILISATEURS_TOKENS`
    ADD PRIMARY KEY (`EMAIL`, `TOKEN`),
  ADD UNIQUE KEY `TOKEN` (`TOKEN`),
  ADD KEY `USAGE` (`USAGE`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `SU_LOG`
--
ALTER TABLE `SU_LOG`
    MODIFY `ID` int (10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `T_UTILISATEURS`
--
ALTER TABLE `T_UTILISATEURS`
    MODIFY `UID` int (11) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;