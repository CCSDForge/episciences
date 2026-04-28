<?php

declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Orchestration of the funding enrichment pipeline.
 * Uses Repository + HalApiClient + Symfony Cache.
 * Owns the singleton logger.
 */
class Episciences_Paper_Projects_EnrichmentService
{
    public  const ONE_MONTH       = 3600 * 24 * 31;
    public  const UNIDENTIFIED    = 'unidentified';
    private const LOGGER_CHANNEL  = 'ProjectsEnrichment';
    private const LOG_FILE_PREFIX = 'funding_enrichment_';
    private const CACHE_NAMESPACE = 'enrichmentFunding';
    private const JSON_ENCODE_FLAGS = JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE;
    private const JSON_DECODE_FLAGS = JSON_THROW_ON_ERROR;
    private const JSON_MAX_DEPTH    = 512;

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
     * Build the globalArrayJson of EU/ANR project metadata from raw HAL docs.
     * Replaces FormatFundingANREuToArray().
     *
     * @throws JsonException
     */
    public static function resolveHalProjectIds(
        array  $halDocs,
        string $identifier,
        array  $accumulator
    ): array {
        $cache         = new FilesystemAdapter(self::CACHE_NAMESPACE, self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $safeIdentifier = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $identifier);

        foreach ($halDocs as $halValue) {
            if (isset($halValue['europeanProjectId_i'])) {
                foreach ($halValue['europeanProjectId_i'] as $idEuro) {
                    self::logInfo('European project found on HAL: ' . $idEuro);
                    $cacheKey  = $safeIdentifier . '_' . $idEuro . '_EU_funding.json';
                    $cacheItem = $cache->getItem($cacheKey);
                    $cacheItem->expiresAfter(self::ONE_MONTH);
                    if (!$cacheItem->isHit()) {
                        $resp = Episciences_Paper_Projects_HalApiClient::fetchEuropeanProject((int) $idEuro);
                        $cacheItem->set($resp);
                        $cache->save($cacheItem);
                    } else {
                        $resp = $cacheItem->get();
                    }
                    $accumulator[] = self::formatEuHalResp(
                        json_decode((string) $resp, true, self::JSON_MAX_DEPTH, self::JSON_DECODE_FLAGS)
                    );
                }
            }

            if (isset($halValue['anrProjectId_i'])) {
                foreach ($halValue['anrProjectId_i'] as $idAnr) {
                    self::logInfo('ANR project found on HAL: ' . $idAnr);
                    $cacheKey  = $safeIdentifier . '_' . $idAnr . '_ANR_funding.json';
                    $cacheItem = $cache->getItem($cacheKey);
                    $cacheItem->expiresAfter(self::ONE_MONTH);
                    if (!$cacheItem->isHit()) {
                        $resp = Episciences_Paper_Projects_HalApiClient::fetchAnrProject((int) $idAnr);
                        $cacheItem->set($resp);
                        $cache->save($cacheItem);
                    } else {
                        self::logInfo('ANR project retrieved from cache');
                        $resp = $cacheItem->get();
                    }
                    $accumulator[] = self::formatAnrHalResp(
                        json_decode((string) $resp, true, self::JSON_MAX_DEPTH, self::JSON_DECODE_FLAGS)
                    );
                }
            }
        }
        return $accumulator;
    }

    /**
     * Filter OpenAire relations to keep only project-type entries.
     * Replaces formatFundingOAForDB().
     */
    public static function formatFundingOAForDB(
        array $fileFound,
        array $fundingArray,
        array $globalFundingArray
    ): array {
        foreach ($fileFound as $valueOpenAire) {
            if (
                !array_key_exists('to', $valueOpenAire) ||
                !array_key_exists('@type', $valueOpenAire['to']) ||
                $valueOpenAire['to']['@type'] !== 'project'
            ) {
                continue;
            }
            if (array_key_exists('title', $valueOpenAire)) {
                $fundingArray['projectTitle'] = $valueOpenAire['title']['$'];
            }
            if (array_key_exists('acronym', $valueOpenAire)) {
                $fundingArray['acronym'] = $valueOpenAire['acronym']['$'];
            }
            if (array_key_exists('funder', $valueOpenAire['funding'])) {
                $fundingArray['funderName'] = $valueOpenAire['funding']['funder']['@name'];
            }
            if (array_key_exists('code', $valueOpenAire)) {
                $fundingArray['code'] = $valueOpenAire['code']['$'];
            }
            $globalFundingArray[] = $fundingArray;
        }
        return $globalFundingArray;
    }

    /**
     * Map a HAL European project API response to a normalized array.
     * Replaces formatEuHalResp().
     */
    public static function formatEuHalResp(array $respEuHal): array
    {
        $defaults = [
            'projectTitle'     => self::UNIDENTIFIED,
            'acronym'          => self::UNIDENTIFIED,
            'funderName'       => 'European Commission',
            'code'             => self::UNIDENTIFIED,
            'callId'           => self::UNIDENTIFIED,
            'projectFinancing' => self::UNIDENTIFIED,
        ];
        return self::normalizeDocs($respEuHal, $defaults);
    }

    /**
     * Map a HAL ANR project API response to a normalized array.
     * Replaces formatAnrHalResp().
     */
    public static function formatAnrHalResp(array $respAnrHal): array
    {
        $defaults = [
            'projectTitle' => self::UNIDENTIFIED,
            'acronym'      => self::UNIDENTIFIED,
            'funderName'   => 'French National Research Agency (ANR)',
            'code'         => self::UNIDENTIFIED,
        ];
        return self::normalizeDocs($respAnrHal, $defaults);
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

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Normalize docs from a HAL API response, filling missing keys with defaults.
     */
    private static function normalizeDocs(array $resp, array $defaults): array
    {
        $result = [];
        if (empty($resp['response']['docs'])) {
            return $result;
        }
        $i = 0;
        foreach ($resp['response']['docs'] as $key => $value) {
            $result[$key] = $value;
            $missing = array_diff_key($defaults, $value);
            foreach ($missing as $missingKey => $missingValue) {
                $result[$i][$missingKey] = $missingValue;
            }
            $i++;
        }
        return $result;
    }

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
            self::$logger->pushHandler(new StreamHandler($logFile, Logger::INFO));
        }
        return self::$logger;
    }
}
