<?php

declare(strict_types=1);

/**
 * Thin facade preserving 100% backward compatibility with all call-sites.
 * All business logic has been moved to dedicated classes:
 *   - Episciences_Paper_Projects_Repository     (DB)
 *   - Episciences_Paper_Projects_HalApiClient   (HTTP)
 *   - Episciences_Paper_Projects_EnrichmentService (orchestration)
 *   - Episciences_Paper_Projects_ViewFormatter  (HTML)
 */
class Episciences_Paper_ProjectsManager
{
    // Constants preserved for external callers (e.g. Repositories/Dataverse/Hooks.php)
    public const ONE_MONTH    = Episciences_Paper_Projects_EnrichmentService::ONE_MONTH;
    public const UNIDENTIFIED = Episciences_Paper_Projects_EnrichmentService::UNIDENTIFIED;

    // -------------------------------------------------------------------------
    // DB read
    // -------------------------------------------------------------------------

    public static function getProjectsByPaperId(int $paperId): array
    {
        return Episciences_Paper_Projects_Repository::getByPaperId($paperId);
    }

    public static function getProjectsByPaperIdAndSourceId(int $paperId, int $sourceId): array
    {
        return Episciences_Paper_Projects_Repository::getByPaperIdAndSourceId($paperId, $sourceId);
    }

    /**
     * @throws JsonException
     */
    public static function getProjectWithDuplicateRemoved(int $paperId): array
    {
        return Episciences_Paper_Projects_Repository::getWithDuplicateRemoved($paperId);
    }

    // -------------------------------------------------------------------------
    // DB write — accept array OR entity for backward compatibility
    // -------------------------------------------------------------------------

    /**
     * @param array|Episciences_Paper_Projects $projects
     */
    public static function insert($projects): int
    {
        if (!($projects instanceof Episciences_Paper_Projects)) {
            $projects = new Episciences_Paper_Projects($projects);
        }
        return Episciences_Paper_Projects_Repository::insert($projects);
    }

    /**
     * @param array|Episciences_Paper_Projects $projects
     */
    public static function update($projects): int
    {
        if (!($projects instanceof Episciences_Paper_Projects)) {
            $projects = new Episciences_Paper_Projects($projects);
        }
        return Episciences_Paper_Projects_Repository::update($projects);
    }

    // -------------------------------------------------------------------------
    // Enrichment
    // -------------------------------------------------------------------------

    /**
     * @throws JsonException
     */
    public static function insertOrUpdateFundingOA(
        array $globalfundingArray,
        array $rowInDBGraph,
        int   $paperId
    ): int {
        return Episciences_Paper_Projects_EnrichmentService::insertOrUpdateFundingOA(
            $globalfundingArray,
            $rowInDBGraph,
            $paperId
        );
    }

    /**
     * @throws JsonException
     */
    public static function insertOrUpdateHalFunding(
        array $rowInDbHal,
        array $mergeArrayANREU,
        int   $paperId
    ): int {
        return Episciences_Paper_Projects_EnrichmentService::insertOrUpdateHalFunding(
            $rowInDbHal,
            $mergeArrayANREU,
            $paperId
        );
    }

    public static function formatFundingOAForDB(
        array $fileFound,
        array $fundingArray,
        array $globalfundingArray
    ): array {
        return Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB(
            $fileFound,
            $fundingArray,
            $globalfundingArray
        );
    }

    public static function formatEuHalResp(array $respEuHAl): array
    {
        return Episciences_Paper_Projects_EnrichmentService::formatEuHalResp($respEuHAl);
    }

    public static function formatAnrHalResp(array $respAnrHAl): array
    {
        return Episciences_Paper_Projects_EnrichmentService::formatAnrHalResp($respAnrHAl);
    }

    // -------------------------------------------------------------------------
    // HAL API (kept for external callers)
    // -------------------------------------------------------------------------

    public static function CallHAlApiForIdEuAndAnrFunding(string $identifier, int $version): string
    {
        return Episciences_Paper_Projects_HalApiClient::fetchProjectIds($identifier, $version);
    }

    public static function CallHAlApiForEuroProject(int $halDocId): string
    {
        return Episciences_Paper_Projects_HalApiClient::fetchEuropeanProject($halDocId);
    }

    public static function CallHAlApiForAnrProject(int $halDocId): string
    {
        return Episciences_Paper_Projects_HalApiClient::fetchAnrProject($halDocId);
    }

    /**
     * @throws JsonException
     */
    public static function FormatFundingANREuToArray(
        array  $rawArray,
        string $identifier,
        array  $globalArrayJson
    ): array {
        return Episciences_Paper_Projects_EnrichmentService::resolveHalProjectIds(
            $rawArray,
            $identifier,
            $globalArrayJson
        );
    }

    // -------------------------------------------------------------------------
    // View
    // -------------------------------------------------------------------------

    /**
     * Backward compat: original method returned '' when empty.
     * ViewFormatter::formatForView() always returns array; restore '' here.
     *
     * @throws JsonException
     * @throws Zend_Exception
     */
    public static function formatProjectsForview(int $paperId): string|array
    {
        $result = Episciences_Paper_Projects_ViewFormatter::formatForView($paperId);
        return empty($result) ? '' : $result;
    }
}
