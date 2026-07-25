-- CAS authentication database -- CI fixture
-- Schema-only dump, generated with:
--   mysqldump --no-data --skip-comments --routines=false --triggers=false \
--            --skip-lock-tables --single-transaction --no-tablespaces <db>
-- Regenerate it whenever a migration in src/mysql/ changes the schema.


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `FTP_GROUP`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `FTP_GROUP` (
  `row_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `groupname` varchar(16) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `gid` smallint NOT NULL DEFAULT '99',
  `members` varchar(16) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`row_id`),
  KEY `groupname` (`groupname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='Table des groupes ProFTPD';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `FTP_QUOTA_LIMITS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `FTP_QUOTA_LIMITS` (
  `Id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `quota_type` enum('user','group','class','all') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'user',
  `par_session` enum('false','true') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'false',
  `limit_type` enum('soft','hard') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'soft',
  `bytes_up_limit` float NOT NULL DEFAULT '0',
  `bytes_down_limit` float NOT NULL DEFAULT '0',
  `bytes_transfer_limit` float NOT NULL DEFAULT '0',
  `files_up_limit` int unsigned NOT NULL DEFAULT '0',
  `files_down_limit` int unsigned NOT NULL DEFAULT '0',
  `files_transfer_limit` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='Table des quotas ProFTPD';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `FTP_QUOTA_TOTAL`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `FTP_QUOTA_TOTAL` (
  `username` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `quota_type` enum('user','group','class','all') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'user',
  `bytes_up_total` float NOT NULL DEFAULT '0',
  `bytes_down_total` float NOT NULL DEFAULT '0',
  `bytes_transfer_total` float NOT NULL DEFAULT '0',
  `files_up_total` int unsigned NOT NULL DEFAULT '0',
  `files_down_total` int unsigned NOT NULL DEFAULT '0',
  `files_transfer_total` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`username`,`quota_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='Table des compteurs des quotas ProFTPD';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `SU_LOG`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SU_LOG` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `FROM_UID` int unsigned NOT NULL,
  `TO_UID` int unsigned NOT NULL,
  `APPLICATION` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `ACTION` enum('GRANTED','DENIED') DEFAULT NULL,
  `SU_TIME` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `FROM_UID` (`FROM_UID`,`TO_UID`,`APPLICATION`),
  KEY `ACTION` (`ACTION`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `T_UTILISATEURS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `T_UTILISATEURS` (
  `UID` int unsigned NOT NULL AUTO_INCREMENT,
  `USERNAME` varchar(100) NOT NULL,
  `PASSWORD` varchar(128) NOT NULL,
  `EMAIL` varchar(320) NOT NULL COMMENT 'http://tools.ietf.org/html/rfc3696#section-3',
  `CIV` varchar(255) DEFAULT NULL,
  `LASTNAME` varchar(100) NOT NULL,
  `FIRSTNAME` varchar(100) DEFAULT NULL,
  `MIDDLENAME` varchar(100) DEFAULT NULL,
  `URL` varchar(500) DEFAULT NULL,
  `PHONE` varchar(50) DEFAULT NULL,
  `FAX` varchar(50) DEFAULT NULL,
  `TIME_REGISTERED` timestamp NULL DEFAULT NULL COMMENT 'Date création du compte',
  `TIME_MODIFIED` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date modification du compte',
  `PHOTO` mediumblob,
  `FTP_HOME` varchar(255) DEFAULT NULL COMMENT 'Chemin du home FTP',
  `FTP_LAST_AUTH` datetime DEFAULT NULL COMMENT 'Dernière authentification par FTP',
  `FTP_LAST_USE` datetime DEFAULT NULL COMMENT 'Dernière utilisation du FTP',
  `VALID` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`UID`),
  UNIQUE KEY `U_USERNAME` (`USERNAME`),
  KEY `PASSWORD` (`PASSWORD`),
  KEY `VALID` (`VALID`),
  KEY `FIRSTNAME` (`FIRSTNAME`),
  KEY `LASTNAME` (`LASTNAME`),
  KEY `EMAIL` (`EMAIL`(100)),
  FULLTEXT KEY `FULLTEXT_INDEX` (`USERNAME`,`EMAIL`,`LASTNAME`,`FIRSTNAME`) /*!50100 WITH PARSER `ngram` */ 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 ROW_FORMAT=DYNAMIC COMMENT='Comptes utilisateurs pour CAS';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `T_UTILISATEURS_TOKENS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `T_UTILISATEURS_TOKENS` (
  `UID` int unsigned NOT NULL,
  `EMAIL` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT 'E-mail auquel le jeton est envoyé',
  `TOKEN` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT 'Jeton à usage unique',
  `TIME_MODIFIED` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `USAGE` enum('VALID','PASSWORD','NEW_EMAIL') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Jeton pour mot de passe perdu ou validation de compte',
  PRIMARY KEY (`EMAIL`,`TOKEN`),
  UNIQUE KEY `TOKEN` (`TOKEN`),
  KEY `USAGE` (`USAGE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `USER_ID_ASSOCIATION`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `USER_ID_ASSOCIATION` (
  `id_federation` varchar(250) NOT NULL,
  `federation` varchar(250) NOT NULL,
  `uid` varchar(100) DEFAULT NULL,
  `idp` varchar(250) DEFAULT NULL,
  `uidCcsd` int unsigned DEFAULT NULL,
  `nom` varchar(150) DEFAULT NULL,
  `prenom` varchar(150) DEFAULT NULL,
  `email` varchar(250) DEFAULT NULL,
  `valid` tinyint(1) NOT NULL,
  PRIMARY KEY (`federation`,`id_federation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `V_UTILISATEURS_VALIDES`;
/*!50001 DROP VIEW IF EXISTS `V_UTILISATEURS_VALIDES`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `V_UTILISATEURS_VALIDES` AS SELECT 
 1 AS `UID`,
 1 AS `USERNAME`,
 1 AS `PASSWORD`,
 1 AS `EMAIL`,
 1 AS `CIV`,
 1 AS `LASTNAME`,
 1 AS `FIRSTNAME`,
 1 AS `MIDDLENAME`*/;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `V_UTILISATEURS_VALIDES`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`%.in2p3.fr` SQL SECURITY DEFINER */
/*!50001 VIEW `V_UTILISATEURS_VALIDES` AS select `T_UTILISATEURS`.`UID` AS `UID`,`T_UTILISATEURS`.`USERNAME` AS `USERNAME`,`T_UTILISATEURS`.`PASSWORD` AS `PASSWORD`,`T_UTILISATEURS`.`EMAIL` AS `EMAIL`,`T_UTILISATEURS`.`CIV` AS `CIV`,`T_UTILISATEURS`.`LASTNAME` AS `LASTNAME`,`T_UTILISATEURS`.`FIRSTNAME` AS `FIRSTNAME`,`T_UTILISATEURS`.`MIDDLENAME` AS `MIDDLENAME` from `T_UTILISATEURS` where (`T_UTILISATEURS`.`VALID` = 1) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

