# Statistics Pipeline

This document describes the full statistics pipeline: from Apache access logs to aggregated KPI data.

## Overview

```
Apache logs  ──►  stats:import-logs  ──►  STAT_TEMP
                                              │
                                              ▼
                                      stats:process  ──►  PAPER_STAT
                                                               │
                                                               ▼
                                                   stats:download-kpi  ──►  data/kpi_downloads.json
```

| Step | Command | Description |
|------|---------|-------------|
| 1 | [`stats:import-logs`](#statsimport-logs) | Parse Apache access logs → `STAT_TEMP` |
| 2 | [`stats:process`](./console-commands.md#statsprocess) | Enrich & classify `STAT_TEMP` → `PAPER_STAT` |
| 3 | [`stats:download-kpi`](./console-commands.md#statsdownload-kpi) | Aggregate `PAPER_STAT` → `data/kpi_downloads.json` |

---

## `stats:import-logs`

Parses Apache Combined Log Format access logs for one or all journals, filters article visit
patterns, and bulk-inserts the raw hits into `STAT_TEMP`. Supports plain and gzip-compressed log
files transparently.

Duplicate prevention: a `STAT_PROCESSING_LOG` table tracks which (journal, date) pairs have already
been processed. Re-runs are skipped unless `--force` is passed.

> **Prerequisite:** run `src/mysql/2025-08-24-stat-processing-log-table.sql` once before the first
> execution to create the `STAT_PROCESSING_LOG` table.

### URL patterns recognized

| Apache URL pattern | `CONSULT` value |
|--------------------|-----------------|
| `GET /articles/{id} HTTP/…` | `notice` (abstract page view) |
| `GET /articles/{id}/download HTTP/…` | `file` (PDF download) |
| `GET /articles/{id}/preview HTTP/…` | `file` (PDF preview) |

### Log file layout

```
{logs-path}/{rvcode}.episciences.org/{YYYY}/{MM}/{DD}-{rvcode}.episciences.org.access_log[.gz]
```

Default `logs-path`: `../logs/httpd` (relative to the project root).

### Usage

```bash
php scripts/console.php stats:import-logs [options]
```

| Option | Description |
|--------|-------------|
| `--rvcode <code>` | Journal to process — mutually exclusive with `--all` |
| `--all` | Process all journals with `is_new_front_switched = yes` — mutually exclusive with `--rvcode` |
| `--date <YYYY-MM-DD>` | Single day to process (default: yesterday) |
| `--month <YYYY-MM>` | Process an entire month |
| `--year <YYYY>` | Process an entire year |
| `--start-date <YYYY-MM-DD>` | Start of a custom date range (requires `--end-date`) |
| `--end-date <YYYY-MM-DD>` | End of a custom date range (requires `--start-date`) |
| `--force` | Reprocess dates already recorded in `STAT_PROCESSING_LOG` |
| `--logs-path <path>` | Override the base Apache log directory |

Only one date selector (`--date`, `--month`, `--year`, or `--start-date`/`--end-date`) may be used at a time.

### Make shortcuts

```bash
# Process yesterday's logs for one journal
make import-apache-logs rvcode=epiga

# Process all journals (is_new_front_switched = yes)
make import-apache-logs all=1

# Specific date
make import-apache-logs rvcode=epiga date=2025-06-15

# Full month
make import-apache-logs rvcode=epiga month=2025-06

# Full year
make import-apache-logs rvcode=epiga year=2025

# Custom date range
make import-apache-logs rvcode=epiga start-date=2025-06-01 end-date=2025-06-30

# Force reprocessing of already-processed dates
make import-apache-logs rvcode=epiga date=2025-06-15 force=1
```

### Cron schedule

Run daily, before `stats:process` (which reads from `STAT_TEMP`):

```
0 1 * * *  www-data  php /var/www/htdocs/scripts/console.php stats:import-logs --all
0 2 * * *  www-data  php /var/www/htdocs/scripts/console.php stats:process
```

---

## Database prerequisites

### `STAT_PROCESSING_LOG` table

Must be created before the first run of `stats:import-logs`:

```bash
# Apply migration
mysql -u root episciences < src/mysql/2025-08-24-stat-processing-log-table.sql
```

```sql
CREATE TABLE `STAT_PROCESSING_LOG` (
  `ID`                int UNSIGNED NOT NULL AUTO_INCREMENT,
  `JOURNAL_CODE`      varchar(50)  NOT NULL,
  `PROCESSED_DATE`    date         NOT NULL,
  `PROCESSED_AT`      timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FILE_PATH`         varchar(500) NOT NULL,
  `RECORDS_PROCESSED` int UNSIGNED NOT NULL DEFAULT 0,
  `STATUS`            enum('success','error','partial') NOT NULL DEFAULT 'success',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `unique_journal_date` (`JOURNAL_CODE`, `PROCESSED_DATE`),
  KEY `idx_journal_code`  (`JOURNAL_CODE`),
  KEY `idx_processed_date` (`PROCESSED_DATE`),
  KEY `idx_processed_at`  (`PROCESSED_AT`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Testing

```bash
# Unit tests
make test-php ARGS="tests/unit/scripts/ImportApacheLogsCommandTest.php"

# Integration tests
make test-php ARGS="tests/integration/scripts/ImportApacheLogsIntegrationTest.php"
```