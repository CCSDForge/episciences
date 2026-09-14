-- Episciences main database -- CI fixture
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
DROP TABLE IF EXISTS `JOURNAL_SETTING`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `JOURNAL_SETTING` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `RVID` int unsigned NOT NULL,
  `SETTING` json NOT NULL,
  `CREATED_AT` datetime DEFAULT CURRENT_TIMESTAMP,
  `UPDATED_AT` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `RVID` (`RVID`),
  CONSTRAINT `JOURNAL_SETTING_ibfk_1` FOREIGN KEY (`RVID`) REFERENCES `REVIEW` (`RVID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `MAIL_LOG`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MAIL_LOG` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `UID` int unsigned DEFAULT NULL COMMENT 'Sender identifier (via the mailing module or the article page)',
  `RVID` int unsigned NOT NULL,
  `DOCID` int unsigned DEFAULT NULL,
  `FROM` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `REPLYTO` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `TO` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `CC` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `BCC` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `SUBJECT` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `CONTENT` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `FILES` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `WHEN` datetime NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `DOCID` (`DOCID`),
  KEY `WHEN` (`WHEN`),
  KEY `UID` (`UID`),
  KEY `idx_rvid_when` (`RVID`,`WHEN`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `MAIL_TEMPLATE`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MAIL_TEMPLATE` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `PARENTID` int unsigned DEFAULT NULL,
  `RVID` int unsigned DEFAULT NULL,
  `RVCODE` varchar(25) DEFAULT NULL,
  `KEY` varchar(255) NOT NULL,
  `TYPE` varchar(255) NOT NULL,
  `POSITION` int unsigned DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `KEY` (`KEY`),
  KEY `RVCODE` (`RVCODE`),
  KEY `RVID` (`RVID`),
  KEY `PARENTID` (`PARENTID`),
  KEY `POSITION` (`POSITION`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `NEWS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NEWS` (
  `NEWSID` int unsigned NOT NULL AUTO_INCREMENT,
  `RVID` int unsigned NOT NULL,
  `UID` int unsigned NOT NULL,
  `LINK` varchar(2000) NOT NULL,
  `ONLINE` tinyint unsigned NOT NULL,
  `DATE_POST` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`NEWSID`),
  KEY `RVID` (`RVID`),
  KEY `ONLINE` (`ONLINE`),
  KEY `DATE_POST` (`DATE_POST`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `PAPERS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PAPERS` (
  `DOCID` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique Identifier for each submission',
  `PAPERID` int unsigned DEFAULT NULL COMMENT 'Common Identifier for several versions of a paper',
  `DOI` varchar(250) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'PID of accepted and published papers',
  `RVID` int unsigned NOT NULL COMMENT 'Link to Journal ID',
  `VID` int unsigned NOT NULL DEFAULT '0' COMMENT 'Link to Volume ID',
  `SID` int unsigned NOT NULL DEFAULT '0' COMMENT 'Link to Section ID',
  `UID` int unsigned NOT NULL COMMENT 'Link to User ID',
  `STATUS` int unsigned NOT NULL DEFAULT '0' COMMENT 'Status of the submission',
  `IDENTIFIER` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT 'Open Repository Identifier',
  `VERSION` float unsigned NOT NULL DEFAULT '1' COMMENT 'Version identifier of a submission',
  `REPOID` int unsigned NOT NULL COMMENT 'Link to Repository ID',
  `TYPE` json DEFAULT NULL,
  `RECORD` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT 'Text of Metadata Record from Open repository ',
  `DOCUMENT` json DEFAULT NULL,
  `CONCEPT_IDENTIFIER` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'Zenodo ID This identifier represents all versions',
  `FLAG` enum('submitted','imported') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'submitted' COMMENT 'Submission source',
  `PASSWORD` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci COMMENT 'Encrypted temporary password for sharing arXiv submissions',
  `WHEN` datetime NOT NULL COMMENT 'Timestamp of insertion in database',
  `SUBMISSION_DATE` datetime NOT NULL COMMENT 'Timestamp of the 1st submission - common to all versions of a Paper',
  `MODIFICATION_DATE` datetime DEFAULT NULL COMMENT 'Timestamp of the update of the line in database',
  `PUBLICATION_DATE` datetime DEFAULT NULL COMMENT 'Timestamp of the publication date of a paper',
  PRIMARY KEY (`DOCID`),
  KEY `FK_REPOID_idx` (`REPOID`),
  KEY `FK_VID_idx` (`VID`),
  KEY `FK_RVID_idx` (`RVID`),
  KEY `STATUS` (`STATUS`),
  KEY `PAPERID` (`PAPERID`),
  KEY `SID` (`SID`),
  KEY `UID` (`UID`),
  KEY `SUBMISSION_DATE` (`SUBMISSION_DATE`),
  KEY `PUBLICATION_DATE` (`PUBLICATION_DATE`),
  KEY `FLAG` (`FLAG`),
  KEY `DOI` (`DOI`),
  KEY `idx_identifier` (`IDENTIFIER`),
  KEY `idx_concept_identifier` (`CONCEPT_IDENTIFIER`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Submissions';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `PAPER_COMMENTS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PAPER_COMMENTS` (
  `PCID` int unsigned NOT NULL AUTO_INCREMENT,
  `PARENTID` int unsigned DEFAULT NULL,
  `TYPE` int unsigned NOT NULL,
  `DOCID` int unsigned NOT NULL,
  `UID` int unsigned NOT NULL,
  `MESSAGE` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `FILE` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `WHEN` datetime NOT NULL,
  `DEADLINE` date DEFAULT NULL,
  `OPTIONS` text,
  PRIMARY KEY (`PCID`),
  KEY `DOCID` (`DOCID`),
  KEY `TYPE` (`TYPE`),
  KEY `UID` (`UID`),
  KEY `DEADLINE` (`DEADLINE`),
  KEY `WHEN` (`WHEN`),
  KEY `PARENTID` (`PARENTID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Suivi des demandes de modification sur un papier';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `PAPER_LOG`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PAPER_LOG` (
  `LOGID` int unsigned NOT NULL AUTO_INCREMENT,
  `PAPERID` int unsigned NOT NULL,
  `DOCID` int unsigned NOT NULL,
  `UID` int unsigned NOT NULL,
  `RVID` int unsigned NOT NULL,
  `ACTION` varchar(50) NOT NULL,
  `FILE` varchar(150) DEFAULT NULL,
  `DATE` datetime NOT NULL,
  `DETAIL` json DEFAULT NULL,
  `status` int unsigned GENERATED ALWAYS AS (json_unquote(json_extract(`DETAIL`,_utf8mb4'$.status'))) STORED,
  PRIMARY KEY (`LOGID`),
  KEY `fk_T_PAPER_MODIF_T_PAPERS_idx` (`DOCID`),
  KEY `fk_T_PAPER_MODIF_T_USER_idx` (`UID`),
  KEY `RVID` (`RVID`),
  KEY `DATE` (`DATE`),
  KEY `idx_status` (`status`),
  KEY `ACTION` (`ACTION`),
  KEY `idx_paperid_docid_date_logid` (`PAPERID`,`DOCID`,`DATE`,`LOGID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Life of papers';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `PAPER_SETTINGS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PAPER_SETTINGS` (
  `PSID` int unsigned NOT NULL AUTO_INCREMENT,
  `DOCID` int unsigned NOT NULL,
  `SETTING` varchar(100) NOT NULL,
  `VALUE` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`PSID`),
  KEY `SETTING` (`SETTING`),
  KEY `DOCID` (`DOCID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `PAPER_STAT`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PAPER_STAT` (
  `DOCID` int unsigned NOT NULL,
  `CONSULT` enum('notice','file','oai','api') NOT NULL DEFAULT 'notice',
  `IP` int unsigned NOT NULL,
  `ROBOT` tinyint unsigned NOT NULL DEFAULT '0',
  `AGENT` varchar(2000) DEFAULT NULL,
  `DOMAIN` varchar(100) DEFAULT NULL,
  `CONTINENT` varchar(100) DEFAULT NULL,
  `COUNTRY` varchar(100) DEFAULT NULL,
  `CITY` varchar(100) DEFAULT NULL,
  `LAT` float DEFAULT NULL,
  `LON` float DEFAULT NULL,
  `HIT` date NOT NULL,
  `COUNTER` int unsigned NOT NULL,
  PRIMARY KEY (`DOCID`,`CONSULT`,`IP`,`HIT`),
  KEY `CONSULT` (`CONSULT`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `REMINDERS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `REMINDERS` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `RVID` int unsigned DEFAULT NULL,
  `TYPE` tinyint unsigned DEFAULT NULL,
  `DELAY` smallint unsigned NOT NULL,
  `REPETITION` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `RECIPIENT` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'reviewer',
  PRIMARY KEY (`ID`),
  KEY `RVID` (`RVID`),
  KEY `TYPE` (`TYPE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `REVIEW`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `REVIEW` (
  `RVID` int unsigned NOT NULL AUTO_INCREMENT,
  `CODE` varchar(50) NOT NULL,
  `NAME` varchar(2000) NOT NULL,
  `subtitle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `STATUS` smallint unsigned NOT NULL DEFAULT '0',
  `CREATION` datetime NOT NULL,
  `PIWIKID` int unsigned NOT NULL,
  `is_new_front_switched` enum('yes','no') NOT NULL DEFAULT 'no',
  PRIMARY KEY (`RVID`),
  UNIQUE KEY `U_CODE` (`CODE`),
  KEY `STATUS` (`STATUS`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Basic journal informations';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `REVIEWER_ALIAS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `REVIEWER_ALIAS` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `UID` int unsigned NOT NULL,
  `DOCID` int unsigned NOT NULL,
  `ALIAS` int unsigned NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `UNIQUE` (`UID`,`DOCID`,`ALIAS`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `REVIEWER_POOL`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `REVIEWER_POOL` (
  `RVID` int unsigned NOT NULL,
  `VID` int unsigned NOT NULL DEFAULT '0',
  `UID` int unsigned NOT NULL,
  PRIMARY KEY (`RVID`,`VID`,`UID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `REVIEWER_REPORT`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `REVIEWER_REPORT` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `UID` int unsigned NOT NULL,
  `ONBEHALF_UID` int unsigned DEFAULT NULL COMMENT 'Mis à jour [!= de NULL] uniquement si l’évaluation est faite à la place de relecteur UID',
  `DOCID` int unsigned NOT NULL,
  `STATUS` int unsigned NOT NULL,
  `CREATION_DATE` datetime NOT NULL,
  `UPDATE_DATE` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `UID` (`UID`,`DOCID`),
  KEY `ONBEHALF_UID` (`ONBEHALF_UID`) USING BTREE,
  KEY `idx_docid` (`DOCID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `REVIEW_SETTING`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `REVIEW_SETTING` (
  `RVID` int unsigned NOT NULL,
  `SETTING` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `VALUE` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `TIME` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`RVID`,`SETTING`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Journal configurations';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `SECTION`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SECTION` (
  `SID` int unsigned NOT NULL AUTO_INCREMENT,
  `RVID` int unsigned NOT NULL,
  `POSITION` int unsigned NOT NULL,
  `titles` json DEFAULT NULL,
  `descriptions` json DEFAULT NULL,
  PRIMARY KEY (`SID`),
  KEY `RVID` (`RVID`),
  KEY `POSITION` (`POSITION`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `SECTION_SETTING`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SECTION_SETTING` (
  `SID` int unsigned NOT NULL,
  `SETTING` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `VALUE` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  PRIMARY KEY (`SID`,`SETTING`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `STAT_PROCESSING_LOG`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `STAT_PROCESSING_LOG` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `JOURNAL_CODE` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `PROCESSED_DATE` date NOT NULL,
  `PROCESSED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FILE_PATH` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `RECORDS_PROCESSED` int unsigned NOT NULL DEFAULT '0',
  `STATUS` enum('success','error','partial') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `unique_journal_date` (`JOURNAL_CODE`,`PROCESSED_DATE`),
  KEY `idx_processed_date` (`PROCESSED_DATE`),
  KEY `idx_processed_at` (`PROCESSED_AT`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks processed statistics log files to prevent duplicates';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `STAT_TEMP`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `STAT_TEMP` (
  `VISITID` int unsigned NOT NULL AUTO_INCREMENT,
  `DOCID` int unsigned NOT NULL,
  `IP` int unsigned NOT NULL,
  `HTTP_USER_AGENT` varchar(2000) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `DHIT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `CONSULT` enum('notice','file','oai') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'notice',
  PRIMARY KEY (`VISITID`),
  KEY `DOCID` (`DOCID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='Statistique de consultation journalière temporaire';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `USER`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `USER` (
  `UID` int unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Storing As a String',
  `LANGUEID` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'fr' COMMENT 'Account language code',
  `SCREEN_NAME` varchar(250) NOT NULL,
  `USERNAME` varchar(100) NOT NULL,
  `API_PASSWORD` varchar(255) NOT NULL,
  `EMAIL` varchar(320) NOT NULL,
  `CIV` varchar(255) DEFAULT NULL,
  `LASTNAME` varchar(100) NOT NULL,
  `FIRSTNAME` varchar(100) DEFAULT NULL,
  `MIDDLENAME` varchar(100) DEFAULT NULL,
  `ORCID` varchar(19) DEFAULT NULL,
  `ADDITIONAL_PROFILE_INFORMATION` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `REGISTRATION_DATE` timestamp NULL DEFAULT NULL COMMENT 'Date the profile was created',
  `MODIFICATION_DATE` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date the profile was updated',
  `IS_VALID` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Is account enabled',
  PRIMARY KEY (`UID`),
  UNIQUE KEY `U_USERNAME` (`USERNAME`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `IS_VALID` (`IS_VALID`),
  KEY `SCREEN_NAME` (`SCREEN_NAME`),
  KEY `EMAIL` (`EMAIL`(255)),
  KEY `REGISTRATION_DATE` (`REGISTRATION_DATE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `USER_ASSIGNMENT`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `USER_ASSIGNMENT` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `INVITATION_ID` int unsigned DEFAULT NULL,
  `RVID` int unsigned NOT NULL,
  `ITEMID` int unsigned NOT NULL,
  `ITEM` varchar(50) NOT NULL DEFAULT 'paper',
  `UID` int unsigned NOT NULL,
  `FROM_UID` int unsigned DEFAULT NULL COMMENT 'Linked from',
  `TMP_USER` tinyint unsigned NOT NULL DEFAULT '0',
  `ROLEID` varchar(50) NOT NULL,
  `STATUS` varchar(20) NOT NULL,
  `WHEN` datetime NOT NULL,
  `DEADLINE` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_ITEMID_idx` (`ITEMID`),
  KEY `FK_UID_idx` (`UID`),
  KEY `ITEM` (`ITEM`),
  KEY `ROLEID` (`ROLEID`),
  KEY `WHEN` (`WHEN`),
  KEY `TMP_USER` (`TMP_USER`),
  KEY `INVITATION_ID` (`INVITATION_ID`),
  KEY `LINKED_FROM` (`FROM_UID`),
  KEY `idx_rvid_roleid` (`RVID`,`ROLEID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `USER_INVITATION`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `USER_INVITATION` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `AID` int unsigned NOT NULL COMMENT 'Assignment ID',
  `STATUS` varchar(50) NOT NULL DEFAULT 'pending',
  `TOKEN` varchar(40) DEFAULT NULL,
  `SENDER_UID` int unsigned DEFAULT NULL,
  `SENDING_DATE` datetime NOT NULL,
  `EXPIRATION_DATE` datetime NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `SENDER_UID` (`SENDER_UID`),
  KEY `AID_SENDING_DATE` (`AID`,`SENDING_DATE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `USER_INVITATION_ANSWER`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `USER_INVITATION_ANSWER` (
  `ID_UIA` int unsigned NOT NULL AUTO_INCREMENT,
  `ID` int unsigned NOT NULL COMMENT 'Invitation ID',
  `ANSWER` varchar(10) NOT NULL,
  `ANSWER_DATE` datetime NOT NULL,
  PRIMARY KEY (`ID_UIA`),
  UNIQUE KEY `U_ID` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `USER_INVITATION_ANSWER_DETAIL`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `USER_INVITATION_ANSWER_DETAIL` (
  `ID_UIAD` int unsigned NOT NULL AUTO_INCREMENT,
  `ID` int unsigned NOT NULL COMMENT 'Invitation ID',
  `NAME` varchar(30) NOT NULL,
  `VALUE` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`ID_UIAD`),
  UNIQUE KEY `U_ID_NAME` (`ID`,`NAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `USER_MERGE`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `USER_MERGE` (
  `MID` int unsigned NOT NULL AUTO_INCREMENT,
  `TOKEN` varchar(40) DEFAULT NULL,
  `MERGER_UID` int unsigned NOT NULL COMMENT 'CASID du compte à fusionner',
  `KEEPER_UID` int unsigned NOT NULL COMMENT 'CASID du compte à conserver',
  `DETAIL` text,
  `DATE` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`MID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `USER_ROLES`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `USER_ROLES` (
  `UID` int unsigned NOT NULL,
  `RVID` int unsigned NOT NULL DEFAULT '0',
  `ROLEID` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `IS_AVAILABLE` tinyint unsigned DEFAULT NULL,
  PRIMARY KEY (`UID`,`RVID`,`ROLEID`),
  KEY `RVID` (`RVID`),
  KEY `ROLEID` (`ROLEID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `USER_TMP`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `USER_TMP` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `EMAIL` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `FIRSTNAME` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `LASTNAME` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `LANG` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `EMAIL` (`EMAIL`(150))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `VOLUME`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `VOLUME` (
  `VID` int unsigned NOT NULL AUTO_INCREMENT,
  `RVID` int unsigned NOT NULL,
  `POSITION` int unsigned NOT NULL,
  `BIB_REFERENCE` varchar(255) DEFAULT NULL COMMENT 'Volume bibliographical reference',
  `titles` json DEFAULT NULL,
  `descriptions` json DEFAULT NULL,
  `vol_year` varchar(9) DEFAULT NULL,
  `vol_type` set('special_issue','proceedings') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `vol_num` varchar(6) DEFAULT NULL,
  PRIMARY KEY (`VID`),
  KEY `FK_CONFID_idx` (`RVID`),
  KEY `POSITION` (`POSITION`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Journal volumes';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `VOLUME_METADATA`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `VOLUME_METADATA` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `VID` int unsigned NOT NULL,
  `POSITION` int unsigned NOT NULL,
  `CONTENT` json DEFAULT NULL COMMENT 'Metadata decsriptions',
  `FILE` varchar(250) DEFAULT NULL,
  `titles` json DEFAULT NULL,
  `date_creation` datetime DEFAULT NULL,
  `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `VID` (`VID`),
  KEY `POSITION` (`POSITION`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `VOLUME_PAPER`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `VOLUME_PAPER` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `VID` int unsigned NOT NULL,
  `DOCID` int unsigned NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `UNIQUE` (`VID`,`DOCID`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `VOLUME_PAPER_POSITION`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `VOLUME_PAPER_POSITION` (
  `ID` int unsigned NOT NULL AUTO_INCREMENT,
  `VID` int unsigned NOT NULL,
  `PAPERID` int unsigned NOT NULL,
  `POSITION` int unsigned NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `VID` (`VID`,`PAPERID`),
  KEY `POSITION` (`POSITION`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `VOLUME_SETTING`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `VOLUME_SETTING` (
  `VID` int unsigned NOT NULL,
  `SETTING` varchar(200) NOT NULL,
  `VALUE` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  PRIMARY KEY (`VID`,`SETTING`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `WEBSITE_HEADER`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `WEBSITE_HEADER` (
  `LOGOID` int unsigned NOT NULL AUTO_INCREMENT,
  `RVID` int unsigned NOT NULL,
  `TYPE` enum('img','text') NOT NULL,
  `IMG` varchar(255) NOT NULL,
  `IMG_WIDTH` varchar(255) NOT NULL,
  `IMG_HEIGHT` varchar(255) NOT NULL,
  `IMG_HREF` varchar(255) NOT NULL,
  `IMG_ALT` varchar(255) NOT NULL,
  `TEXT` varchar(1000) NOT NULL,
  `TEXT_CLASS` varchar(255) NOT NULL,
  `TEXT_STYLE` varchar(255) NOT NULL,
  `ALIGN` varchar(10) NOT NULL,
  PRIMARY KEY (`LOGOID`,`RVID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `WEBSITE_NAVIGATION`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `WEBSITE_NAVIGATION` (
  `NAVIGATIONID` int unsigned NOT NULL AUTO_INCREMENT,
  `SID` int unsigned NOT NULL COMMENT 'RVID',
  `PAGEID` int unsigned NOT NULL,
  `TYPE_PAGE` varchar(255) NOT NULL,
  `CONTROLLER` varchar(255) NOT NULL,
  `ACTION` varchar(255) NOT NULL,
  `LABEL` varchar(500) NOT NULL,
  `PARENT_PAGEID` int unsigned NOT NULL,
  `PARAMS` text NOT NULL,
  PRIMARY KEY (`NAVIGATIONID`),
  KEY `SID` (`SID`),
  KEY `TYPE_PAGE` (`TYPE_PAGE`),
  KEY `PARENT_PAGEID` (`PARENT_PAGEID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `WEBSITE_SETTINGS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `WEBSITE_SETTINGS` (
  `SID` int unsigned NOT NULL,
  `SETTING` varchar(50) NOT NULL,
  `VALUE` varchar(1000) NOT NULL,
  PRIMARY KEY (`SID`,`SETTING`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `WEBSITE_STYLES`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `WEBSITE_STYLES` (
  `RVID` int unsigned NOT NULL,
  `SETTING` varchar(50) NOT NULL,
  `VALUE` varchar(1000) NOT NULL,
  PRIMARY KEY (`RVID`,`SETTING`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `authors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `authors` (
  `idauthors` int unsigned NOT NULL AUTO_INCREMENT,
  `authors` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'json',
  `paperid` int unsigned NOT NULL,
  `date_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idauthors`),
  KEY `paperid` (`paperid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classification_jel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classification_jel` (
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classification_msc2020`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classification_msc2020` (
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_descriptor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_descriptor` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL,
  `docid` int unsigned NOT NULL,
  `fileid` int unsigned NOT NULL,
  `version` float unsigned NOT NULL DEFAULT '1',
  `submission_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `docid` (`docid`),
  KEY `fileid` (`fileid`),
  KEY `version` (`version`),
  KEY `submission_date` (`submission_date`),
  KEY `INDEX_UID` (`uid`),
  CONSTRAINT `FK_DD_FILES` FOREIGN KEY (`fileid`) REFERENCES `files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `doctrine_migration_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) COLLATE utf8mb3_unicode_ci NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `doi_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doi_queue` (
  `id_doi_queue` int unsigned NOT NULL AUTO_INCREMENT,
  `paperid` int unsigned NOT NULL,
  `doi_status` enum('assigned','requested','public','') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assigned',
  `date_init` datetime NOT NULL,
  `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_doi_queue`),
  UNIQUE KEY `paperid` (`paperid`),
  KEY `doi_status` (`doi_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `doi_queue_volumes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doi_queue_volumes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `vid` int unsigned NOT NULL,
  `doi_status` enum('assigned','requested','public','') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'assigned',
  `date_init` datetime NOT NULL,
  `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vid` (`vid`) USING BTREE,
  KEY `vid` (`vid`),
  CONSTRAINT `doi_queue_volumes_ibfk_1` FOREIGN KEY (`vid`) REFERENCES `VOLUME` (`VID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `docid` int unsigned NOT NULL,
  `name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `extension` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `type_mime` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `size` bigint unsigned NOT NULL,
  `md5` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'dd',
  `uploaded_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `INDEX_DOCID` (`docid`),
  KEY `INDEX_SOURCE` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mailing_list_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mailing_list_roles` (
  `list_id` int unsigned NOT NULL,
  `role` varchar(50) NOT NULL,
  PRIMARY KEY (`list_id`,`role`),
  CONSTRAINT `fk_mlr_list_id` FOREIGN KEY (`list_id`) REFERENCES `mailing_lists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mailing_list_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mailing_list_users` (
  `list_id` int unsigned NOT NULL,
  `uid` int unsigned NOT NULL,
  PRIMARY KEY (`list_id`,`uid`),
  CONSTRAINT `fk_mlu_list_id` FOREIGN KEY (`list_id`) REFERENCES `mailing_lists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mailing_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mailing_lists` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `rvid` int unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_unique` (`name`),
  KEY `rvid` (`rvid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `messenger_failed`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messenger_failed` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `body` longtext NOT NULL,
  `headers` longtext NOT NULL,
  `queue_name` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_BF048994FB7336F0E3BD61CE16BA31DBBF396750` (`queue_name`,`available_at`,`delivered_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `messenger_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messenger_messages` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `body` longtext NOT NULL,
  `headers` longtext NOT NULL,
  `queue_name` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750` (`queue_name`,`available_at`,`delivered_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `metadata_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `metadata_sources` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` enum('repository','metadataRepository','dataverse','dspace','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'enabled by default',
  `identifier` varchar(50) DEFAULT NULL COMMENT 'OAI identifier',
  `base_url` varchar(100) DEFAULT NULL COMMENT 'OAI base url',
  `doi_prefix` varchar(10) NOT NULL,
  `api_url` varchar(100) NOT NULL,
  `doc_url` varchar(150) NOT NULL COMMENT 'See the document''s page on',
  `paper_url` varchar(100) NOT NULL COMMENT 'PDF',
  PRIMARY KEY (`id`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `news` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `legacy_id` int unsigned DEFAULT NULL COMMENT 'Legacy News id',
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Journal code rvcode',
  `uid` int unsigned NOT NULL,
  `date_creation` datetime DEFAULT NULL,
  `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `title` json NOT NULL COMMENT 'Page title',
  `content` json DEFAULT NULL,
  `link` json DEFAULT NULL,
  `visibility` json NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Journal code rvcode',
  `uid` int unsigned NOT NULL,
  `date_creation` datetime DEFAULT NULL,
  `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `title` json NOT NULL COMMENT 'Page title',
  `content` json NOT NULL,
  `visibility` json NOT NULL,
  `page_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Page code',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `rvcode` (`code`) USING BTREE,
  KEY `page_code` (`page_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paper_citations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paper_citations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `citation` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Json Citations.php',
  `docid` int unsigned NOT NULL,
  `source_id` int unsigned NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_id_2` (`source_id`,`docid`),
  KEY `docid` (`docid`),
  KEY `source_id` (`source_id`),
  CONSTRAINT `paper_citations_ibfk_1` FOREIGN KEY (`docid`) REFERENCES `PAPERS` (`DOCID`),
  CONSTRAINT `paper_citations_ibfk_2` FOREIGN KEY (`source_id`) REFERENCES `metadata_sources` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paper_classifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paper_classifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `docid` int unsigned NOT NULL,
  `classification_code` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `classification_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_id` int unsigned NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniqClassification` (`docid`,`classification_code`,`classification_name`),
  KEY `source_id` (`source_id`),
  KEY `classification_code` (`classification_code`),
  KEY `classification_name` (`classification_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paper_conflicts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paper_conflicts` (
  `cid` int unsigned NOT NULL AUTO_INCREMENT,
  `paper_id` int unsigned NOT NULL,
  `by` int unsigned NOT NULL COMMENT 'uid',
  `answer` enum('yes','no') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cid`),
  UNIQUE KEY `U_PAPERID_BY` (`paper_id`,`by`) USING BTREE,
  KEY `BY_UID` (`by`),
  KEY `answer` (`answer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='conflicts handling';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paper_datasets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paper_datasets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `doc_id` int NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Identifier type',
  `value` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(750) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` int NOT NULL,
  `relationship` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_paper_datasets_meta` int unsigned DEFAULT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique` (`doc_id`,`code`(15),`name`(35),`value`(47),`source_id`),
  KEY `source_id` (`source_id`),
  KEY `code` (`code`(15)),
  KEY `name` (`name`(35)),
  KEY `id_paper_datasets_meta` (`id_paper_datasets_meta`),
  CONSTRAINT `deleteAssocMeta` FOREIGN KEY (`id_paper_datasets_meta`) REFERENCES `paper_datasets_meta` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paper_datasets_meta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paper_datasets_meta` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `metatext` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'JSON text',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paper_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paper_files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `doc_id` int unsigned NOT NULL,
  `source` int NOT NULL DEFAULT '4',
  `file_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `checksum` char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `checksum_type` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'md5',
  `self_link` varchar(750) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned NOT NULL,
  `file_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates that this is the main file ',
  `time_modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_docid_self_link` (`doc_id`,`self_link`),
  KEY `doc_id` (`doc_id`),
  KEY `source` (`source`),
  KEY `is_main_index` (`is_main`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paper_files-2026-07-16`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paper_files-2026-07-16` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `doc_id` int unsigned NOT NULL,
  `source` int NOT NULL DEFAULT '4',
  `file_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `checksum` char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `checksum_type` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'md5',
  `self_link` varchar(750) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned NOT NULL,
  `file_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `time_modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `doc_id` (`doc_id`),
  KEY `source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paper_licences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paper_licences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `licence` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `docid` int unsigned NOT NULL,
  `source_id` int unsigned NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `docid` (`docid`),
  KEY `source_id` (`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paper_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paper_projects` (
  `idproject` int unsigned NOT NULL AUTO_INCREMENT,
  `funding` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Json of funding',
  `paperid` int unsigned NOT NULL,
  `source_id` int unsigned NOT NULL,
  `date_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idproject`),
  UNIQUE KEY `paperid` (`paperid`),
  KEY `idx_source_id` (`source_id`),
  CONSTRAINT `paper_projects_ibfk_1` FOREIGN KEY (`paperid`) REFERENCES `PAPERS` (`PAPERID`),
  CONSTRAINT `paper_projects_ibfk_2` FOREIGN KEY (`source_id`) REFERENCES `metadata_sources` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `queue_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `queue_messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `rvcode` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT '',
  `message` json NOT NULL,
  `created_at` int unsigned NOT NULL,
  `timeout` int unsigned NOT NULL DEFAULT '120',
  `processed` tinyint(1) NOT NULL DEFAULT '0',
  `updated_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`processed`),
  KEY `idx_timeout` (`timeout`),
  KEY `idx_created` (`created_at`),
  KEY `idx_time_status` (`processed`,`created_at`),
  KEY `idx_ready_messages` (`processed`,`timeout`,`created_at`),
  KEY `idx_type` (`type`),
  KEY `idx_rvcode` (`rvcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `refresh_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `refresh_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `refreshToken` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rvid` int unsigned DEFAULT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `valid` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_9BACE7E16973EC66` (`refreshToken`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `v_mailing_lists_resolved`;
/*!50001 DROP VIEW IF EXISTS `v_mailing_lists_resolved`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_mailing_lists_resolved` AS SELECT 
 1 AS `list_id`,
 1 AS `journal_id`,
 1 AS `list_name`,
 1 AS `list_type`,
 1 AS `list_status`,
 1 AS `members`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `volume_proceeding`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `volume_proceeding` (
  `VID` int unsigned NOT NULL,
  `SETTING` varchar(200) NOT NULL,
  `VALUE` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  PRIMARY KEY (`VID`,`SETTING`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `v_mailing_lists_resolved`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY INVOKER */
/*!50001 VIEW `v_mailing_lists_resolved` AS with `list_members_data` as (select `ml`.`id` AS `list_id`,`u`.`UID` AS `UID`,`u`.`FIRSTNAME` AS `FIRSTNAME`,`u`.`LASTNAME` AS `LASTNAME`,`u`.`EMAIL` AS `EMAIL` from ((`mailing_lists` `ml` join (select `mailing_list_users`.`list_id` AS `list_id`,`mailing_list_users`.`uid` AS `uid` from `mailing_list_users` union select `mlr`.`list_id` AS `list_id`,`ur`.`UID` AS `uid` from ((`mailing_list_roles` `mlr` join `mailing_lists` `ml_inner` on((`ml_inner`.`id` = `mlr`.`list_id`))) join `USER_ROLES` `ur` on(((`ur`.`ROLEID` = `mlr`.`role`) and (`ur`.`RVID` = `ml_inner`.`rvid`))))) `lm` on((`ml`.`id` = `lm`.`list_id`))) join `USER` `u` on((`lm`.`uid` = `u`.`UID`))) where (`u`.`IS_VALID` = 1)) select `ml`.`id` AS `list_id`,`ml`.`rvid` AS `journal_id`,`ml`.`name` AS `list_name`,`ml`.`type` AS `list_type`,if((`ml`.`status` = 1),'open','closed') AS `list_status`,if((count(`lmd`.`UID`) = 0),json_array(),json_arrayagg(json_object('firstname',`lmd`.`FIRSTNAME`,'lastname',`lmd`.`LASTNAME`,'email',`lmd`.`EMAIL`))) AS `members` from (`mailing_lists` `ml` left join `list_members_data` `lmd` on((`ml`.`id` = `lmd`.`list_id`))) group by `ml`.`id`,`ml`.`rvid`,`ml`.`name`,`ml`.`type`,`ml`.`status` */;
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

