-- Remove the "arXiv:" prefix (any case) from arXiv identifiers (REPOID = 2), see issue #1481.
-- New submissions are cleaned by Episciences_Submit::cleanIdentifier().
-- Run the whole script in a single session: steps 2 and 3 rely on the temporary table of step 1.

-- 1. Freeze the affected DOCIDs: only these rows are updated (step 2) and listed for reindexing (step 3),
--    so a row inserted while the script runs cannot be updated without being reindexed.
CREATE TEMPORARY TABLE ARXIV_PREFIXED_DOCIDS (DOCID INT UNSIGNED NOT NULL PRIMARY KEY)
SELECT DOCID
FROM PAPERS
WHERE REPOID = 2
  AND REGEXP_LIKE(IDENTIFIER, '^[[:space:]]*arxiv[[:space:]]*:', 'i');

-- 2. Strip the prefix
UPDATE PAPERS
    INNER JOIN ARXIV_PREFIXED_DOCIDS USING (DOCID)
SET PAPERS.IDENTIFIER = REGEXP_REPLACE(PAPERS.IDENTIFIER, '^[[:space:]]*arxiv[[:space:]]*:[[:space:]]*', '', 1, 0, 'i');

-- 3. List the DOCIDs to reindex (the default group_concat_max_len would silently truncate the list)
SET SESSION group_concat_max_len = 1048576;
SELECT GROUP_CONCAT(DOCID ORDER BY DOCID) AS DOCIDS_TO_REINDEX
FROM ARXIV_PREFIXED_DOCIDS;

DROP TEMPORARY TABLE ARXIV_PREFIXED_DOCIDS;

-- 4. Reindex the DOCIDs listed in step 3 (identifier_s field):
--    php scripts/solr/solrJob.php -e <env> -c episciences --sqlwhere "DOCID IN (<DOCIDS_TO_REINDEX>)"
--    then revalidate the matching pages of the new front, if any.
