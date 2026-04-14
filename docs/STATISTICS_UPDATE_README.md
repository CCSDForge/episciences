# UpdateStatistics.php Enhancement

## Overview
The UpdateStatistics.php script has been enhanced with new features for processing Apache log files in date ranges, handling compressed files, and preventing duplicate processing.

## New Features

### 1. Enhanced Date Processing Options
- **Single Date**: `--date YYYY-MM-DD` (existing functionality)
- **Date Range**: `--start-date YYYY-MM-DD --end-date YYYY-MM-DD`
- **Full Month**: `--month YYYY-MM`
- **Force Reprocessing**: `--force` (reprocess already processed dates)

### 2. Compressed File Support
- Automatically handles both `.access_log` and `.access_log.gz` files
- Uses PHP's gzopen/gzgets for transparent decompression
- No re-compression needed (files left as-is after processing)

### 3. Duplicate Prevention
- New `STAT_PROCESSING_LOG` InnoDB table tracks processed dates per journal
- Prevents reprocessing unless `--force` flag is used
- Tracks processing status and record counts

### 4. Security Enhancements
- Input sanitization for document IDs, IP addresses, and user agents
- Prepared statements for all database operations
- Control character removal from user agent strings
- Document ID validation (1-9999999 range)

## Usage Examples

### Makefile Commands (Recommended)
```bash
# Process yesterday's logs for journal 'mbj'
make update-statistics rvcode=mbj

# Process specific date
make update-statistics rvcode=mbj date=2023-01-15

# Process entire month
make update-statistics rvcode=mbj month=2023-01

# Process date range
make update-statistics rvcode=mbj start-date=2023-01-01 end-date=2023-01-07

# Force reprocess (ignore duplicate prevention)
make update-statistics rvcode=mbj date=2023-01-15 force=1
```

### Direct PHP Commands
```bash
# Single date
php scripts/UpdateStatistics.php update:statistics --rvcode mbj --date 2023-01-15

# Date range
php scripts/UpdateStatistics.php update:statistics --rvcode mbj --start-date 2023-01-01 --end-date 2023-01-07

# Full month
php scripts/UpdateStatistics.php update:statistics --rvcode mbj --month 2023-01

# Force reprocessing
php scripts/UpdateStatistics.php update:statistics --rvcode mbj --date 2023-01-15 --force
```

## Database Changes

### New Table: STAT_PROCESSING_LOG
```sql
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### STAT_TEMP Table Fix
- Fixed auto-increment issue by removing `VISITID` from INSERT statements
- Column names updated to match actual table structure: `DOCID`, `IP`, `HTTP_USER_AGENT`, `DHIT`, `CONSULT`

## File Structure
- **Apache Logs**: `../logs/httpd/{journal}.episciences.org/{year}/{month}/{day}-{journal}.episciences.org.access_log[.gz]`
- **Processing Logs**: Database-based tracking in `STAT_PROCESSING_LOG` table

## Log File Format Support
The script processes Apache Combined Log Format:
```
IP - - [timestamp] "GET /articles/{id} HTTP/1.1" status size "referer" "user-agent"
```

### Supported URL Patterns
- `/articles/{id}` → Notice view (`CONSULT = 'notice'`)
- `/articles/{id}/download` → File download (`CONSULT = 'file'`)
- `/articles/{id}/preview` → File preview (`CONSULT = 'file'`)

## Testing
Run the test suite to verify functionality:
```bash
# PHP unit tests
make phpunit tests/unit/scripts/UpdateStatisticsTest.php
make phpunit tests/unit/scripts/UpdateStatisticsIntegrationTest.php
```

## Error Handling
- Missing log files are skipped with warnings (not errors)
- Invalid document IDs are filtered out
- Malformed log lines are logged but don't stop processing
- Database errors cause rollback and proper error reporting

## Performance Considerations
- Uses transactions for bulk database inserts
- Progress reporting for large date ranges
- Memory-efficient line-by-line file processing
- Indexes on processing log table for fast duplicate checking