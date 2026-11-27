-- Store the relations of the linked data (paper_datasets.relationship) in the Crossref vocabulary
-- (Episciences\Paper\Relationship): rows imported from HAL TEI, the DataCite shaped repositories (Zenodo, ...)
-- or Scholexplorer may hold a DataCite / Scholix relation type (PascalCase or not in the vocabulary).
-- New rows are normalized by Episciences\Paper\RelationshipMapper; the mapping below is the same,
-- see docs/linked-data-relationships.md.
-- Run the whole script in a single session: the steps rely on the temporary tables.
--
-- The column is utf8mb4_unicode_ci (case insensitive): the comparisons use utf8mb4_bin, so that only the rows
-- that are not normalized yet are updated and running the script twice changes nothing.

-- 0. Audit (optional): relations stored, case sensitive
-- SELECT relationship COLLATE utf8mb4_bin AS rel, COUNT(*) AS nb
-- FROM paper_datasets
-- GROUP BY rel
-- ORDER BY nb DESC;

-- 1. DataCite / Scholix relation type => stored relation
CREATE TEMPORARY TABLE PAPER_DATASETS_RELATIONSHIP_MAP
(
    FROM_VALUE VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL PRIMARY KEY,
    TO_VALUE   VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
);

-- 1a. Dedicated mappings (RelationshipMapper::DATACITE_TO_CROSSREF), PascalCase and camelCase
INSERT INTO PAPER_DATASETS_RELATIONSHIP_MAP (FROM_VALUE, TO_VALUE)
VALUES ('IsReviewedBy', 'hasReview'),
       ('isReviewedBy', 'hasReview'),
       ('Reviews', 'isReviewOf'),
       ('reviews', 'isReviewOf'),
       ('Cites', 'references'),
       ('cites', 'references'),
       ('IsCitedBy', 'isReferencedBy'),
       ('isCitedBy', 'isReferencedBy'),
       ('IsSourceOf', 'hasDerivation'),
       ('isSourceOf', 'hasDerivation'),
       ('IsNewVersionOf', 'isVersionOf'),
       ('isNewVersionOf', 'isVersionOf'),
       ('IsPreviousVersionOf', 'hasVersion'),
       ('isPreviousVersionOf', 'hasVersion'),
       ('IsRelatedTo', 'isRelatedMaterial'),
       ('isRelatedTo', 'isRelatedMaterial'),
       ('IsDescribedBy', 'isDocumentedBy'),
       ('isDescribedBy', 'isDocumentedBy'),
       ('Describes', 'documents'),
       ('describes', 'documents'),
       ('IsObsoletedBy', 'isReplacedBy'),
       ('isObsoletedBy', 'isReplacedBy'),
       ('Obsoletes', 'replaces'),
       ('obsoletes', 'replaces');

-- 1b. DataCite relation types whose lcfirst() is in the vocabulary: PascalCase => camelCase
INSERT INTO PAPER_DATASETS_RELATIONSHIP_MAP (FROM_VALUE, TO_VALUE)
SELECT FROM_VALUE, CONCAT(LOWER(LEFT(FROM_VALUE, 1)), SUBSTRING(FROM_VALUE, 2))
FROM (SELECT 'IsSupplementTo' AS FROM_VALUE
      UNION ALL SELECT 'IsSupplementedBy'
      UNION ALL SELECT 'IsContinuedBy'
      UNION ALL SELECT 'Continues'
      UNION ALL SELECT 'HasVersion'
      UNION ALL SELECT 'IsVersionOf'
      UNION ALL SELECT 'IsPartOf'
      UNION ALL SELECT 'HasPart'
      UNION ALL SELECT 'IsReferencedBy'
      UNION ALL SELECT 'References'
      UNION ALL SELECT 'IsDocumentedBy'
      UNION ALL SELECT 'Documents'
      UNION ALL SELECT 'IsCompiledBy'
      UNION ALL SELECT 'Compiles'
      UNION ALL SELECT 'IsVariantFormOf'
      UNION ALL SELECT 'IsOriginalFormOf'
      UNION ALL SELECT 'IsIdenticalTo'
      UNION ALL SELECT 'IsDerivedFrom'
      UNION ALL SELECT 'IsRequiredBy'
      UNION ALL SELECT 'Requires'
      UNION ALL SELECT 'IsTranslationOf'
      UNION ALL SELECT 'HasTranslation') AS DATACITE_RELATION_TYPES;

-- 1c. Stored vocabulary (Relationship::getFlattenedRelationships() and getFlattenedRelationshipsIntraWorkRelation())
CREATE TEMPORARY TABLE PAPER_DATASETS_STORED_RELATIONSHIPS
(
    RELATIONSHIP VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL PRIMARY KEY
);

INSERT INTO PAPER_DATASETS_STORED_RELATIONSHIPS (RELATIONSHIP)
VALUES ('isBasedOn'), ('isBasisFor'), ('basedOnData'), ('isDataBasisFor'),
       ('isCommentOn'), ('hasComment'),
       ('isContinuedBy'), ('continues'),
       ('isDerivedFrom'), ('hasDerivation'),
       ('isDocumentedBy'), ('documents'),
       ('isFinancedBy'),
       ('isPartOf'), ('hasPart'),
       ('isReviewOf'), ('hasReview'),
       ('references'), ('isReferencedBy'),
       ('hasRelatedMaterial'), ('isRelatedMaterial'),
       ('isReplyTo'), ('hasReply'),
       ('requires'), ('isRequiredBy'),
       ('isCompiledBy'), ('compiles'),
       ('isSupplementTo'), ('isSupplementedBy'),
       ('isTranslationOf'), ('hasTranslation'),
       ('isPreprintOf'), ('hasPreprint'),
       ('isManuscriptOf'), ('hasManuscript'),
       ('isExpressionOf'), ('hasExpression'),
       ('isManifestationOf'), ('hasManifestation'),
       ('isReplacedBy'), ('replaces'),
       ('isSameAs'),
       ('isIdenticalTo'),
       ('isVariantFormOf'), ('isOriginalFormOf'),
       ('isVersionOf'), ('hasVersion'),
       ('isFormatOf'), ('hasFormat');

-- 2. Freeze the affected DOCIDs: only the mapped rows of these papers are updated (step 3),
--    all of them are listed for the follow-up (step 4)
CREATE TEMPORARY TABLE PAPER_DATASETS_NORMALIZED_DOCIDS (DOCID INT NOT NULL PRIMARY KEY)
SELECT DISTINCT pd.doc_id AS DOCID
FROM paper_datasets pd
         INNER JOIN PAPER_DATASETS_RELATIONSHIP_MAP m ON pd.relationship COLLATE utf8mb4_bin = m.FROM_VALUE;

-- 2b. Papers with a relation left without equivalent (HasMetadata, IsMetadataFor, IsCollectedBy, Collects,
--     IsPublishedIn, unknown values): not updated, but the Crossref export now gives them as "references"
--     (they were left out), so their PAPERS.DOCUMENT must be rebuilt too.
--     These papers are listed again when the script is run twice.
INSERT IGNORE INTO PAPER_DATASETS_NORMALIZED_DOCIDS (DOCID)
SELECT DISTINCT pd.doc_id
FROM paper_datasets pd
         LEFT JOIN PAPER_DATASETS_RELATIONSHIP_MAP m ON pd.relationship COLLATE utf8mb4_bin = m.FROM_VALUE
         LEFT JOIN PAPER_DATASETS_STORED_RELATIONSHIPS s ON pd.relationship COLLATE utf8mb4_bin = s.RELATIONSHIP
WHERE pd.relationship IS NOT NULL
  AND m.FROM_VALUE IS NULL
  AND s.RELATIONSHIP IS NULL;

-- 3. Normalize
UPDATE paper_datasets pd
    INNER JOIN PAPER_DATASETS_NORMALIZED_DOCIDS d ON d.DOCID = pd.doc_id
    INNER JOIN PAPER_DATASETS_RELATIONSHIP_MAP m ON pd.relationship COLLATE utf8mb4_bin = m.FROM_VALUE
SET pd.relationship = m.TO_VALUE;

-- 4. List the DOCIDs to update (the default group_concat_max_len would silently truncate the list)
SET SESSION group_concat_max_len = 1048576;
SELECT GROUP_CONCAT(DOCID ORDER BY DOCID) AS DOCIDS_TO_UPDATE
FROM PAPER_DATASETS_NORMALIZED_DOCIDS;

-- 5. Audit (optional): relations left without equivalent, exported as "references" to Crossref
--    and left out of the OpenAIRE export
-- SELECT pd.relationship COLLATE utf8mb4_bin AS rel, COUNT(*) AS nb
-- FROM paper_datasets pd
--          LEFT JOIN PAPER_DATASETS_STORED_RELATIONSHIPS s ON pd.relationship COLLATE utf8mb4_bin = s.RELATIONSHIP
-- WHERE pd.relationship IS NOT NULL
--   AND s.RELATIONSHIP IS NULL
-- GROUP BY rel
-- ORDER BY nb DESC;

DROP TEMPORARY TABLE PAPER_DATASETS_NORMALIZED_DOCIDS;
DROP TEMPORARY TABLE PAPER_DATASETS_STORED_RELATIONSHIPS;
DROP TEMPORARY TABLE PAPER_DATASETS_RELATIONSHIP_MAP;

-- 6. For the DOCIDs listed in step 4:
--    a. rebuild PAPERS.DOCUMENT, built from the Crossref export that now includes the normalized rows:
--       php scripts/console.php papers:update-document --sqlwhere "DOCID IN (<DOCIDS_TO_UPDATE>)"
--    b. reindex them:
--       php scripts/solr/solrJob.php -e <env> -c episciences --sqlwhere "DOCID IN (<DOCIDS_TO_UPDATE>)"
--    c. revalidate the matching pages of the new front, if any.
--    The cached exports of these papers (Episciences_Paper::get(): Crossref, OpenAIRE, TEI) are rebuilt when their
--    cache expires (31 days for a published paper); no command purges them yet.
