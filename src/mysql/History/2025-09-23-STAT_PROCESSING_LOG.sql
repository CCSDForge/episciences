SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `episciences`
--

-- --------------------------------------------------------

--
-- Table structure for table `STAT_PROCESSING_LOG`
--

CREATE TABLE `STAT_PROCESSING_LOG`
(
    `ID`                int UNSIGNED                                                                        NOT NULL,
    `JOURNAL_CODE`      varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci                        NOT NULL,
    `PROCESSED_DATE`    date                                                                                NOT NULL,
    `PROCESSED_AT`      timestamp                                                                           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `FILE_PATH`         varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci                       NOT NULL,
    `RECORDS_PROCESSED` int UNSIGNED                                                                        NOT NULL DEFAULT '0',
    `STATUS`            enum ('success','error','partial') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success'
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT ='Tracks processed statistics log files to prevent duplicates';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `STAT_PROCESSING_LOG`
--
ALTER TABLE `STAT_PROCESSING_LOG`
    ADD PRIMARY KEY (`ID`),
    ADD UNIQUE KEY `unique_journal_date` (`JOURNAL_CODE`, `PROCESSED_DATE`),
    ADD KEY `idx_journal_code` (`JOURNAL_CODE`),
    ADD KEY `idx_processed_date` (`PROCESSED_DATE`),
    ADD KEY `idx_processed_at` (`PROCESSED_AT`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `STAT_PROCESSING_LOG`
--
ALTER TABLE `STAT_PROCESSING_LOG`
    MODIFY `ID` int UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;
