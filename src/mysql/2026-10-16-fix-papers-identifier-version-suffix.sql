-- Remove the trailing version suffix ("vN") stored in PAPERS.IDENTIFIER for HAL and arXiv submissions
-- e.g. 2310.02192v2 -> 2310.02192, hal-01234567v4 -> hal-01234567
-- The application now strips this suffix at submission time (Episciences_Submit::getDoc()).
-- Modified: 2026-10-16
--
-- Run the steps in order. Steps 1 and 2 are read-only: review their output before running steps 3 to 5.

-- Repositories concerned: arXiv and every HAL portal (same rule as Episciences_Repositories::isFromHalRepository())
SET @arxiv_repo_id = 2;

-- -----------------------------------------------------------------------------
-- Step 1 (read-only): affected rows
--   SUFFIX_VERSION  : version carried by the identifier suffix
--   VERSION_MISMATCH: 1 when the stored VERSION differs from the suffix
--   SIBLING_CONFLICT: 1 when another version of the same paper already has VERSION = SUFFIX_VERSION
-- -----------------------------------------------------------------------------
SELECT p.DOCID,
       p.PAPERID,
       p.RVID,
       p.REPOID,
       p.STATUS,
       p.IDENTIFIER,
       REGEXP_REPLACE(p.IDENTIFIER, 'v[0-9]+$', '')                 AS NEW_IDENTIFIER,
       p.VERSION,
       CAST(REGEXP_SUBSTR(p.IDENTIFIER, '[0-9]+$') AS UNSIGNED)      AS SUFFIX_VERSION,
       p.VERSION <> CAST(REGEXP_SUBSTR(p.IDENTIFIER, '[0-9]+$') AS UNSIGNED) AS VERSION_MISMATCH,
       EXISTS (SELECT 1
               FROM PAPERS s
               WHERE s.PAPERID = p.PAPERID
                 AND s.DOCID <> p.DOCID
                 AND s.VERSION = CAST(REGEXP_SUBSTR(p.IDENTIFIER, '[0-9]+$') AS UNSIGNED)) AS SIBLING_CONFLICT,
       p.WHEN
FROM PAPERS p
WHERE p.IDENTIFIER REGEXP '^.+v[0-9]+$'
  AND (p.REPOID = @arxiv_repo_id
    OR p.REPOID IN (SELECT id FROM metadata_sources WHERE name LIKE BINARY '%HAL%'))
ORDER BY p.RVID, p.PAPERID, p.VERSION;

-- -----------------------------------------------------------------------------
-- Step 2 (read-only): count per repository
-- -----------------------------------------------------------------------------
SELECT p.REPOID, COUNT(*) AS NB
FROM PAPERS p
WHERE p.IDENTIFIER REGEXP '^.+v[0-9]+$'
  AND (p.REPOID = @arxiv_repo_id
    OR p.REPOID IN (SELECT id FROM metadata_sources WHERE name LIKE BINARY '%HAL%'))
GROUP BY p.REPOID;

-- -----------------------------------------------------------------------------
-- Step 3: backup of the affected rows
-- -----------------------------------------------------------------------------
CREATE TABLE PAPERS_BACKUP_20261016_IDENTIFIER_SUFFIX AS
SELECT p.DOCID, p.PAPERID, p.RVID, p.REPOID, p.IDENTIFIER, p.VERSION, p.MODIFICATION_DATE
FROM PAPERS p
WHERE p.IDENTIFIER REGEXP '^.+v[0-9]+$'
  AND (p.REPOID = @arxiv_repo_id
    OR p.REPOID IN (SELECT id FROM metadata_sources WHERE name LIKE BINARY '%HAL%'));

-- -----------------------------------------------------------------------------
-- Step 4: version, only where it is safe
--   (stored VERSION differs from the suffix and no other version of the paper uses it)
--   Must run before step 5, which removes the suffix.
--   Rows with SIBLING_CONFLICT = 1 in step 1 are left untouched and must be reviewed manually.
-- -----------------------------------------------------------------------------
START TRANSACTION;

UPDATE PAPERS p
    JOIN PAPERS_BACKUP_20261016_IDENTIFIER_SUFFIX b ON b.DOCID = p.DOCID
    LEFT JOIN PAPERS s ON s.PAPERID = p.PAPERID
                      AND s.DOCID <> p.DOCID
                      AND s.VERSION = CAST(REGEXP_SUBSTR(b.IDENTIFIER, '[0-9]+$') AS UNSIGNED)
SET p.VERSION = CAST(REGEXP_SUBSTR(b.IDENTIFIER, '[0-9]+$') AS UNSIGNED)
WHERE s.DOCID IS NULL
  AND p.VERSION <> CAST(REGEXP_SUBSTR(b.IDENTIFIER, '[0-9]+$') AS UNSIGNED);

-- -----------------------------------------------------------------------------
-- Step 5: identifier
-- -----------------------------------------------------------------------------
UPDATE PAPERS p
    JOIN PAPERS_BACKUP_20261016_IDENTIFIER_SUFFIX b ON b.DOCID = p.DOCID
SET p.IDENTIFIER = REGEXP_REPLACE(b.IDENTIFIER, 'v[0-9]+$', '');

-- Check the result, then COMMIT (or ROLLBACK)
SELECT b.DOCID, b.IDENTIFIER AS OLD_IDENTIFIER, p.IDENTIFIER, b.VERSION AS OLD_VERSION, p.VERSION
FROM PAPERS_BACKUP_20261016_IDENTIFIER_SUFFIX b
         JOIN PAPERS p ON p.DOCID = b.DOCID;

-- COMMIT;
-- ROLLBACK;

-- -----------------------------------------------------------------------------
-- Restore (if needed)
-- -----------------------------------------------------------------------------
-- UPDATE PAPERS p
--     JOIN PAPERS_BACKUP_20261016_IDENTIFIER_SUFFIX b ON b.DOCID = p.DOCID
-- SET p.IDENTIFIER = b.IDENTIFIER, p.VERSION = b.VERSION;
--
-- DROP TABLE PAPERS_BACKUP_20261016_IDENTIFIER_SUFFIX;
