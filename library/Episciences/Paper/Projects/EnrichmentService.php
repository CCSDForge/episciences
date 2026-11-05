<?php

declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Episciences\Paper\Funding\HalTeiFundingParser;

/**
 * Orchestration of the funding enrichment pipeline.
 * Uses Repository, the cached HAL TEI and the OpenAIRE Graph responses.
 * Owns the singleton logger.
 */
class Episciences_Paper_Projects_EnrichmentService
{
    public  const ONE_MONTH       = 3600 * 24 * 31;
    public  const UNIDENTIFIED    = 'unidentified';
    private const LOGGER_CHANNEL  = 'ProjectsEnrichment';
    private const LOG_FILE_PREFIX = 'funding_enrichment_';
    private const JSON_ENCODE_FLAGS = JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE;

    private static ?Logger $logger = null;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Insert or update OpenAire Graph funding for a paper.
     * Replaces insertOrUpdateFundingOA().
     *
     * @throws JsonException
     */
    public static function insertOrUpdateFundingOA(
        array $globalFundingArray,
        array $rowInDbGraph,
        int   $paperId
    ): int {
        if ($globalFundingArray === []) {
            return 0;
        }

        $fundingJson = json_encode($globalFundingArray, self::JSON_ENCODE_FLAGS);

        if ($rowInDbGraph === []) {
            self::logInfo('OpenAIRE funding project found, saving');
            $project = self::buildProject($fundingJson, $paperId, (int) Episciences_Repositories::GRAPH_OPENAIRE_ID);
            return Episciences_Paper_Projects_Repository::insert($project);
        }

        $project = self::buildProject($fundingJson, $paperId, (int) Episciences_Repositories::GRAPH_OPENAIRE_ID);
        return Episciences_Paper_Projects_Repository::update($project);
    }

    /**
     * Insert or update HAL funding (ANR + EU) for a paper.
     * Replaces insertOrUpdateHalFunding().
     *
     * @throws JsonException
     */
    public static function insertOrUpdateHalFunding(
        array $rowInDbHal,
        array $mergeArrayAnrEu,
        int   $paperId
    ): int {
        if ($mergeArrayAnrEu === []) {
            return 0;
        }

        $fundingJson = json_encode($mergeArrayAnrEu, self::JSON_ENCODE_FLAGS);
        $project     = self::buildProject($fundingJson, $paperId, (int) Episciences_Repositories::HAL_REPO_ID);

        if ($rowInDbHal !== []) {
            self::logInfo('HAL funding project updated');
            return Episciences_Paper_Projects_Repository::update($project);
        }

        self::logInfo('HAL funding project saved');
        return Episciences_Paper_Projects_Repository::insert($project);
    }

    /**
     * Read the HAL fundings (ANR and European projects, free-text fundings) from the document TEI,
     * fetched once and cached for the author enrichment as well, then save them.
     *
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function enrichHalFundingFromTei(int $paperId, string $identifier, int $version): int
    {
        $tei = Episciences_Hal_TeiCacheManager::fetchAndGet($identifier, $version);

        if (trim($tei) === '') {
            self::logWarning(sprintf('HAL TEI unavailable for %s (version %d): HAL fundings not updated', $identifier, $version));
            return 0;
        }

        $unresolved = HalTeiFundingParser::unresolvedReferences($tei);

        if ($unresolved !== []) {
            // the fundings read would be incomplete: keep the stored ones rather than lose some
            self::logWarning(sprintf(
                'HAL TEI for %s (version %d) refers to unknown project(s) %s: HAL fundings not updated',
                $identifier,
                $version,
                implode(', ', $unresolved)
            ));
            return 0;
        }

        $fundings = HalTeiFundingParser::parse($tei);

        if ($fundings === [] && !HalTeiFundingParser::hasDocument($tei)) {
            self::logWarning(sprintf('HAL TEI for %s (version %d) holds no document: HAL fundings not updated', $identifier, $version));
            return 0;
        }

        $rowInDbHal = Episciences_Paper_Projects_Repository::getByPaperIdAndSourceId($paperId, (int) Episciences_Repositories::HAL_REPO_ID);

        // the document was read: no funding in it means that there is none, or that the depositor removed it from HAL
        if ($fundings === []) {
            if ($rowInDbHal === []) {
                return 0;
            }
            self::logInfo(sprintf('HAL TEI: no funding left for %s, HAL funding project removed', $identifier));
            return Episciences_Paper_Projects_Repository::deleteByPaperIdAndSourceId($paperId, (int) Episciences_Repositories::HAL_REPO_ID);
        }

        self::logInfo(sprintf('HAL TEI: %d funding(s) found for %s', count($fundings), $identifier));

        return self::insertOrUpdateHalFunding($rowInDbHal, $fundings, $paperId);
    }

    /**
     * Map OpenAire Graph v3 project entries (results[0].projects) to the DB funding structure.
     *
     * Replaces formatFundingOAForDB().
     */
    public static function formatFundingOAForDB(
        array $fileFound,
        array $globalFundingArray
    ): array {
        foreach ($fileFound as $valueOpenAire) {
            if (!self::isV3ProjectEntry($valueOpenAire)) {
                continue;
            }
            $globalFundingArray[] = self::mapV3ProjectEntry($valueOpenAire);
        }
        return $globalFundingArray;
    }

    /**
     * A valid v3 project entry carries a direct scalar 'title'.
     *
     * @param array<string, mixed> $entry
     */
    private static function isV3ProjectEntry(array $entry): bool
    {
        return array_key_exists('title', $entry) && is_string($entry['title']);
    }

    /**
     * Map a single v3 project entry ({id, title, acronym, code, funder, pids}) to the DB funding structure.
     * 'funder' may be a scalar name (current REST serialization) or a Data Model object
     * ({name, shortName, jurisdiction, fundingStream}); both are supported.
     *
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function mapV3ProjectEntry(array $entry): array
    {
        $fundingArray = ['projectTitle' => $entry['title']];

        if (!empty($entry['code'])) {
            $fundingArray['code'] = $entry['code'];
        }
        if (!empty($entry['acronym'])) {
            $fundingArray['acronym'] = $entry['acronym'];
        }

        $funder = $entry['funder'] ?? null;
        if (is_string($funder) && $funder !== '') {
            $fundingArray['funderName'] = $funder;
        } elseif (is_array($funder) && !empty($funder['name'])) {
            $fundingArray['funderName'] = $funder['name'];
        }

        // the identifier prefix tells the funder and the funding stream (e.g. "corda__h2020::...")
        if (is_string($entry['id'] ?? null) && $entry['id'] !== '') {
            $fundingArray['openaireId'] = $entry['id'];
        }

        $grantDoi = self::extractGrantDoi($entry['pids'] ?? null);
        if ($grantDoi !== null) {
            $fundingArray['grantDoi'] = $grantDoi;
        }

        return $fundingArray;
    }

    /**
     * Grant DOI of a v3 project entry, from its pids ([{scheme: "doi", value: "10.3030/101017452"}]).
     */
    private static function extractGrantDoi(mixed $pids): ?string
    {
        if (!is_array($pids)) {
            return null;
        }
        foreach ($pids as $pid) {
            if (
                is_array($pid)
                && strtolower((string) ($pid['scheme'] ?? '')) === 'doi'
                && is_string($pid['value'] ?? null)
                && $pid['value'] !== ''
            ) {
                return $pid['value'];
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    public static function logInfo(string $message): void
    {
        if (PHP_SAPI === 'cli') {
            echo $message . PHP_EOL;
        }
        self::getLogger()->info($message);
    }

    public static function logWarning(string $message): void
    {
        if (PHP_SAPI === 'cli') {
            echo $message . PHP_EOL;
        }
        self::getLogger()->warning($message);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build an Episciences_Paper_Projects entity from raw values.
     */
    private static function buildProject(string $fundingJson, int $paperId, int $sourceId): Episciences_Paper_Projects
    {
        return (new Episciences_Paper_Projects())
            ->setFunding($fundingJson)
            ->setPaperId($paperId)
            ->setSourceId($sourceId);
    }

    /**
     * Lazy singleton logger.
     */
    private static function getLogger(): Logger
    {
        if (!self::$logger instanceof \Monolog\Logger) {
            $logFile      = EPISCIENCES_LOG_PATH . self::LOG_FILE_PREFIX . date('Y-m-d') . '.log';
            self::$logger = new Logger(self::LOGGER_CHANNEL);
            self::$logger->pushHandler(new StreamHandler($logFile, Level::Info));
        }
        return self::$logger;
    }
}
