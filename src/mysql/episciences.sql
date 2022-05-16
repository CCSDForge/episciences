-- Generation Time: Nov 03, 2021 at 07:30 PM
-- Server version: 5.6.51-log
-- PHP Version: 7.2.24-0ubuntu0.18.04.10

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
-- Table structure for table `doi_queue`
--

CREATE TABLE `doi_queue` (
  `id_doi_queue` int(11) UNSIGNED NOT NULL,
  `paperid` int(11) UNSIGNED NOT NULL,
  `doi_status` enum('assigned','requested','public','') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assigned',
  `date_init` datetime NOT NULL,
  `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MAIL_LOG`
--

CREATE TABLE `MAIL_LOG` (
  `ID` int(11) UNSIGNED NOT NULL,
  `RVID` int(11) UNSIGNED NOT NULL,
  `DOCID` int(11) UNSIGNED DEFAULT NULL,
  `FROM` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `REPLYTO` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TO` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `CC` text COLLATE utf8mb4_unicode_ci,
  `BCC` text COLLATE utf8mb4_unicode_ci,
  `SUBJECT` varchar(250) CHARACTER SET utf8mb4 DEFAULT NULL,
  `CONTENT` mediumtext COLLATE utf8mb4_unicode_ci,
  `FILES` mediumtext COLLATE utf8mb4_unicode_ci,
  `WHEN` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MAIL_TEMPLATE`
--

CREATE TABLE `MAIL_TEMPLATE` (
  `ID` int(11) UNSIGNED NOT NULL,
  `PARENTID` int(11) UNSIGNED DEFAULT NULL,
  `RVID` int(11) UNSIGNED DEFAULT NULL,
  `RVCODE` varchar(25) CHARACTER SET utf8 DEFAULT NULL,
  `KEY` varchar(255) CHARACTER SET utf8 NOT NULL,
  `TYPE` varchar(255) CHARACTER SET utf8 NOT NULL,
  `POSITION` int(11) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `NEWS`
--

CREATE TABLE `NEWS` (
  `NEWSID` int(11) UNSIGNED NOT NULL,
  `RVID` int(11) UNSIGNED NOT NULL,
  `UID` int(11) UNSIGNED NOT NULL,
  `LINK` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ONLINE` tinyint(4) UNSIGNED NOT NULL,
  `DATE_POST` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `PAPERS`
--

CREATE TABLE `PAPERS` (
  `DOCID` int(11) UNSIGNED NOT NULL,
  `PAPERID` int(11) UNSIGNED DEFAULT NULL,
  `DOI` varchar(250) CHARACTER SET utf8 DEFAULT NULL,
  `RVID` int(11) UNSIGNED NOT NULL,
  `VID` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `SID` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `UID` int(11) UNSIGNED NOT NULL,
  `STATUS` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `IDENTIFIER` varchar(500) CHARACTER SET utf8 NOT NULL,
  `VERSION` float UNSIGNED NOT NULL DEFAULT '1',
  `REPOID` int(11) UNSIGNED NOT NULL,
  `RECORD` text CHARACTER SET utf8 NOT NULL,
  `CONCEPT_IDENTIFIER` varchar(500) CHARACTER SET utf8 DEFAULT NULL COMMENT 'This identifier represents all versions',
  `FLAG` enum('submitted','imported') CHARACTER SET utf8 NOT NULL DEFAULT 'submitted',
  `WHEN` datetime DEFAULT NULL,
  `SUBMISSION_DATE` datetime NOT NULL,
  `MODIFICATION_DATE` datetime DEFAULT NULL,
  `PUBLICATION_DATE` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal papers';

-- --------------------------------------------------------

--
-- Table structure for table `PAPER_COMMENTS`
--

CREATE TABLE `PAPER_COMMENTS` (
  `PCID` int(11) UNSIGNED NOT NULL,
  `PARENTID` int(11) UNSIGNED DEFAULT NULL,
  `TYPE` int(11) UNSIGNED NOT NULL,
  `DOCID` int(11) UNSIGNED NOT NULL,
  `UID` int(11) UNSIGNED NOT NULL,
  `MESSAGE` mediumtext COLLATE utf8mb4_unicode_ci,
  `FILE` text COLLATE utf8mb4_unicode_ci,
  `DEADLINE` date DEFAULT NULL,
  `OPTIONS` text CHARACTER SET utf8mb4,
  `WHEN` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Suivi des demandes de modification sur un papier';

-- --------------------------------------------------------

--
-- Table structure for table `paper_conflicts`
--

CREATE TABLE `paper_conflicts` (
  `cid` int(10) UNSIGNED NOT NULL,
  `paper_id` int(10) UNSIGNED NOT NULL,
  `by` int(10) UNSIGNED NOT NULL COMMENT 'uid',
  `answer` enum('yes','no','later') CHARACTER SET utf8 NOT NULL,
  `message` text CHARACTER SET utf8mb4,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `valid` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='conflicts handling';

-- --------------------------------------------------------

--
-- Table structure for table `paper_datasets`
--

CREATE TABLE `paper_datasets` (
  `id` int(11) NOT NULL,
  `doc_id` int(11) NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(750) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` int(11) NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `paper_files`
--

CREATE TABLE `paper_files` (
  `id` int(10) UNSIGNED NOT NULL,
  `doc_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `checksum` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `checksum_type` char(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `self_link` varchar(750) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED NOT NULL,
  `file_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `time_modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `PAPER_LOG`
--

CREATE TABLE `PAPER_LOG` (
  `LOGID` int(11) UNSIGNED NOT NULL,
  `PAPERID` int(11) UNSIGNED NOT NULL,
  `DOCID` int(11) UNSIGNED NOT NULL,
  `UID` int(11) UNSIGNED NOT NULL,
  `RVID` int(11) UNSIGNED NOT NULL,
  `ACTION` varchar(50) CHARACTER SET utf8mb4 NOT NULL,
  `DETAIL` text CHARACTER SET utf8mb4,
  `FILE` varchar(150) CHARACTER SET utf8mb4 DEFAULT NULL,
  `DATE` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Life of papers';

-- --------------------------------------------------------

--
-- Table structure for table `PAPER_RATING_GRID`
--

CREATE TABLE `PAPER_RATING_GRID` (
  `DOCID` int(11) NOT NULL,
  `RGID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `PAPER_SETTINGS`
--

CREATE TABLE `PAPER_SETTINGS` (
  `PSID` int(11) UNSIGNED NOT NULL,
  `DOCID` int(11) UNSIGNED NOT NULL,
  `SETTING` varchar(100) CHARACTER SET utf8 NOT NULL,
  `VALUE` varchar(250) CHARACTER SET utf8 DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `PAPER_STAT`
--

CREATE TABLE `PAPER_STAT` (
  `DOCID` int(10) UNSIGNED NOT NULL,
  `CONSULT` enum('notice','file','oai','api') CHARACTER SET utf8 NOT NULL DEFAULT 'notice',
  `IP` int(10) UNSIGNED NOT NULL,
  `ROBOT` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `AGENT` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `DOMAIN` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CONTINENT` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  `COUNTRY` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  `CITY` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  `LAT` float DEFAULT NULL,
  `LON` float DEFAULT NULL,
  `HIT` date NOT NULL,
  `COUNTER` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `REMINDERS`
--

CREATE TABLE `REMINDERS` (
  `ID` int(11) UNSIGNED NOT NULL,
  `RVID` int(11) UNSIGNED NOT NULL,
  `TYPE` tinyint(4) UNSIGNED NOT NULL,
  `RECIPIENT` varchar(25) CHARACTER SET utf8mb4 NOT NULL DEFAULT 'reviewer',
  `DELAY` smallint(6) UNSIGNED NOT NULL,
  `REPETITION` varchar(20) CHARACTER SET utf8mb4 DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `REVIEW`
--

CREATE TABLE `REVIEW` (
  `RVID` int(11) UNSIGNED NOT NULL,
  `CODE` varchar(50) CHARACTER SET utf8 NOT NULL,
  `NAME` varchar(2000) CHARACTER SET utf8 NOT NULL,
  `STATUS` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `CREATION` datetime NOT NULL,
  `PIWIKID` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Basic journal informations';

-- --------------------------------------------------------

--
-- Table structure for table `REVIEWER_ALIAS`
--

CREATE TABLE `REVIEWER_ALIAS` (
  `UID` int(11) UNSIGNED NOT NULL,
  `DOCID` int(11) UNSIGNED NOT NULL,
  `ALIAS` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `REVIEWER_POOL`
--

CREATE TABLE `REVIEWER_POOL` (
  `RVID` int(11) UNSIGNED NOT NULL,
  `VID` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `UID` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `REVIEWER_REPORT`
--

CREATE TABLE `REVIEWER_REPORT` (
  `ID` int(11) UNSIGNED NOT NULL,
  `UID` int(11) UNSIGNED NOT NULL,
  `ONBEHALF_UID` int(11) UNSIGNED DEFAULT NULL COMMENT 'Mis à jour [!= de NULL] uniquement si l’évaluation est faite à la place de relecteur UID',
  `DOCID` int(11) UNSIGNED NOT NULL,
  `STATUS` int(11) UNSIGNED NOT NULL,
  `CREATION_DATE` datetime NOT NULL,
  `UPDATE_DATE` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `REVIEW_SETTING`
--

CREATE TABLE `REVIEW_SETTING` (
  `RVID` int(11) UNSIGNED NOT NULL,
  `SETTING` varchar(200) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `VALUE` text CHARACTER SET utf8 COLLATE utf8_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal configurations';

-- --------------------------------------------------------

--
-- Table structure for table `SECTION`
--

CREATE TABLE `SECTION` (
  `SID` int(11) UNSIGNED NOT NULL,
  `RVID` int(11) UNSIGNED NOT NULL,
  `POSITION` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `SECTION_SETTING`
--

CREATE TABLE `SECTION_SETTING` (
  `SID` int(11) UNSIGNED NOT NULL,
  `SETTING` varchar(200) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `VALUE` text CHARACTER SET utf8 COLLATE utf8_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `STAT_TEMP`
--

CREATE TABLE `STAT_TEMP` (
  `VISITID` int(10) UNSIGNED NOT NULL,
  `DOCID` int(10) UNSIGNED NOT NULL,
  `IP` int(10) UNSIGNED NOT NULL,
  `HTTP_USER_AGENT` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `DHIT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `CONSULT` enum('notice','file','oai') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'notice'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Statistique de consultation journalière temporaire';

-- --------------------------------------------------------

--
-- Table structure for table `USER`
--

CREATE TABLE `USER` (
  `UID` int(11) UNSIGNED NOT NULL,
  `LANGUEID` varchar(2) CHARACTER SET utf8 NOT NULL DEFAULT 'fr',
  `SCREEN_NAME` varchar(250) CHARACTER SET utf8 NOT NULL,
  `USERNAME` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `API_PASSWORD` varchar(255) CHARACTER SET utf8 NOT NULL,
  `EMAIL` varchar(320) CHARACTER SET utf8 NOT NULL,
  `CIV` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `LASTNAME` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `FIRSTNAME` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MIDDLENAME` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `REGISTRATION_DATE` datetime DEFAULT NULL,
  `MODIFICATION_DATE` datetime DEFAULT NULL,
  `IS_VALID` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `USER_ASSIGNMENT`
--

CREATE TABLE `USER_ASSIGNMENT` (
  `ID` int(11) UNSIGNED NOT NULL,
  `INVITATION_ID` int(11) UNSIGNED DEFAULT NULL,
  `RVID` int(11) UNSIGNED NOT NULL,
  `ITEMID` int(11) UNSIGNED NOT NULL,
  `ITEM` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT 'paper',
  `UID` int(11) UNSIGNED NOT NULL,
  `TMP_USER` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `ROLEID` varchar(50) CHARACTER SET utf8 NOT NULL,
  `STATUS` varchar(20) CHARACTER SET utf8 NOT NULL,
  `WHEN` datetime NOT NULL,
  `DEADLINE` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `USER_INVITATION`
--

CREATE TABLE `USER_INVITATION` (
  `ID` int(11) UNSIGNED NOT NULL,
  `AID` int(10) UNSIGNED NOT NULL COMMENT 'Assignment ID',
  `STATUS` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT 'pending',
  `SENDER_UID` int(11) UNSIGNED DEFAULT NULL,
  `SENDING_DATE` datetime NOT NULL,
  `EXPIRATION_DATE` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `USER_INVITATION_ANSWER`
--

CREATE TABLE `USER_INVITATION_ANSWER` (
  `ID` int(11) UNSIGNED NOT NULL COMMENT 'Invitation ID',
  `ANSWER` varchar(10) CHARACTER SET utf8 NOT NULL,
  `ANSWER_DATE` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `USER_INVITATION_ANSWER_DETAIL`
--

CREATE TABLE `USER_INVITATION_ANSWER_DETAIL` (
  `ID` int(11) UNSIGNED NOT NULL COMMENT 'Invitation ID',
  `NAME` varchar(30) CHARACTER SET utf8 NOT NULL,
  `VALUE` varchar(500) CHARACTER SET utf8 DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `USER_MERGE`
--

CREATE TABLE `USER_MERGE` (
  `MID` int(11) UNSIGNED NOT NULL,
  `TOKEN` varchar(40) CHARACTER SET utf8 DEFAULT NULL,
  `MERGER_UID` int(11) UNSIGNED NOT NULL COMMENT 'CASID du compte à fusionner',
  `KEEPER_UID` int(11) UNSIGNED NOT NULL COMMENT 'CASID du compte à conserver',
  `DETAIL` text CHARACTER SET utf8,
  `DATE` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `USER_ROLES`
--

CREATE TABLE `USER_ROLES` (
  `UID` int(11) UNSIGNED NOT NULL,
  `RVID` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `ROLEID` varchar(20) CHARACTER SET utf8 NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `USER_TMP`
--

CREATE TABLE `USER_TMP` (
  `ID` int(11) UNSIGNED NOT NULL,
  `EMAIL` varchar(250) CHARACTER SET utf8mb4 DEFAULT NULL,
  `FIRSTNAME` varchar(100) CHARACTER SET utf8mb4 DEFAULT NULL,
  `LASTNAME` varchar(100) CHARACTER SET utf8mb4 DEFAULT NULL,
  `LANG` varchar(3) CHARACTER SET utf8mb4 DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `VOLUME`
--

CREATE TABLE `VOLUME` (
  `VID` int(11) UNSIGNED NOT NULL,
  `RVID` int(11) UNSIGNED NOT NULL,
  `POSITION` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `BIB_REFERENCE` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL COMMENT 'Volume''s bibliographical reference'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal volumes';

-- --------------------------------------------------------

--
-- Table structure for table `VOLUME_METADATA`
--

CREATE TABLE `VOLUME_METADATA` (
  `ID` int(11) UNSIGNED NOT NULL,
  `VID` int(11) UNSIGNED NOT NULL,
  `POSITION` int(2) UNSIGNED NOT NULL DEFAULT '0',
  `CONTENT` tinyint(1) UNSIGNED NOT NULL,
  `FILE` varchar(250) CHARACTER SET utf8mb4 DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `VOLUME_PAPER`
--

CREATE TABLE `VOLUME_PAPER` (
  `ID` int(11) UNSIGNED NOT NULL,
  `VID` int(11) UNSIGNED NOT NULL,
  `DOCID` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `VOLUME_PAPER_POSITION`
--

CREATE TABLE `VOLUME_PAPER_POSITION` (
  `VID` int(11) UNSIGNED NOT NULL,
  `PAPERID` int(11) UNSIGNED NOT NULL,
  `POSITION` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `VOLUME_PAPER_POSITION_CLONE`
--

CREATE TABLE `VOLUME_PAPER_POSITION_CLONE` (
  `VID` int(11) UNSIGNED NOT NULL,
  `DOCID` int(11) UNSIGNED NOT NULL,
  `POSITION` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `VOLUME_SETTING`
--

CREATE TABLE `VOLUME_SETTING` (
  `VID` int(11) UNSIGNED NOT NULL,
  `SETTING` varchar(200) CHARACTER SET utf8 NOT NULL,
  `VALUE` text CHARACTER SET utf8 COLLATE utf8_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal configurations';

-- --------------------------------------------------------

--
-- Table structure for table `WEBSITE_HEADER`
--

CREATE TABLE `WEBSITE_HEADER` (
  `LOGOID` int(11) UNSIGNED NOT NULL,
  `RVID` int(11) UNSIGNED NOT NULL,
  `TYPE` enum('img','text') CHARACTER SET utf8 NOT NULL,
  `IMG` varchar(255) CHARACTER SET utf8 NOT NULL,
  `IMG_WIDTH` varchar(255) CHARACTER SET utf8 NOT NULL,
  `IMG_HEIGHT` varchar(255) CHARACTER SET utf8 NOT NULL,
  `IMG_HREF` varchar(255) CHARACTER SET utf8 NOT NULL,
  `IMG_ALT` varchar(255) CHARACTER SET utf8 NOT NULL,
  `TEXT` varchar(1000) CHARACTER SET utf8 NOT NULL,
  `TEXT_CLASS` varchar(255) CHARACTER SET utf8 NOT NULL,
  `TEXT_STYLE` varchar(255) CHARACTER SET utf8 NOT NULL,
  `ALIGN` varchar(10) CHARACTER SET utf8 NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `WEBSITE_NAVIGATION`
--

CREATE TABLE `WEBSITE_NAVIGATION` (
  `NAVIGATIONID` int(11) UNSIGNED NOT NULL,
  `SID` int(11) UNSIGNED NOT NULL,
  `PAGEID` int(11) UNSIGNED NOT NULL,
  `TYPE_PAGE` varchar(255) CHARACTER SET utf8 NOT NULL,
  `CONTROLLER` varchar(255) CHARACTER SET utf8 NOT NULL,
  `ACTION` varchar(255) CHARACTER SET utf8 NOT NULL,
  `LABEL` varchar(500) CHARACTER SET utf8 NOT NULL,
  `PARENT_PAGEID` int(11) UNSIGNED NOT NULL,
  `PARAMS` text CHARACTER SET utf8 NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `WEBSITE_SETTINGS`
--

CREATE TABLE `WEBSITE_SETTINGS` (
  `SID` int(11) UNSIGNED NOT NULL,
  `SETTING` varchar(50) CHARACTER SET utf8 NOT NULL,
  `VALUE` varchar(1000) CHARACTER SET utf8 NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `WEBSITE_STYLES`
--

CREATE TABLE `WEBSITE_STYLES` (
  `RVID` int(11) UNSIGNED NOT NULL,
  `SETTING` varchar(50) CHARACTER SET utf8 NOT NULL,
  `VALUE` varchar(1000) CHARACTER SET utf8 NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `metadata_sources`
--

CREATE TABLE `metadata_sources` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('repository','metadataRepository','user') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Unloading the table data`metadata_sources`
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
(12, 'Episciences User', 'user');

--
-- Indexes for dumped tables

--
-- Indexes for table `doi_queue`
--
ALTER TABLE `doi_queue`
  ADD PRIMARY KEY (`id_doi_queue`),
  ADD UNIQUE KEY `paperid` (`paperid`),
  ADD KEY `doi_status` (`doi_status`);

--
-- Indexes for table `MAIL_LOG`
--
ALTER TABLE `MAIL_LOG`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `IDX_RVID` (`RVID`),
  ADD KEY `DOCID` (`DOCID`);

--
-- Indexes for table `MAIL_TEMPLATE`
--
ALTER TABLE `MAIL_TEMPLATE`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `IDX_RVID` (`RVID`),
  ADD KEY `KEY` (`KEY`),
  ADD KEY `RVCODE` (`RVCODE`);

--
-- Indexes for table `NEWS`
--
ALTER TABLE `NEWS`
  ADD PRIMARY KEY (`NEWSID`);

--
-- Indexes for table `PAPERS`
--
ALTER TABLE `PAPERS`
  ADD PRIMARY KEY (`DOCID`),
  ADD KEY `RVID` (`RVID`),
  ADD KEY `VID` (`VID`),
  ADD KEY `SID` (`SID`),
  ADD KEY `UID` (`UID`),
  ADD KEY `STATUS` (`STATUS`),
  ADD KEY `PAPERID` (`PAPERID`);
ALTER TABLE `PAPERS` ADD FULLTEXT KEY `RECORD` (`RECORD`);
ALTER TABLE `PAPERS` ADD FULLTEXT KEY `RECORD_2` (`RECORD`);

--
-- Indexes for table `PAPER_COMMENTS`
--
ALTER TABLE `PAPER_COMMENTS`
  ADD PRIMARY KEY (`PCID`),
  ADD KEY `DOCID` (`DOCID`),
  ADD KEY `TYPE` (`TYPE`),
  ADD KEY `UID` (`UID`),
  ADD KEY `DEADLINE` (`DEADLINE`),
  ADD KEY `WHEN` (`WHEN`);

--
-- Indexes for table `paper_conflicts`
--
ALTER TABLE `paper_conflicts`
  ADD PRIMARY KEY (`cid`),
  ADD UNIQUE KEY `U_PAPERID_BY` (`paper_id`,`by`) USING BTREE,
  ADD KEY `BY_UID` (`by`),
  ADD KEY `PAPERID` (`paper_id`);

--
-- Indexes for table `paper_datasets`
--
ALTER TABLE `paper_datasets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique` (`doc_id`,`code`(15),`name`(35),`value`(47),`source_id`),
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `source_id` (`source_id`),
  ADD KEY `code` (`code`(15)),
  ADD KEY `name` (`name`(35));

--
-- Indexes for table `paper_files`
--
ALTER TABLE `paper_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doc_id` (`doc_id`);

--
-- Indexes for table `PAPER_LOG`
--
ALTER TABLE `PAPER_LOG`
  ADD PRIMARY KEY (`LOGID`),
  ADD KEY `fk_T_PAPER_MODIF_T_PAPERS_idx` (`DOCID`),
  ADD KEY `fk_T_PAPER_MODIF_T_USER_idx` (`UID`),
  ADD KEY `PAPERID` (`PAPERID`);

--
-- Indexes for table `PAPER_SETTINGS`
--
ALTER TABLE `PAPER_SETTINGS`
  ADD PRIMARY KEY (`PSID`),
  ADD KEY `DOCID` (`DOCID`),
  ADD KEY `SETTING` (`SETTING`);

--
-- Indexes for table `PAPER_STAT`
--
ALTER TABLE `PAPER_STAT`
  ADD PRIMARY KEY (`DOCID`,`CONSULT`,`IP`,`HIT`);

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
  ADD UNIQUE KEY `U_CODE` (`CODE`);

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
  ADD KEY `RVID` (`RVID`);

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
  ADD UNIQUE KEY `USERNAME` (`USERNAME`),
  ADD KEY `LASTNAME` (`LASTNAME`),
  ADD KEY `API_PASSWORD` (`API_PASSWORD`),
  ADD KEY `EMAIL` (`EMAIL`(255)),
  ADD KEY `SCREEN_NAME` (`SCREEN_NAME`);

--
-- Indexes for table `USER_ASSIGNMENT`
--
ALTER TABLE `USER_ASSIGNMENT`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `ITEMID` (`ITEMID`),
  ADD KEY `ROLEID` (`ROLEID`),
  ADD KEY `UID` (`UID`),
  ADD KEY `ITEM` (`ITEM`),
  ADD KEY `INDEX_SET` (`ITEM`,`ITEMID`,`ROLEID`,`UID`,`STATUS`),
  ADD KEY `STATUS` (`STATUS`),
  ADD KEY `RVID` (`RVID`),
  ADD KEY `WHEN` (`WHEN`),
  ADD KEY `INVITATION_ID` (`INVITATION_ID`);

--
-- Indexes for table `USER_INVITATION`
--
ALTER TABLE `USER_INVITATION`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `USER_INVITATION_ANSWER`
--
ALTER TABLE `USER_INVITATION_ANSWER`
  ADD UNIQUE KEY `U_ID` (`ID`) USING BTREE;

--
-- Indexes for table `USER_INVITATION_ANSWER_DETAIL`
--
ALTER TABLE `USER_INVITATION_ANSWER_DETAIL`
  ADD UNIQUE KEY `U_ID_NAME` (`ID`,`NAME`) USING BTREE;

--
-- Indexes for table `USER_MERGE`
--
ALTER TABLE `USER_MERGE`
  ADD PRIMARY KEY (`MID`);

--
-- Indexes for table `USER_ROLES`
--
ALTER TABLE `USER_ROLES`
  ADD PRIMARY KEY (`UID`,`RVID`,`ROLEID`);

--
-- Indexes for table `USER_TMP`
--
ALTER TABLE `USER_TMP`
  ADD PRIMARY KEY (`ID`);

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
  ADD KEY `VID` (`VID`);

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
  ADD UNIQUE KEY `VID` (`VID`,`PAPERID`);

--
-- Indexes for table `VOLUME_PAPER_POSITION_CLONE`
--
ALTER TABLE `VOLUME_PAPER_POSITION_CLONE`
  ADD UNIQUE KEY `VID` (`VID`,`DOCID`);

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
  ADD KEY `TYPE_PAGE` (`TYPE_PAGE`);

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

ALTER TABLE `metadata_sources`
    ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `doi_queue`
--
ALTER TABLE `doi_queue`
  MODIFY `id_doi_queue` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `MAIL_LOG`
--
ALTER TABLE `MAIL_LOG`
  MODIFY `ID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `MAIL_TEMPLATE`
--
ALTER TABLE `MAIL_TEMPLATE`
  MODIFY `ID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `NEWS`
--
ALTER TABLE `NEWS`
  MODIFY `NEWSID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `PAPERS`
--
ALTER TABLE `PAPERS`
  MODIFY `DOCID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `PAPER_COMMENTS`
--
ALTER TABLE `PAPER_COMMENTS`
  MODIFY `PCID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paper_conflicts`
--
ALTER TABLE `paper_conflicts`
  MODIFY `cid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paper_files`
--
ALTER TABLE `paper_files`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `PAPER_LOG`
--
ALTER TABLE `PAPER_LOG`
  MODIFY `LOGID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `PAPER_SETTINGS`
--
ALTER TABLE `PAPER_SETTINGS`
  MODIFY `PSID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `REMINDERS`
--
ALTER TABLE `REMINDERS`
  MODIFY `ID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `REVIEW`
--
ALTER TABLE `REVIEW`
  MODIFY `RVID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `REVIEWER_REPORT`
--
ALTER TABLE `REVIEWER_REPORT`
  MODIFY `ID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `SECTION`
--
ALTER TABLE `SECTION`
  MODIFY `SID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `STAT_TEMP`
--
ALTER TABLE `STAT_TEMP`
  MODIFY `VISITID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `USER`
--
ALTER TABLE `USER`
  MODIFY `UID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `USER_ASSIGNMENT`
--
ALTER TABLE `USER_ASSIGNMENT`
  MODIFY `ID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `USER_INVITATION`
--
ALTER TABLE `USER_INVITATION`
  MODIFY `ID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `USER_MERGE`
--
ALTER TABLE `USER_MERGE`
  MODIFY `MID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `USER_TMP`
--
ALTER TABLE `USER_TMP`
  MODIFY `ID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `VOLUME`
--
ALTER TABLE `VOLUME`
  MODIFY `VID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `VOLUME_METADATA`
--
ALTER TABLE `VOLUME_METADATA`
  MODIFY `ID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `VOLUME_PAPER`
--
ALTER TABLE `VOLUME_PAPER`
  MODIFY `ID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `WEBSITE_HEADER`
--
ALTER TABLE `WEBSITE_HEADER`
  MODIFY `LOGOID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `WEBSITE_NAVIGATION`
--
ALTER TABLE `WEBSITE_NAVIGATION`
  MODIFY `NAVIGATIONID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `metadata_sources`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;