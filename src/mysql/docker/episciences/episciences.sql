-- Generation Time: Jun 13, 2024 at 04:01 PM
-- Server version: 8.0.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `episciences`
--

-- --------------------------------------------------------

--
-- Table structure for table `authors`
--

CREATE TABLE `authors` (
                           `idauthors` int UNSIGNED NOT NULL,
                           `authors` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'json',
                           `paperid` int UNSIGNED NOT NULL,
                           `date_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doi_queue`
--

CREATE TABLE `doi_queue` (
                             `id_doi_queue` int UNSIGNED NOT NULL,
                             `paperid` int UNSIGNED NOT NULL,
                             `doi_status` enum('assigned','requested','public','') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assigned',
                             `date_init` datetime NOT NULL,
                             `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doi_queue_volumes`
--

CREATE TABLE `doi_queue_volumes` (
                                     `id` int UNSIGNED NOT NULL,
                                     `vid` int UNSIGNED NOT NULL,
                                     `doi_status` enum('assigned','requested','public','') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'assigned',
                                     `date_init` datetime NOT NULL,
                                     `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MAIL_LOG`
--

CREATE TABLE `MAIL_LOG` (
                            `ID` int UNSIGNED NOT NULL,
                            `RVID` int UNSIGNED NOT NULL,
                            `DOCID` int UNSIGNED DEFAULT NULL,
                            `FROM` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
                            `REPLYTO` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
                            `TO` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
                            `CC` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
                            `BCC` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
                            `SUBJECT` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
                            `CONTENT` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                            `FILES` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                            `WHEN` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MAIL_TEMPLATE`
--

CREATE TABLE `MAIL_TEMPLATE` (
                                 `ID` int UNSIGNED NOT NULL,
                                 `PARENTID` int UNSIGNED DEFAULT NULL,
                                 `RVID` int UNSIGNED DEFAULT NULL,
                                 `RVCODE` varchar(25) DEFAULT NULL,
                                 `KEY` varchar(255) NOT NULL,
                                 `TYPE` varchar(255) NOT NULL,
                                 `POSITION` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `metadata_sources`
--

CREATE TABLE `metadata_sources` (
                                    `id` int UNSIGNED NOT NULL,
                                    `name` varchar(255) NOT NULL,
                                    `type` enum('repository','metadataRepository','dataverse','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
                                    `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'enabled by default',
                                    `identifier` varchar(50) DEFAULT NULL COMMENT 'OAI identifier',
                                    `base_url` varchar(100) DEFAULT NULL COMMENT 'OAI base url',
                                    `doi_prefix` varchar(10) NOT NULL,
                                    `api_url` varchar(100) NOT NULL,
                                    `doc_url` varchar(150) NOT NULL COMMENT 'See the document''s page on',
                                    `paper_url` varchar(100) NOT NULL COMMENT 'PDF'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `NEWS`
--

CREATE TABLE `NEWS` (
                        `NEWSID` int UNSIGNED NOT NULL,
                        `RVID` int UNSIGNED NOT NULL,
                        `UID` int UNSIGNED NOT NULL,
                        `LINK` varchar(2000) NOT NULL,
                        `ONLINE` tinyint UNSIGNED NOT NULL,
                        `DATE_POST` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `PAPERS`
--

CREATE TABLE `PAPERS` (
                          `DOCID` int UNSIGNED NOT NULL COMMENT 'Unique Identifier for each submission',
                          `PAPERID` int UNSIGNED DEFAULT NULL COMMENT 'Common Identifier for several versions of a paper',
                          `DOI` varchar(250) CHARACTER SET utf8mb3 COLLATE utf8_general_ci DEFAULT NULL COMMENT 'PID of accepted and published papers',
                          `TYPE` json DEFAULT NULL,
                          `RVID` int UNSIGNED NOT NULL COMMENT 'Link to Journal ID',
                          `VID` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Link to Volume ID',
                          `SID` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Link to Section ID',
                          `UID` int UNSIGNED NOT NULL COMMENT 'Link to User ID',
                          `STATUS` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Status of the submission',
                          `IDENTIFIER` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8_general_ci NOT NULL COMMENT 'Open Repository Identifier',
                          `VERSION` float UNSIGNED NOT NULL DEFAULT '1' COMMENT 'Version identifier of a submission',
                          `REPOID` int UNSIGNED NOT NULL COMMENT 'Link to Repository ID',
                          `RECORD` text CHARACTER SET utf8mb3 COLLATE utf8_general_ci NOT NULL COMMENT 'Text of Metadata Record from Open repository ',
                          `CONCEPT_IDENTIFIER` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8_general_ci DEFAULT NULL COMMENT 'Zenodo ID This identifier represents all versions',
                          `FLAG` enum('submitted','imported') CHARACTER SET utf8mb3 COLLATE utf8_general_ci NOT NULL DEFAULT 'submitted' COMMENT 'Submission source',
                          `PASSWORD` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci COMMENT 'Encrypted temporary password for sharing arXiv submissions',
                          `WHEN` datetime NOT NULL COMMENT 'Timestamp of insertion in database',
                          `SUBMISSION_DATE` datetime NOT NULL COMMENT 'Timestamp of the 1st submission - common to all versions of a Paper',
                          `MODIFICATION_DATE` datetime DEFAULT NULL COMMENT 'Timestamp of the update of the line in database',
                          `PUBLICATION_DATE` datetime DEFAULT NULL COMMENT 'Timestamp of the publication date of a paper'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Submissions';

-- --------------------------------------------------------

--
-- Table structure for table `paper_citations`
--

CREATE TABLE `paper_citations` (
                                   `id` int UNSIGNED NOT NULL,
                                   `citation` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Json Citations.php',
                                   `docid` int UNSIGNED NOT NULL,
                                   `source_id` int UNSIGNED NOT NULL,
                                   `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `paper_classifications`
--

CREATE TABLE `paper_classifications` (
                                         `id` int UNSIGNED NOT NULL,
                                         `paperid` int UNSIGNED NOT NULL,
                                         `classification` varchar(200) NOT NULL,
                                         `type` varchar(50) NOT NULL,
                                         `source_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `PAPER_COMMENTS`
--

CREATE TABLE `PAPER_COMMENTS` (
                                  `PCID` int UNSIGNED NOT NULL,
                                  `PARENTID` int UNSIGNED DEFAULT NULL,
                                  `TYPE` int UNSIGNED NOT NULL,
                                  `DOCID` int UNSIGNED NOT NULL,
                                  `UID` int UNSIGNED NOT NULL,
                                  `MESSAGE` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
                                  `FILE` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
                                  `WHEN` datetime NOT NULL,
                                  `DEADLINE` date DEFAULT NULL,
                                  `OPTIONS` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Suivi des demandes de modification sur un papier';

-- --------------------------------------------------------

--
-- Table structure for table `paper_conflicts`
--

CREATE TABLE `paper_conflicts` (
                                   `cid` int UNSIGNED NOT NULL,
                                   `paper_id` int UNSIGNED NOT NULL,
                                   `by` int UNSIGNED NOT NULL COMMENT 'uid',
                                   `answer` enum('yes','no') CHARACTER SET utf8mb3 COLLATE utf8_general_ci NOT NULL,
                                   `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
                                   `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='conflicts handling';

-- --------------------------------------------------------

--
-- Table structure for table `paper_datasets`
--

CREATE TABLE `paper_datasets` (
                                  `id` int NOT NULL,
                                  `doc_id` int NOT NULL,
                                  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                                  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Identifier type',
                                  `value` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                                  `link` varchar(750) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                                  `source_id` int NOT NULL,
                                  `relationship` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                                  `id_paper_datasets_meta` int UNSIGNED DEFAULT NULL,
                                  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `paper_datasets_meta`
--

CREATE TABLE `paper_datasets_meta` (
                                       `id` int UNSIGNED NOT NULL,
                                       `metatext` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'JSON text',
                                       `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `paper_files`
--

CREATE TABLE `paper_files` (
                               `id` int UNSIGNED NOT NULL,
                               `doc_id` int UNSIGNED NOT NULL,
                               `source` int NOT NULL DEFAULT '4',
                               `file_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                               `checksum` char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                               `checksum_type` char(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'md5',
                               `self_link` varchar(750) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                               `file_size` bigint UNSIGNED NOT NULL,
                               `file_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                               `time_modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `paper_licences`
--

CREATE TABLE `paper_licences` (
                                  `id` int UNSIGNED NOT NULL,
                                  `licence` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                                  `docid` int UNSIGNED NOT NULL,
                                  `source_id` int UNSIGNED NOT NULL,
                                  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `PAPER_LOG`
--

CREATE TABLE `PAPER_LOG` (
                             `LOGID` int UNSIGNED NOT NULL,
                             `PAPERID` int UNSIGNED NOT NULL,
                             `DOCID` int UNSIGNED NOT NULL,
                             `UID` int UNSIGNED NOT NULL,
                             `RVID` int UNSIGNED NOT NULL,
                             `ACTION` varchar(50) NOT NULL,
                             `DETAIL` mediumtext,
                             `FILE` varchar(150) DEFAULT NULL,
                             `DATE` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Life of papers';

-- --------------------------------------------------------

--
-- Table structure for table `paper_projects`
--

CREATE TABLE `paper_projects` (
                                  `idproject` int UNSIGNED NOT NULL,
                                  `funding` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Json of funding',
                                  `paperid` int UNSIGNED NOT NULL,
                                  `source_id` int UNSIGNED NOT NULL,
                                  `date_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `PAPER_SETTINGS`
--

CREATE TABLE `PAPER_SETTINGS` (
                                  `PSID` int UNSIGNED NOT NULL,
                                  `DOCID` int UNSIGNED NOT NULL,
                                  `SETTING` varchar(100) NOT NULL,
                                  `VALUE` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `PAPER_STAT`
--

CREATE TABLE `PAPER_STAT` (
                              `DOCID` int UNSIGNED NOT NULL,
                              `CONSULT` enum('notice','file','oai','api') NOT NULL DEFAULT 'notice',
                              `IP` int UNSIGNED NOT NULL,
                              `ROBOT` tinyint UNSIGNED NOT NULL DEFAULT '0',
                              `AGENT` varchar(2000) DEFAULT NULL,
                              `DOMAIN` varchar(100) DEFAULT NULL,
                              `CONTINENT` varchar(100) DEFAULT NULL,
                              `COUNTRY` varchar(100) DEFAULT NULL,
                              `CITY` varchar(100) DEFAULT NULL,
                              `LAT` float DEFAULT NULL,
                              `LON` float DEFAULT NULL,
                              `HIT` date NOT NULL,
                              `COUNTER` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `refresh_tokens`
--

CREATE TABLE `refresh_tokens` (
                                  `id` int NOT NULL,
                                  `refreshToken` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                                  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                                  `rvid` int UNSIGNED DEFAULT NULL,
                                  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                  `valid` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `REMINDERS`
--

CREATE TABLE `REMINDERS` (
                             `ID` int UNSIGNED NOT NULL,
                             `RVID` int UNSIGNED DEFAULT NULL,
                             `TYPE` tinyint UNSIGNED DEFAULT NULL,
                             `DELAY` smallint UNSIGNED NOT NULL,
                             `REPETITION` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
                             `RECIPIENT` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'reviewer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `REVIEW`
--

CREATE TABLE `REVIEW` (
                          `RVID` int UNSIGNED NOT NULL,
                          `CODE` varchar(50) NOT NULL,
                          `NAME` varchar(2000) NOT NULL,
                          `STATUS` smallint UNSIGNED NOT NULL DEFAULT '0',
                          `CREATION` datetime NOT NULL,
                          `PIWIKID` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Basic journal informations';

-- --------------------------------------------------------

--
-- Table structure for table `REVIEWER_ALIAS`
--

CREATE TABLE `REVIEWER_ALIAS` (
                                  `UID` int UNSIGNED NOT NULL,
                                  `DOCID` int UNSIGNED NOT NULL,
                                  `ALIAS` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `REVIEWER_POOL`
--

CREATE TABLE `REVIEWER_POOL` (
                                 `RVID` int UNSIGNED NOT NULL,
                                 `VID` int UNSIGNED NOT NULL DEFAULT '0',
                                 `UID` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `REVIEWER_REPORT`
--

CREATE TABLE `REVIEWER_REPORT` (
                                   `ID` int UNSIGNED NOT NULL,
                                   `UID` int UNSIGNED NOT NULL,
                                   `ONBEHALF_UID` int UNSIGNED DEFAULT NULL COMMENT 'Mis à jour [!= de NULL] uniquement si l’évaluation est faite à la place de relecteur UID',
                                   `DOCID` int UNSIGNED NOT NULL,
                                   `STATUS` int UNSIGNED NOT NULL,
                                   `CREATION_DATE` datetime NOT NULL,
                                   `UPDATE_DATE` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `REVIEW_SETTING`
--

CREATE TABLE `REVIEW_SETTING` (
                                  `RVID` int UNSIGNED NOT NULL,
                                  `SETTING` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8_unicode_ci NOT NULL,
                                  `VALUE` text CHARACTER SET utf8mb3 COLLATE utf8_unicode_ci,
                                  `TIME` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Journal configurations';

-- --------------------------------------------------------

--
-- Table structure for table `SECTION`
--

CREATE TABLE `SECTION` (
                           `SID` int UNSIGNED NOT NULL,
                           `RVID` int UNSIGNED NOT NULL,
                           `POSITION` int UNSIGNED NOT NULL,
                           `titles` json DEFAULT NULL,
                           `descriptions` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `SECTION_SETTING`
--

CREATE TABLE `SECTION_SETTING` (
                                   `SID` int UNSIGNED NOT NULL,
                                   `SETTING` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8_unicode_ci NOT NULL,
                                   `VALUE` text CHARACTER SET utf8mb3 COLLATE utf8_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `STAT_TEMP`
--

CREATE TABLE `STAT_TEMP` (
                             `VISITID` int UNSIGNED NOT NULL,
                             `DOCID` int UNSIGNED NOT NULL,
                             `IP` int UNSIGNED NOT NULL,
                             `HTTP_USER_AGENT` varchar(2000) CHARACTER SET utf8mb3 COLLATE utf8_unicode_ci NOT NULL,
                             `DHIT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                             `CONSULT` enum('notice','file','oai') CHARACTER SET utf8mb3 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'notice'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci COMMENT='Statistique de consultation journalière temporaire';

-- --------------------------------------------------------

--
-- Table structure for table `USER`
--

CREATE TABLE `USER` (
                        `UID` int UNSIGNED NOT NULL,
                        `LANGUEID` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8_general_ci NOT NULL DEFAULT 'fr' COMMENT 'Account language code',
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
                        `IS_VALID` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Is account enabled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `USER_ASSIGNMENT`
--

CREATE TABLE `USER_ASSIGNMENT` (
                                   `ID` int UNSIGNED NOT NULL,
                                   `INVITATION_ID` int UNSIGNED DEFAULT NULL,
                                   `RVID` int UNSIGNED NOT NULL,
                                   `ITEMID` int UNSIGNED NOT NULL,
                                   `ITEM` varchar(50) NOT NULL DEFAULT 'paper',
                                   `UID` int UNSIGNED NOT NULL,
                                   `TMP_USER` tinyint UNSIGNED NOT NULL DEFAULT '0',
                                   `ROLEID` varchar(50) NOT NULL,
                                   `STATUS` varchar(20) NOT NULL,
                                   `WHEN` datetime NOT NULL,
                                   `DEADLINE` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `USER_INVITATION`
--

CREATE TABLE `USER_INVITATION` (
                                   `ID` int UNSIGNED NOT NULL,
                                   `AID` int UNSIGNED NOT NULL COMMENT 'Assignment ID',
                                   `STATUS` varchar(50) NOT NULL DEFAULT 'pending',
                                   `TOKEN` varchar(40) DEFAULT NULL,
                                   `SENDER_UID` int UNSIGNED DEFAULT NULL,
                                   `SENDING_DATE` datetime NOT NULL,
                                   `EXPIRATION_DATE` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `USER_INVITATION_ANSWER`
--

CREATE TABLE `USER_INVITATION_ANSWER` (
                                          `ID` int UNSIGNED NOT NULL COMMENT 'Invitation ID',
                                          `ANSWER` varchar(10) NOT NULL,
                                          `ANSWER_DATE` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `USER_INVITATION_ANSWER_DETAIL`
--

CREATE TABLE `USER_INVITATION_ANSWER_DETAIL` (
                                                 `ID` int UNSIGNED NOT NULL COMMENT 'Invitation ID',
                                                 `NAME` varchar(30) NOT NULL,
                                                 `VALUE` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `USER_MERGE`
--

CREATE TABLE `USER_MERGE` (
                              `MID` int UNSIGNED NOT NULL,
                              `TOKEN` varchar(40) DEFAULT NULL,
                              `MERGER_UID` int UNSIGNED NOT NULL COMMENT 'CASID du compte à fusionner',
                              `KEEPER_UID` int UNSIGNED NOT NULL COMMENT 'CASID du compte à conserver',
                              `DETAIL` text,
                              `DATE` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `USER_ROLES`
--

CREATE TABLE `USER_ROLES` (
                              `UID` int UNSIGNED NOT NULL,
                              `RVID` int UNSIGNED NOT NULL DEFAULT '0',
                              `ROLEID` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `USER_TMP`
--

CREATE TABLE `USER_TMP` (
                            `ID` int UNSIGNED NOT NULL,
                            `EMAIL` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
                            `FIRSTNAME` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
                            `LASTNAME` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
                            `LANG` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `VOLUME`
--

CREATE TABLE `VOLUME` (
                          `VID` int UNSIGNED NOT NULL,
                          `RVID` int UNSIGNED NOT NULL,
                          `POSITION` int UNSIGNED NOT NULL,
                          `BIB_REFERENCE` varchar(255) DEFAULT NULL COMMENT 'Volume bibliographical reference',
                          `titles` json DEFAULT NULL,
                          `descriptions` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Journal volumes';

-- --------------------------------------------------------

--
-- Table structure for table `VOLUME_METADATA`
--

CREATE TABLE `VOLUME_METADATA` (
                                   `ID` int UNSIGNED NOT NULL,
                                   `VID` int UNSIGNED NOT NULL,
                                   `POSITION` int UNSIGNED NOT NULL,
                                   `CONTENT` json DEFAULT NULL COMMENT 'Metadata decsriptions',
                                   `FILE` varchar(250) DEFAULT NULL,
                                   `titles` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `VOLUME_PAPER`
--

CREATE TABLE `VOLUME_PAPER` (
                                `ID` int UNSIGNED NOT NULL,
                                `VID` int UNSIGNED NOT NULL,
                                `DOCID` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `VOLUME_PAPER_POSITION`
--

CREATE TABLE `VOLUME_PAPER_POSITION` (
                                         `VID` int UNSIGNED NOT NULL,
                                         `PAPERID` int UNSIGNED NOT NULL,
                                         `POSITION` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `VOLUME_SETTING`
--

CREATE TABLE `VOLUME_SETTING` (
                                  `VID` int UNSIGNED NOT NULL,
                                  `SETTING` varchar(200) NOT NULL,
                                  `VALUE` text CHARACTER SET utf8mb3 COLLATE utf8_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `WEBSITE_HEADER`
--

CREATE TABLE `WEBSITE_HEADER` (
                                  `LOGOID` int UNSIGNED NOT NULL,
                                  `RVID` int UNSIGNED NOT NULL,
                                  `TYPE` enum('img','text') NOT NULL,
                                  `IMG` varchar(255) NOT NULL,
                                  `IMG_WIDTH` varchar(255) NOT NULL,
                                  `IMG_HEIGHT` varchar(255) NOT NULL,
                                  `IMG_HREF` varchar(255) NOT NULL,
                                  `IMG_ALT` varchar(255) NOT NULL,
                                  `TEXT` varchar(1000) NOT NULL,
                                  `TEXT_CLASS` varchar(255) NOT NULL,
                                  `TEXT_STYLE` varchar(255) NOT NULL,
                                  `ALIGN` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `WEBSITE_NAVIGATION`
--

CREATE TABLE `WEBSITE_NAVIGATION` (
                                      `NAVIGATIONID` int UNSIGNED NOT NULL,
                                      `SID` int UNSIGNED NOT NULL COMMENT 'RVID',
                                      `PAGEID` int UNSIGNED NOT NULL,
                                      `TYPE_PAGE` varchar(255) NOT NULL,
                                      `CONTROLLER` varchar(255) NOT NULL,
                                      `ACTION` varchar(255) NOT NULL,
                                      `LABEL` varchar(500) NOT NULL,
                                      `PARENT_PAGEID` int UNSIGNED NOT NULL,
                                      `PARAMS` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `WEBSITE_SETTINGS`
--

CREATE TABLE `WEBSITE_SETTINGS` (
                                    `SID` int UNSIGNED NOT NULL,
                                    `SETTING` varchar(50) NOT NULL,
                                    `VALUE` varchar(1000) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `WEBSITE_STYLES`
--

CREATE TABLE `WEBSITE_STYLES` (
                                  `RVID` int UNSIGNED NOT NULL,
                                  `SETTING` varchar(50) NOT NULL,
                                  `VALUE` varchar(1000) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `authors`
--
ALTER TABLE `authors`
    ADD PRIMARY KEY (`idauthors`),
  ADD KEY `paperid` (`paperid`);

--
-- Indexes for table `doi_queue`
--
ALTER TABLE `doi_queue`
    ADD PRIMARY KEY (`id_doi_queue`),
  ADD UNIQUE KEY `paperid` (`paperid`),
  ADD KEY `doi_status` (`doi_status`);

--
-- Indexes for table `doi_queue_volumes`
--
ALTER TABLE `doi_queue_volumes`
    ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_vid` (`vid`) USING BTREE,
  ADD KEY `vid` (`vid`);

--
-- Indexes for table `MAIL_LOG`
--
ALTER TABLE `MAIL_LOG`
    ADD PRIMARY KEY (`ID`),
  ADD KEY `RVID` (`RVID`),
  ADD KEY `DOCID` (`DOCID`),
  ADD KEY `WHEN` (`WHEN`);

--
-- Indexes for table `MAIL_TEMPLATE`
--
ALTER TABLE `MAIL_TEMPLATE`
    ADD PRIMARY KEY (`ID`),
  ADD KEY `KEY` (`KEY`),
  ADD KEY `RVCODE` (`RVCODE`),
  ADD KEY `RVID` (`RVID`),
  ADD KEY `PARENTID` (`PARENTID`),
  ADD KEY `POSITION` (`POSITION`);

--
-- Indexes for table `metadata_sources`
--
ALTER TABLE `metadata_sources`
    ADD PRIMARY KEY (`id`),
  ADD KEY `type` (`type`);

--
-- Indexes for table `NEWS`
--
ALTER TABLE `NEWS`
    ADD PRIMARY KEY (`NEWSID`),
  ADD KEY `RVID` (`RVID`),
  ADD KEY `ONLINE` (`ONLINE`),
  ADD KEY `DATE_POST` (`DATE_POST`);

--
-- Indexes for table `PAPERS`
--
ALTER TABLE `PAPERS`
    ADD PRIMARY KEY (`DOCID`),
  ADD KEY `FK_REPOID_idx` (`REPOID`),
  ADD KEY `FK_VID_idx` (`VID`),
  ADD KEY `FK_RVID_idx` (`RVID`),
  ADD KEY `STATUS` (`STATUS`),
  ADD KEY `PAPERID` (`PAPERID`),
  ADD KEY `SID` (`SID`),
  ADD KEY `UID` (`UID`),
  ADD KEY `SUBMISSION_DATE` (`SUBMISSION_DATE`),
  ADD KEY `PUBLICATION_DATE` (`PUBLICATION_DATE`),
  ADD KEY `FLAG` (`FLAG`),
  ADD KEY `DOI` (`DOI`);
ALTER TABLE `PAPERS` ADD FULLTEXT KEY `RECORD` (`RECORD`);

--
-- Indexes for table `paper_citations`
--
ALTER TABLE `paper_citations`
    ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `source_id_2` (`source_id`,`docid`),
  ADD KEY `docid` (`docid`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `paper_classifications`
--
ALTER TABLE `paper_classifications`
    ADD PRIMARY KEY (`id`),
  ADD KEY `paperid` (`paperid`),
  ADD KEY `type` (`type`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `PAPER_COMMENTS`
--
ALTER TABLE `PAPER_COMMENTS`
    ADD PRIMARY KEY (`PCID`),
  ADD KEY `DOCID` (`DOCID`),
  ADD KEY `TYPE` (`TYPE`),
  ADD KEY `UID` (`UID`),
  ADD KEY `DEADLINE` (`DEADLINE`),
  ADD KEY `WHEN` (`WHEN`),
  ADD KEY `PARENTID` (`PARENTID`);

--
-- Indexes for table `paper_conflicts`
--
ALTER TABLE `paper_conflicts`
    ADD PRIMARY KEY (`cid`),
  ADD UNIQUE KEY `U_PAPERID_BY` (`paper_id`,`by`) USING BTREE,
  ADD KEY `BY_UID` (`by`),
  ADD KEY `PAPERID` (`paper_id`),
  ADD KEY `answer` (`answer`);

--
-- Indexes for table `paper_datasets`
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
-- Indexes for table `paper_datasets_meta`
--
ALTER TABLE `paper_datasets_meta`
    ADD PRIMARY KEY (`id`);

--
-- Indexes for table `paper_files`
--
ALTER TABLE `paper_files`
    ADD PRIMARY KEY (`id`),
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `source` (`source`);

--
-- Indexes for table `paper_licences`
--
ALTER TABLE `paper_licences`
    ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `docid` (`docid`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `PAPER_LOG`
--
ALTER TABLE `PAPER_LOG`
    ADD PRIMARY KEY (`LOGID`),
  ADD KEY `fk_T_PAPER_MODIF_T_PAPERS_idx` (`DOCID`),
  ADD KEY `fk_T_PAPER_MODIF_T_USER_idx` (`UID`),
  ADD KEY `PAPERID` (`PAPERID`),
  ADD KEY `RVID` (`RVID`),
  ADD KEY `DATE` (`DATE`);

--
-- Indexes for table `paper_projects`
--
ALTER TABLE `paper_projects`
    ADD PRIMARY KEY (`idproject`),
  ADD UNIQUE KEY `paperid` (`paperid`),
  ADD UNIQUE KEY `paperid_src_uniq` (`paperid`,`source_id`) USING BTREE,
  ADD KEY `idx_source_id` (`source_id`);

--
-- Indexes for table `PAPER_SETTINGS`
--
ALTER TABLE `PAPER_SETTINGS`
    ADD PRIMARY KEY (`PSID`),
  ADD KEY `SETTING` (`SETTING`),
  ADD KEY `DOCID` (`DOCID`);

--
-- Indexes for table `PAPER_STAT`
--
ALTER TABLE `PAPER_STAT`
    ADD PRIMARY KEY (`DOCID`,`CONSULT`,`IP`,`HIT`),
  ADD KEY `COUNTER` (`COUNTER`),
  ADD KEY `CONSULT` (`CONSULT`);

--
-- Indexes for table `refresh_tokens`
--
ALTER TABLE `refresh_tokens`
    ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_9BACE7E16973EC66` (`refreshToken`);

--
-- Indexes for table `REMINDERS`
--
ALTER TABLE `REMINDERS`
    ADD PRIMARY KEY (`ID`),
  ADD KEY `RVID` (`RVID`),
  ADD KEY `TYPE` (`TYPE`);

--
-- Indexes for table `REVIEW`
--
ALTER TABLE `REVIEW`
    ADD PRIMARY KEY (`RVID`),
  ADD UNIQUE KEY `U_CODE` (`CODE`),
  ADD KEY `STATUS` (`STATUS`);

--
-- Indexes for table `REVIEWER_ALIAS`
--
ALTER TABLE `REVIEWER_ALIAS`
    ADD UNIQUE KEY `UNIQUE` (`UID`,`DOCID`,`ALIAS`) USING BTREE;

--
-- Indexes for table `REVIEWER_POOL`
--
ALTER TABLE `REVIEWER_POOL`
    ADD PRIMARY KEY (`RVID`,`VID`,`UID`);

--
-- Indexes for table `REVIEWER_REPORT`
--
ALTER TABLE `REVIEWER_REPORT`
    ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `UID` (`UID`,`DOCID`),
  ADD KEY `ONBEHALF_UID` (`ONBEHALF_UID`) USING BTREE;

--
-- Indexes for table `REVIEW_SETTING`
--
ALTER TABLE `REVIEW_SETTING`
    ADD PRIMARY KEY (`RVID`,`SETTING`),
  ADD KEY `FK_CONFIG_idx` (`RVID`);

--
-- Indexes for table `SECTION`
--
ALTER TABLE `SECTION`
    ADD PRIMARY KEY (`SID`),
  ADD KEY `RVID` (`RVID`),
  ADD KEY `POSITION` (`POSITION`);

--
-- Indexes for table `SECTION_SETTING`
--
ALTER TABLE `SECTION_SETTING`
    ADD PRIMARY KEY (`SID`,`SETTING`);

--
-- Indexes for table `STAT_TEMP`
--
ALTER TABLE `STAT_TEMP`
    ADD PRIMARY KEY (`VISITID`),
  ADD KEY `DOCID` (`DOCID`);

--
-- Indexes for table `USER`
--
ALTER TABLE `USER`
    ADD PRIMARY KEY (`UID`),
  ADD UNIQUE KEY `U_USERNAME` (`USERNAME`),
  ADD KEY `API_PASSWORD` (`API_PASSWORD`),
  ADD KEY `IS_VALID` (`IS_VALID`),
  ADD KEY `FIRSTNAME` (`FIRSTNAME`),
  ADD KEY `LASTNAME` (`LASTNAME`),
  ADD KEY `SCREEN_NAME` (`SCREEN_NAME`),
  ADD KEY `EMAIL` (`EMAIL`(255)),
  ADD KEY `REGISTRATION_DATE` (`REGISTRATION_DATE`);

--
-- Indexes for table `USER_ASSIGNMENT`
--
ALTER TABLE `USER_ASSIGNMENT`
    ADD PRIMARY KEY (`ID`),
  ADD KEY `FK_ITEMID_idx` (`ITEMID`),
  ADD KEY `FK_UID_idx` (`UID`),
  ADD KEY `ITEM` (`ITEM`),
  ADD KEY `ROLEID` (`ROLEID`),
  ADD KEY `RVID` (`RVID`),
  ADD KEY `STATUS` (`STATUS`),
  ADD KEY `WHEN` (`WHEN`),
  ADD KEY `TMP_USER` (`TMP_USER`),
  ADD KEY `INVITATION_ID` (`INVITATION_ID`);

--
-- Indexes for table `USER_INVITATION`
--
ALTER TABLE `USER_INVITATION`
    ADD PRIMARY KEY (`ID`),
  ADD KEY `TOKEN` (`TOKEN`),
  ADD KEY `STATUS` (`STATUS`),
  ADD KEY `SENDER_UID` (`SENDER_UID`);

--
-- Indexes for table `USER_INVITATION_ANSWER`
--
ALTER TABLE `USER_INVITATION_ANSWER`
    ADD UNIQUE KEY `U_ID` (`ID`);

--
-- Indexes for table `USER_INVITATION_ANSWER_DETAIL`
--
ALTER TABLE `USER_INVITATION_ANSWER_DETAIL`
    ADD UNIQUE KEY `U_ID_NAME` (`ID`,`NAME`);

--
-- Indexes for table `USER_MERGE`
--
ALTER TABLE `USER_MERGE`
    ADD PRIMARY KEY (`MID`);

--
-- Indexes for table `USER_ROLES`
--
ALTER TABLE `USER_ROLES`
    ADD PRIMARY KEY (`UID`,`RVID`,`ROLEID`),
  ADD KEY `RVID` (`RVID`),
  ADD KEY `ROLEID` (`ROLEID`),
  ADD KEY `UID` (`UID`);

--
-- Indexes for table `USER_TMP`
--
ALTER TABLE `USER_TMP`
    ADD PRIMARY KEY (`ID`),
  ADD KEY `EMAIL` (`EMAIL`(150));

--
-- Indexes for table `VOLUME`
--
ALTER TABLE `VOLUME`
    ADD PRIMARY KEY (`VID`),
  ADD KEY `FK_CONFID_idx` (`RVID`),
  ADD KEY `POSITION` (`POSITION`);

--
-- Indexes for table `VOLUME_METADATA`
--
ALTER TABLE `VOLUME_METADATA`
    ADD PRIMARY KEY (`ID`),
  ADD KEY `VID` (`VID`),
  ADD KEY `POSITION` (`POSITION`);

--
-- Indexes for table `VOLUME_PAPER`
--
ALTER TABLE `VOLUME_PAPER`
    ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `UNIQUE` (`VID`,`DOCID`) USING BTREE;

--
-- Indexes for table `VOLUME_PAPER_POSITION`
--
ALTER TABLE `VOLUME_PAPER_POSITION`
    ADD UNIQUE KEY `VID` (`VID`,`PAPERID`),
    ADD KEY `POSITION` (`POSITION`);

--
-- Indexes for table `VOLUME_SETTING`
--
ALTER TABLE `VOLUME_SETTING`
    ADD PRIMARY KEY (`VID`,`SETTING`),
  ADD KEY `FK_RVID0_idx` (`VID`);

--
-- Indexes for table `WEBSITE_HEADER`
--
ALTER TABLE `WEBSITE_HEADER`
    ADD PRIMARY KEY (`LOGOID`,`RVID`);

--
-- Indexes for table `WEBSITE_NAVIGATION`
--
ALTER TABLE `WEBSITE_NAVIGATION`
    ADD PRIMARY KEY (`NAVIGATIONID`),
  ADD KEY `SID` (`SID`),
  ADD KEY `TYPE_PAGE` (`TYPE_PAGE`),
  ADD KEY `PARENT_PAGEID` (`PARENT_PAGEID`);

--
-- Indexes for table `WEBSITE_SETTINGS`
--
ALTER TABLE `WEBSITE_SETTINGS`
    ADD PRIMARY KEY (`SID`,`SETTING`);

--
-- Indexes for table `WEBSITE_STYLES`
--
ALTER TABLE `WEBSITE_STYLES`
    ADD PRIMARY KEY (`RVID`,`SETTING`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `authors`
--
ALTER TABLE `authors`
    MODIFY `idauthors` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doi_queue`
--
ALTER TABLE `doi_queue`
    MODIFY `id_doi_queue` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doi_queue_volumes`
--
ALTER TABLE `doi_queue_volumes`
    MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `MAIL_LOG`
--
ALTER TABLE `MAIL_LOG`
    MODIFY `ID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `MAIL_TEMPLATE`
--
ALTER TABLE `MAIL_TEMPLATE`
    MODIFY `ID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `metadata_sources`
--
ALTER TABLE `metadata_sources`
    MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `NEWS`
--
ALTER TABLE `NEWS`
    MODIFY `NEWSID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `PAPERS`
--
ALTER TABLE `PAPERS`
    MODIFY `DOCID` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique Identifier for each submission';

--
-- AUTO_INCREMENT for table `paper_citations`
--
ALTER TABLE `paper_citations`
    MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `PAPER_COMMENTS`
--
ALTER TABLE `PAPER_COMMENTS`
    MODIFY `PCID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paper_conflicts`
--
ALTER TABLE `paper_conflicts`
    MODIFY `cid` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paper_datasets`
--
ALTER TABLE `paper_datasets`
    MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paper_datasets_meta`
--
ALTER TABLE `paper_datasets_meta`
    MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paper_files`
--
ALTER TABLE `paper_files`
    MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paper_licences`
--
ALTER TABLE `paper_licences`
    MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `PAPER_LOG`
--
ALTER TABLE `PAPER_LOG`
    MODIFY `LOGID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paper_projects`
--
ALTER TABLE `paper_projects`
    MODIFY `idproject` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `PAPER_SETTINGS`
--
ALTER TABLE `PAPER_SETTINGS`
    MODIFY `PSID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refresh_tokens`
--
ALTER TABLE `refresh_tokens`
    MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `REMINDERS`
--
ALTER TABLE `REMINDERS`
    MODIFY `ID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `REVIEW`
--
ALTER TABLE `REVIEW`
    MODIFY `RVID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `REVIEWER_REPORT`
--
ALTER TABLE `REVIEWER_REPORT`
    MODIFY `ID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `SECTION`
--
ALTER TABLE `SECTION`
    MODIFY `SID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `STAT_TEMP`
--
ALTER TABLE `STAT_TEMP`
    MODIFY `VISITID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `USER`
--
ALTER TABLE `USER`
    MODIFY `UID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `USER_ASSIGNMENT`
--
ALTER TABLE `USER_ASSIGNMENT`
    MODIFY `ID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `USER_INVITATION`
--
ALTER TABLE `USER_INVITATION`
    MODIFY `ID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `USER_MERGE`
--
ALTER TABLE `USER_MERGE`
    MODIFY `MID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `USER_TMP`
--
ALTER TABLE `USER_TMP`
    MODIFY `ID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `VOLUME`
--
ALTER TABLE `VOLUME`
    MODIFY `VID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `VOLUME_METADATA`
--
ALTER TABLE `VOLUME_METADATA`
    MODIFY `ID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `VOLUME_PAPER`
--
ALTER TABLE `VOLUME_PAPER`
    MODIFY `ID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `WEBSITE_HEADER`
--
ALTER TABLE `WEBSITE_HEADER`
    MODIFY `LOGOID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `WEBSITE_NAVIGATION`
--
ALTER TABLE `WEBSITE_NAVIGATION`
    MODIFY `NAVIGATIONID` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `doi_queue_volumes`
--
ALTER TABLE `doi_queue_volumes`
    ADD CONSTRAINT `doi_queue_volumes_ibfk_1` FOREIGN KEY (`vid`) REFERENCES `VOLUME` (`VID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `paper_citations`
--
ALTER TABLE `paper_citations`
    ADD CONSTRAINT `paper_citations_ibfk_1` FOREIGN KEY (`docid`) REFERENCES `PAPERS` (`DOCID`),
  ADD CONSTRAINT `paper_citations_ibfk_2` FOREIGN KEY (`source_id`) REFERENCES `metadata_sources` (`id`);

--
-- Constraints for table `paper_datasets`
--
ALTER TABLE `paper_datasets`
    ADD CONSTRAINT `deleteAssocMeta` FOREIGN KEY (`id_paper_datasets_meta`) REFERENCES `paper_datasets_meta` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `paper_projects`
--
ALTER TABLE `paper_projects`
    ADD CONSTRAINT `paper_projects_ibfk_1` FOREIGN KEY (`paperid`) REFERENCES `PAPERS` (`PAPERID`),
  ADD CONSTRAINT `paper_projects_ibfk_2` FOREIGN KEY (`source_id`) REFERENCES `metadata_sources` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;