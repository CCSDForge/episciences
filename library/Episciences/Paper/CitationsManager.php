<?php
declare(strict_types=1);
/**
 * Backward-compatible facade for the citations pipeline.
 *
 * All public methods are preserved and delegate to the focused sub-classes:
 *   - Episciences_Paper_Citations_Repository  (DB I/O)
 *   - Episciences_Paper_Citations_ViewFormatter (HTML rendering)
 *   - Episciences_Paper_Citations_EnrichmentService (API enrichment pipeline)
 *   - Episciences_Paper_Citations_Logger (logging)
 *
 * @deprecated All static methods in this class are deprecated. Use the sub-classes directly.
 */
class Episciences_Paper_CitationsManager
{
    /** @deprecated Use Episciences_Paper_Citations_ViewFormatter::NUMBER_OF_AUTHORS_WANTED_VIEWS */
    public const NUMBER_OF_AUTHORS_WANTED_VIEWS = Episciences_Paper_Citations_ViewFormatter::NUMBER_OF_AUTHORS_WANTED_VIEWS;

    // ────────────────────────────────────────────
    // Persistence (delegates to Repository)
    // ────────────────────────────────────────────
    /**
     * @deprecated Use Episciences_Paper_Citations_Repository::insert()
     */
    public static function insert(array $citations): int
    {
        return Episciences_Paper_Citations_Repository::insert($citations);
    }

    /**
     * @deprecated Use Episciences_Paper_Citations_Repository::findByDocId()
     */
    public static function getCitationByDocId(int $docId): array
    {
        return Episciences_Paper_Citations_Repository::findByDocId($docId);
    }

    // ────────────────────────────────────────────
    // HTML rendering (delegates to ViewFormatter)
    // ────────────────────────────────────────────
    /**
     * @return array{template: string, counterCitations: int}
     * @deprecated Use Episciences_Paper_Citations_ViewFormatter::formatCitationsForViewPaper()
     */
    public static function formatCitationsForViewPaper(int $docId): array
    {
        return Episciences_Paper_Citations_ViewFormatter::formatCitationsForViewPaper($docId);
    }

    /**
     * @deprecated Use Episciences_Paper_Citations_ViewFormatter::formatAuthors()
     */
    public static function formatAuthors(string $author): string
    {
        return Episciences_Paper_Citations_ViewFormatter::formatAuthors($author);
    }

    /**
     * @deprecated Use Episciences_Paper_Citations_ViewFormatter::reduceAuthorsView()
     */
    public static function reduceAuthorsView(string $author): string
    {
        return Episciences_Paper_Citations_ViewFormatter::reduceAuthorsView($author);
    }

    /**
     * @deprecated Use Episciences_Paper_Citations_ViewFormatter::createOrcidStringForView()
     */
    public static function createOrcidStringForView(string $orcid): string
    {
        return Episciences_Paper_Citations_ViewFormatter::createOrcidStringForView($orcid);
    }

    /**
     * @deprecated Use Episciences_Paper_Citations_ViewFormatter::reorganizeForBookChapter()
     */
    public static function reorganizeForBookChapter(array $citation): array
    {
        return Episciences_Paper_Citations_ViewFormatter::reorganizeForBookChapter($citation);
    }

    /**
     * @deprecated Use Episciences_Paper_Citations_ViewFormatter::reorganizeForProceedingsArticle()
     */
    public static function reorganizeForProceedingsArticle(array $citation): array
    {
        return Episciences_Paper_Citations_ViewFormatter::reorganizeForProceedingsArticle($citation);
    }

    /**
     * @deprecated Use Episciences_Paper_Citations_ViewFormatter::sortAuthorAndYear()
     */
    public static function sortAuthorAndYear(array $arrayMetadata = []): array
    {
        return Episciences_Paper_Citations_ViewFormatter::sortAuthorAndYear($arrayMetadata);
    }

    // ────────────────────────────────────────────
    // Enrichment pipeline (delegates to EnrichmentService)
    // ────────────────────────────────────────────
    /**
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     * @deprecated Use Episciences_Paper_Citations_EnrichmentService::getAllCitationInfoAndFormat()
     */
    public static function getAllCitationInfoAndFormat(
        array $metadataInfoCitation,
        array $globalInfoMetadata,
        int $i,
        string $doiWhoCite
    ): array {
        return Episciences_Paper_Citations_EnrichmentService::getAllCitationInfoAndFormat(
            $metadataInfoCitation,
            $globalInfoMetadata,
            $i,
            $doiWhoCite
        );
    }

    /**
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     * @deprecated Use Episciences_Paper_Citations_EnrichmentService::processCitationsByDoiCited()
     */
    public static function processCitationsByDoiCited(string $doiWhoCite, array $globalInfoMetadata, int $i): array
    {
        return Episciences_Paper_Citations_EnrichmentService::processCitationsByDoiCited(
            $doiWhoCite,
            $globalInfoMetadata,
            $i
        );
    }

    /**
     * @throws JsonException
     * @deprecated Use Episciences_Paper_Citations_EnrichmentService::persist()
     */
    public static function insertOrUpdateCitationsByDocId(array $globalInfoMetadata, int $docId): void
    {
        Episciences_Paper_Citations_EnrichmentService::persist($globalInfoMetadata, $docId);
    }

    /**
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     * @deprecated Use Episciences_Paper_Citations_EnrichmentService::extractAndStore()
     */
    public static function extractCitationsAndInsertInDb(array $apiCallCitationCache, int $docId): void
    {
        Episciences_Paper_Citations_EnrichmentService::extractAndStore($apiCallCitationCache, $docId);
    }

    // ────────────────────────────────────────────
    // Logging — kept public (called by CrossrefTools + OpenalexTools)
    // ────────────────────────────────────────────
    /**
     * @deprecated Use Episciences_Paper_Citations_Logger::log()
     */
    public static function logInfoMessage(string $msg): void
    {
        Episciences_Paper_Citations_Logger::log($msg);
    }
}
