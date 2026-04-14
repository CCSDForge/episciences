-- Statistics Processing Log Table
-- Tracks which log files have been processed to prevent duplicates
-- Created: 2025-08-24

CREATE TABLE `STAT_PROCESSING_LOG` (
  `ID` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `JOURNAL_CODE` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `PROCESSED_DATE` date NOT NULL,
  `PROCESSED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FILE_PATH` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `RECORDS_PROCESSED` int UNSIGNED NOT NULL DEFAULT 0,
  `STATUS` enum('success','error','partial') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `unique_journal_date` (`JOURNAL_CODE`, `PROCESSED_DATE`),
  KEY `idx_journal_code` (`JOURNAL_CODE`),
  KEY `idx_processed_date` (`PROCESSED_DATE`),
  KEY `idx_processed_at` (`PROCESSED_AT`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks processed statistics log files to prevent duplicates';