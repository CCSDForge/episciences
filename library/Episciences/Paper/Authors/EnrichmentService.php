<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Episciences_Paper_Authors_EnrichmentService
{
    private const KEY_ORCID = 'orcid';
    private const KEY_FULLNAME = 'fullname';
    private const KEY_AFFILIATION = 'affiliation';
    private const KEY_AFFILIATIONS = 'affiliations';
    private const KEY_ROR = 'ROR';
    private const KEY_ACRONYM = 'acronym';
    private const KEY_NAME = 'name';

    private const JSON_ENCODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT;
    private const LOGGER_CHANNEL = 'AuthorsManager';
    private const LOG_FILE_PREFIX = 'getcreatordata_';

    private static ?Logger $logger = null;

    /**
     * Enrich author ORCID and affiliation data from HAL TEI into the database
     *
     * @param int $repoId repository identifier
     * @param int $paperId paper identifier
     * @param string $identifier HAL document identifier
     * @param int $version HAL document version (0 = latest)
     * @return int number of affected rows
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function enrichAffiOrcidFromTeiHalInDB(int $repoId, int $paperId, string $identifier, int $version): int
    {
        if ($repoId !== (int)Episciences_Repositories::HAL_REPO_ID) {
            return 0;
        }

        $decodedAuthors = Episciences_Paper_Authors_Repository::getDecodedAuthors($paperId);

        Episciences_Hal_TeiCacheManager::fetchAndCache($identifier, $version);
        $cachedTeiXml = Episciences_Hal_TeiCacheManager::getFromCache($identifier, $version);

        if ($cachedTeiXml === '') {
            return 0;
        }

        $teiDocument = simplexml_load_string($cachedTeiXml);

        if (!is_object($teiDocument) || $teiDocument->count() <= 0) {
            return 0;
        }

        $teiAuthors = Episciences_Paper_Authors_HalTeiParser::getAuthorsFromHalTei($teiDocument);
        $teiAffiliations = Episciences_Paper_Authors_HalTeiParser::getAffiFromHalTei($teiDocument);
        $teiAuthors = Episciences_Paper_Authors_HalTeiParser::mergeAuthorInfoAndAffiTei($teiAuthors, $teiAffiliations);

        $enrichedAuthors = self::mergeInfoDbAndInfoTei($decodedAuthors, $teiAuthors);

        $authorEntity = new Episciences_Paper_Authors();
        $authorEntity->setAuthors(json_encode($enrichedAuthors, self::JSON_ENCODE_FLAGS));
        $authorEntity->setPaperId($paperId);

        return Episciences_Paper_Authors_Repository::update($authorEntity);
    }

    /**
     * Merge database author records with TEI-parsed author data (ORCID + affiliations)
     *
     * @param array $dbAuthors authors from the database
     * @param array $teiAuthors authors parsed from TEI
     * @return array enriched authors array
     */
    public static function mergeInfoDbAndInfoTei(array $dbAuthors, array $teiAuthors): array
    {
        foreach ($dbAuthors as $authorIndex => $dbAuthor) {
            foreach ($teiAuthors as $teiAuthor) {
                if (!self::matchAuthorByName($dbAuthor, $teiAuthor)) {
                    continue;
                }

                self::enrichAuthorOrcid($dbAuthors, $authorIndex, $dbAuthor, $teiAuthor);
                self::enrichAuthorAffiliations($dbAuthors, $authorIndex, $dbAuthor, $teiAuthor);
            }
        }

        return $dbAuthors;
    }

    /**
     * Check whether a DB author and a TEI author represent the same person (by name)
     *
     * @param array $dbAuthor single author from database
     * @param array $teiAuthor single author from TEI
     * @return bool true if names match (exact or accent-insensitive)
     */
    private static function matchAuthorByName(array $dbAuthor, array $teiAuthor): bool
    {
        $dbFullname = $dbAuthor[self::KEY_FULLNAME];
        $teiFullname = $teiAuthor[self::KEY_FULLNAME];

        return ($dbFullname === $teiFullname)
            || (Episciences_Tools::replaceAccents($teiFullname) === Episciences_Tools::replaceAccents($dbFullname));
    }

    /**
     * Add ORCID from TEI to a DB author that lacks one
     *
     * @param array &$dbAuthors full authors array (modified by reference)
     * @param int|string $authorIndex index of the author being enriched
     * @param array $dbAuthor current author data from DB
     * @param array $teiAuthor matching author data from TEI
     */
    private static function enrichAuthorOrcid(array &$dbAuthors, int|string $authorIndex, array $dbAuthor, array $teiAuthor): void
    {
        if (!array_key_exists(self::KEY_ORCID, $teiAuthor) || array_key_exists(self::KEY_ORCID, $dbAuthor)) {
            return;
        }

        $dbAuthors[$authorIndex][self::KEY_ORCID] = $teiAuthor[self::KEY_ORCID];

        if (PHP_SAPI === 'cli') {
            self::logInfoMessage('Orcid Added for ' . $dbAuthors[$authorIndex][self::KEY_FULLNAME]);
        }
    }

    /**
     * Add or merge affiliations from TEI into a DB author record
     *
     * @param array &$dbAuthors full authors array (modified by reference)
     * @param int|string $authorIndex index of the author being enriched
     * @param array $dbAuthor current author data from DB
     * @param array $teiAuthor matching author data from TEI
     */
    private static function enrichAuthorAffiliations(array &$dbAuthors, int|string $authorIndex, array $dbAuthor, array $teiAuthor): void
    {
        if (!array_key_exists(self::KEY_AFFILIATIONS, $teiAuthor)) {
            return;
        }

        if (array_key_exists(self::KEY_AFFILIATION, $dbAuthor)) {
            self::mergeExistingAffiliations($dbAuthors, $authorIndex, $dbAuthor, $teiAuthor);
        } else {
            self::addNewAffiliations($dbAuthors, $authorIndex, $teiAuthor);
        }
    }

    /**
     * Merge TEI affiliations into an author that already has affiliations in DB
     *
     * @param array &$dbAuthors full authors array (modified by reference)
     * @param int|string $authorIndex index of the author being enriched
     * @param array $dbAuthor current author data from DB
     * @param array $teiAuthor matching author data from TEI
     */
    private static function mergeExistingAffiliations(array &$dbAuthors, int|string $authorIndex, array $dbAuthor, array $teiAuthor): void
    {
        foreach ($teiAuthor[self::KEY_AFFILIATIONS] as $teiAffiliation) {
            $existingNames = array_column($dbAuthor[self::KEY_AFFILIATION], self::KEY_NAME);
            $affiliationName = $teiAffiliation[self::KEY_NAME];

            if (!in_array($affiliationName, $existingNames, true)) {
                self::appendAffiliation($dbAuthors, $authorIndex, $teiAffiliation);

                if (PHP_SAPI === 'cli') {
                    self::logInfoMessage('Affiliation Added with ROR for ' . $dbAuthors[$authorIndex][self::KEY_FULLNAME]);
                }
                continue;
            }

            $currentAffiliationKey = key($dbAuthors[$authorIndex][self::KEY_AFFILIATION]);
            $hasRor = array_key_exists(self::KEY_ROR, $teiAffiliation);
            $dbAffiliationHasRor = Episciences_Paper_Authors_AffiliationHelper::hasRor(
                $dbAuthor[self::KEY_AFFILIATION][$currentAffiliationKey]
            );

            if ($hasRor && !$dbAffiliationHasRor) {
                $acronym = $teiAffiliation[self::KEY_ACRONYM] ?? '';
                $dbAuthors[$authorIndex][self::KEY_AFFILIATION][$currentAffiliationKey]['id'] =
                    Episciences_Paper_Authors_AffiliationHelper::buildRorOnly($teiAffiliation[self::KEY_ROR], $acronym);

                if (PHP_SAPI === 'cli') {
                    self::logInfoMessage('ROR to Affiliation Added for ' . $dbAuthors[$authorIndex][self::KEY_FULLNAME] . ' - ' . $affiliationName);
                }
            }
        }
    }

    /**
     * Add all TEI affiliations to an author that has no affiliations in DB
     *
     * @param array &$dbAuthors full authors array (modified by reference)
     * @param int|string $authorIndex index of the author being enriched
     * @param array $teiAuthor matching author data from TEI
     */
    private static function addNewAffiliations(array &$dbAuthors, int|string $authorIndex, array $teiAuthor): void
    {
        foreach ($teiAuthor[self::KEY_AFFILIATIONS] as $teiAffiliation) {
            $hasRor = array_key_exists(self::KEY_ROR, $teiAffiliation);
            self::appendAffiliation($dbAuthors, $authorIndex, $teiAffiliation);

            if (PHP_SAPI === 'cli') {
                $rorLabel = $hasRor ? 'with ROR' : 'without ROR found,';
                self::logInfoMessage(
                    'New Affiliation ' . $rorLabel . ' Added for '
                    . $dbAuthors[$authorIndex][self::KEY_FULLNAME] . ' - ' . $teiAffiliation[self::KEY_NAME]
                );
            }
        }
    }

    /**
     * Append a single affiliation (with or without ROR) to an author
     *
     * @param array &$dbAuthors full authors array (modified by reference)
     * @param int|string $authorIndex index of the author
     * @param array $teiAffiliation affiliation data from TEI
     */
    private static function appendAffiliation(array &$dbAuthors, int|string $authorIndex, array $teiAffiliation): void
    {
        if (array_key_exists(self::KEY_ROR, $teiAffiliation)) {
            $dbAuthors[$authorIndex][self::KEY_AFFILIATION][] =
                Episciences_Paper_Authors_AffiliationHelper::buildWithRor($teiAffiliation);
        } else {
            $dbAuthors[$authorIndex][self::KEY_AFFILIATION][] =
                Episciences_Paper_Authors_AffiliationHelper::buildNameOnly($teiAffiliation[self::KEY_NAME]);
        }
    }

    /**
     * Log an informational message to the enrichment log file
     *
     * @param string $message log message
     * @throws Exception
     */
    public static function logInfoMessage(string $message): void
    {
        self::getLogger()->info($message);
    }

    /**
     * @return Logger
     * @throws Exception
     */
    private static function getLogger(): Logger
    {
        if (self::$logger === null) {
            $logFile = EPISCIENCES_LOG_PATH . self::LOG_FILE_PREFIX . date('Y-m-d') . '.log';
            self::$logger = new Logger(self::LOGGER_CHANNEL);
            self::$logger->pushHandler(new StreamHandler($logFile, Logger::INFO));
        }

        return self::$logger;
    }
}
