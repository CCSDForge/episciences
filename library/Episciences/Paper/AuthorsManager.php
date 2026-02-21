<?php
declare(strict_types=1);
class Episciences_Paper_AuthorsManager
{
    /** @deprecated Use Episciences_Hal_TeiCacheManager::ONE_MONTH */
    public const ONE_MONTH = Episciences_Hal_TeiCacheManager::ONE_MONTH;

    // ────────────────────────────────────────────
    // ORCID normalization (stays here, used cross-module)
    // ────────────────────────────────────────────
    /**
     * Normalize an ORCID identifier: strip URL prefix and fix lowercase checksum digit
     *
     * @deprecated Use Episciences_Paper_Authors_HalTeiParser::normalizeOrcid()
     */
    public static function normalizeOrcid(string $orcid): string
    {
        return Episciences_Paper_Authors_HalTeiParser::normalizeOrcid($orcid);
    }

    /**
     * @deprecated Use normalizeOrcid() instead
     */
    public static function cleanLowerCaseOrcid(string $orcid): string
    {
        return self::normalizeOrcid($orcid);
    }

    // ────────────────────────────────────────────
    // Orchestration methods (delegate to new classes)
    // ────────────────────────────────────────────
    /**
     * @return array|mixed
     * @throws JsonException
     */
    public static function getArrayAuthorsAffi(int $paperId)
    {
        return Episciences_Paper_Authors_Repository::getDecodedAuthors($paperId);
    }

    /**
     * @return mixed|string
     * @throws JsonException
     */
    public static function findAffiliationsOneAuthorByPaperId(int $paperId, int $idAuthorInJson)
    {
        return Episciences_Paper_Authors_Repository::findAffiliationsOneAuthorByPaperId($paperId, $idAuthorInJson);
    }

    // ────────────────────────────────────────────
    // Backward-compatible proxies — HAL TEI cache
    // ────────────────────────────────────────────
    /**
     * @throws \Psr\Cache\InvalidArgumentException
     * @deprecated Use Episciences_Hal_TeiCacheManager::fetchAndCache()
     */
    public static function getHalTei(string $identifier, int $version = 0): bool
    {
        return Episciences_Hal_TeiCacheManager::fetchAndCache($identifier, $version);
    }

    /**
     * @deprecated Use Episciences_Hal_TeiCacheManager
     */
    public static function getTeiHalByIdentifier(string $identifier, int $version = 0): string
    {
        return Episciences_Hal_TeiCacheManager::fetchAndGet($identifier, $version);
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     * @deprecated Use Episciences_Hal_TeiCacheManager::getFromCache()
     */
    public static function getHalTeiCache(string $identifier, int $version = 0): string
    {
        return Episciences_Hal_TeiCacheManager::getFromCache($identifier, $version);
    }

    // ────────────────────────────────────────────
    // Backward-compatible proxies — HAL TEI parser
    // ────────────────────────────────────────────
    /**
     * @deprecated Use Episciences_Paper_Authors_HalTeiParser::getAuthorsFromHalTei()
     */
    public static function getAuthorsFromHalTei(SimpleXMLElement $xmlString): array
    {
        return Episciences_Paper_Authors_HalTeiParser::getAuthorsFromHalTei($xmlString);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_HalTeiParser::getAuthorInfoFromXmlTei()
     */
    public static function getAuthorInfoFromXmlTei(?SimpleXMLElement $infoName, array $globalAuthorArray): array
    {
        return Episciences_Paper_Authors_HalTeiParser::getAuthorInfoFromXmlTei($infoName, $globalAuthorArray);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_HalTeiParser::getAuthorStructureFromXmlTei()
     */
    public static function getAuthorStructureFromXmlTei(?SimpleXMLElement $author, array $globalAuthorArray): array
    {
        return Episciences_Paper_Authors_HalTeiParser::getAuthorStructureFromXmlTei($author, $globalAuthorArray);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_HalTeiParser::getOrcidAuthorFromXmlTei()
     */
    public static function getOrcidAuthorFromXmlTei(?SimpleXMLElement $author, array $globalAuthorArray): array
    {
        return Episciences_Paper_Authors_HalTeiParser::getOrcidAuthorFromXmlTei($author, $globalAuthorArray);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_HalTeiParser::getAffiFromHalTei()
     */
    public static function getAffiFromHalTei(SimpleXMLElement $xmlString): array
    {
        return Episciences_Paper_Authors_HalTeiParser::getAffiFromHalTei($xmlString);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_HalTeiParser::mergeAuthorInfoAndAffiTei()
     */
    public static function mergeAuthorInfoAndAffiTei(array $authorTei, array $affiliationTei): array
    {
        return Episciences_Paper_Authors_HalTeiParser::mergeAuthorInfoAndAffiTei($authorTei, $affiliationTei);
    }

    // ────────────────────────────────────────────
    // Backward-compatible proxies — Repository
    // ────────────────────────────────────────────
    /**
     * @param int|string $paperId
     * @deprecated Use Episciences_Paper_Authors_Repository::getAuthorByPaperId()
     */
    public static function getAuthorByPaperId($paperId): array
    {
        return Episciences_Paper_Authors_Repository::getAuthorByPaperId($paperId);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_Repository::insert()
     */
    public static function insert(array $authors): int
    {
        return Episciences_Paper_Authors_Repository::insert($authors);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_Repository::update()
     */
    public static function update(Episciences_Paper_Authors $authors): int
    {
        return Episciences_Paper_Authors_Repository::update($authors);
    }

    /**
     * @return bool
     * @deprecated Use Episciences_Paper_Authors_Repository::deleteAuthorsByPaperId()
     */
    public static function deleteAuthorsByPaperId(int $paperId)
    {
        return Episciences_Paper_Authors_Repository::deleteAuthorsByPaperId($paperId);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_Repository::insertFromPaper()
     */
    public static function InsertAuthorsFromPapers(Episciences_Paper $paper): int
    {
        return Episciences_Paper_Authors_Repository::insertFromPaper($paper);
    }

    /**
     * @throws Zend_Db_Statement_Exception
     * @deprecated Use Episciences_Paper_Authors_Repository::verifyExistOrInsert()
     */
    public static function verifyExistOrInsert(int $docId, int $paperId): void
    {
        Episciences_Paper_Authors_Repository::verifyExistOrInsert($docId, $paperId);
    }

    // ────────────────────────────────────────────
    // Backward-compatible proxies — Enrichment
    // ────────────────────────────────────────────
    /**
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     * @deprecated Use Episciences_Paper_Authors_EnrichmentService::enrichAffiOrcidFromTeiHalInDB()
     */
    public static function enrichAffiOrcidFromTeiHalInDB(int $repoId, int $paperId, string $identifier, int $version): int
    {
        return Episciences_Paper_Authors_EnrichmentService::enrichAffiOrcidFromTeiHalInDB($repoId, $paperId, $identifier, $version);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei()
     */
    public static function mergeInfoDbAndInfoTei(array $authorDb, array $authorTei): array
    {
        return Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($authorDb, $authorTei);
    }

    /**
     * @throws Exception
     * @deprecated Use Episciences_Paper_Authors_EnrichmentService::logInfoMessage()
     */
    public static function logInfoMessage(string $msg): void
    {
        Episciences_Paper_Authors_EnrichmentService::logInfoMessage($msg);
    }

    // ────────────────────────────────────────────
    // Backward-compatible proxies — View formatter
    // ────────────────────────────────────────────
    /**
     * @param int|string $paperId
     * @deprecated Use Episciences_Paper_Authors_ViewFormatter::formatAuthorEnrichmentForViewByPaper()
     */
    public static function formatAuthorEnrichmentForViewByPaper($paperId): array
    {
        return Episciences_Paper_Authors_ViewFormatter::formatAuthorEnrichmentForViewByPaper($paperId);
    }

    /**
     * @return array
     * @throws JsonException
     * @deprecated Use Episciences_Paper_Authors_ViewFormatter::filterAuthorsAndAffiNumeric()
     */
    public static function filterAuthorsAndAffiNumeric(int $paperId)
    {
        return Episciences_Paper_Authors_ViewFormatter::filterAuthorsAndAffiNumeric($paperId);
    }

    // ────────────────────────────────────────────
    // Backward-compatible proxies — Affiliation helper
    // ────────────────────────────────────────────
    /**
     * @deprecated Use Episciences_Paper_Authors_AffiliationHelper::buildWithRor()
     */
    public static function putAffiliationWithRORinArray(array $affiliation): array
    {
        return Episciences_Paper_Authors_AffiliationHelper::buildWithRor($affiliation);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_AffiliationHelper::buildNameOnly()
     */
    public static function putOnlyNameAffiliation(string $name): array
    {
        return Episciences_Paper_Authors_AffiliationHelper::buildNameOnly($name);
    }

    /**
     * @return array[]
     * @deprecated Use Episciences_Paper_Authors_AffiliationHelper::buildRorOnly()
     */
    public static function putOnlyRORAffiliation(string $ror, ?string $acronym): array
    {
        return Episciences_Paper_Authors_AffiliationHelper::buildRorOnly($ror, $acronym);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_AffiliationHelper::isAcronymDuplicate()
     */
    public static function acronymAlreadyExist(array $arrayAffi, string $acronym): bool
    {
        return Episciences_Paper_Authors_AffiliationHelper::isAcronymDuplicate($arrayAffi, $acronym);
    }

    /**
     * Format for the ROR input in paper View
     * @return array
     * @deprecated Use Episciences_Paper_Authors_AffiliationHelper::formatAffiliationForInputRor()
     */
    public static function formatAffiliationForInputRor(array $affiliation)
    {
        return Episciences_Paper_Authors_AffiliationHelper::formatAffiliationForInputRor($affiliation);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_AffiliationHelper::setOrUpdateRorAcronym()
     */
    public static function setOrUpdateRorAcronym(array $acronyms, string $haystack): string
    {
        return Episciences_Paper_Authors_AffiliationHelper::setOrUpdateRorAcronym($acronyms, $haystack);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_AffiliationHelper::eraseAcronymInName()
     */
    public static function eraseAcronymInName(string $name, string $acronym): string
    {
        return Episciences_Paper_Authors_AffiliationHelper::eraseAcronymInName($name, $acronym);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_AffiliationHelper::cleanAcronym()
     */
    public static function cleanAcronym(string $acronym): string
    {
        return Episciences_Paper_Authors_AffiliationHelper::cleanAcronym($acronym);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_AffiliationHelper::hasAcronym()
     */
    public static function AcronymExist(array $affiliationOfAuthor): bool
    {
        return Episciences_Paper_Authors_AffiliationHelper::hasAcronym($affiliationOfAuthor);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_AffiliationHelper::getExistingAcronyms()
     */
    public static function getAcronymExisting(array $affiliationDb): string
    {
        return Episciences_Paper_Authors_AffiliationHelper::getExistingAcronyms($affiliationDb);
    }

    /**
     * @deprecated Use Episciences_Paper_Authors_AffiliationHelper::formatAcronymList()
     */
    public static function formatAcronymList(array $acronymList): string
    {
        return Episciences_Paper_Authors_AffiliationHelper::formatAcronymList($acronymList);
    }
}
