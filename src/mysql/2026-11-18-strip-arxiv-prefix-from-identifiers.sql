-- Remove the "arXiv:" prefix (any case) from arXiv identifiers (REPOID = 2), see issue #1481.
-- New submissions are cleaned by Episciences_Submit::cleanIdentifier().

-- 1. Keep the list of the affected DOCIDs: they must be reindexed in Solr once updated (step 3),
--    and they can no longer be selected by their prefix after step 2.
SELECT GROUP_CONCAT(DOCID ORDER BY DOCID) AS DOCIDS_TO_REINDEX
FROM PAPERS
WHERE REPOID = 2
  AND IDENTIFIER REGEXP '^[[:space:]]*arxiv[[:space:]]*:';

-- 2. Strip the prefix
UPDATE PAPERS
SET IDENTIFIER = REGEXP_REPLACE(IDENTIFIER, '^[[:space:]]*arxiv[[:space:]]*:[[:space:]]*', '', 1, 0, 'i')
WHERE REPOID = 2
  AND IDENTIFIER REGEXP '^[[:space:]]*arxiv[[:space:]]*:';

-- 3. Reindex the DOCIDs listed in step 1 (identifier_s field):
--    php scripts/solr/solrJob.php -e <env> -c episciences --sqlwhere "DOCID IN (<DOCIDS_TO_REINDEX>)"
--    then revalidate the matching pages of the new front, if any.
