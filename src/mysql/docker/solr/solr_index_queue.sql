-- Generation Time: Jun 14, 2024 at 10:12 AM
-- Server version: 8.0.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `solr_index`
--

-- --------------------------------------------------------

--
-- Table structure for table `INDEX_QUEUE`
--

CREATE TABLE `INDEX_QUEUE` (
                               `ID` int UNSIGNED NOT NULL,
                               `DOCID` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                               `UPDATED` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                               `APPLICATION` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Application requesting indexing',
                               `ORIGIN` set('UPDATE','DELETE','') CHARACTER SET utf8mb3 COLLATE utf8_general_ci NOT NULL COMMENT 'Type of indexing request',
                               `CORE` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Solr Collection',
                               `PRIORITY` tinyint UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Indexing priority',
                               `PID` int UNSIGNED NOT NULL DEFAULT '0',
                               `HOSTNAME` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                               `STATUS` set('locked','error','ok','') CHARACTER SET utf8mb3 COLLATE utf8_general_ci NOT NULL DEFAULT 'ok' COMMENT 'Request status',
                               `MESSAGE` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `INDEX_QUEUE`
--
ALTER TABLE `INDEX_QUEUE`
    ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `DOCID` (`DOCID`,`ORIGIN`,`CORE`),
  ADD KEY `STATUS` (`STATUS`),
  ADD KEY `PRIORITY` (`PRIORITY`),
  ADD KEY `ORIGIN` (`ORIGIN`),
  ADD KEY `PID` (`PID`),
  ADD KEY `HOSTNAME` (`HOSTNAME`);
ALTER TABLE `INDEX_QUEUE` ADD FULLTEXT KEY `CORE` (`CORE`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `INDEX_QUEUE`
--
ALTER TABLE `INDEX_QUEUE`
    MODIFY `ID` int UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;
