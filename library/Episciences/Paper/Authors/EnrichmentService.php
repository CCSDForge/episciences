<?php
declare(strict_types=1);
use Monolog\Handler\StreamHandler;
use Monolog\Level;
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
    private const KEY_ID = 'id';
    private const KEY_GIVEN = 'given';
    private const KEY_TEI_GIVEN_NAME = 'given_name';
    private const KEY_TEI_FIRST_NAME = 'first_name';
    private const KEY_FAMILY = 'family';

    private const JSON_ENCODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT;
    private const LOGGER_CHANNEL = 'AuthorsManager';
    private const LOG_FILE_PREFIX = 'getcreatordata_';

    private static ?Logger $logger = null;

    /** @var string[] corrections applied since the last resetCorrections() call */
    private static array $corrections = [];

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
        $teiAuthors = self::getHalTeiAuthors($identifier, $version);

        if ($teiAuthors === []) {
            return 0;
        }

        $enrichedAuthors = self::mergeInfoDbAndInfoTei($decodedAuthors, $teiAuthors);

        $authorEntity = new Episciences_Paper_Authors();
        $authorEntity->setAuthors(json_encode($enrichedAuthors, self::JSON_ENCODE_FLAGS));
        $authorEntity->setPaperId($paperId);

        return Episciences_Paper_Authors_Repository::update($authorEntity);
    }

    /**
     * Fetch (or read from cache) the HAL TEI of a document and parse its authors with resolved affiliations
     *
     * @param string $identifier HAL document identifier
     * @param int $version HAL document version (0 = latest)
     * @return array<int, array<string, mixed>> TEI authors, empty if the TEI is unavailable or invalid
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getHalTeiAuthors(string $identifier, int $version): array
    {
        $cachedTeiXml = Episciences_Hal_TeiCacheManager::fetchAndGet($identifier, $version);

        if ($cachedTeiXml === '') {
            return [];
        }

        $previousXmlErrors = libxml_use_internal_errors(true);
        $teiDocument = simplexml_load_string($cachedTeiXml);
        libxml_use_internal_errors($previousXmlErrors);

        if (!is_object($teiDocument) || $teiDocument->count() <= 0) {
            return [];
        }

        $teiAuthors = Episciences_Paper_Authors_HalTeiParser::getAuthorsFromHalTei($teiDocument);
        $teiAffiliations = Episciences_Paper_Authors_HalTeiParser::getAffiFromHalTei($teiDocument);
        $resolvedAuthors = Episciences_Paper_Authors_HalTeiParser::mergeAuthorInfoAndAffiTei($teiAuthors, $teiAffiliations);

        if ($teiAuthors !== [] && $resolvedAuthors === []) {
            \Episciences\AppRegistry::getMonoLogger()?->warning(
                'HAL TEI of ' . $identifier . ' v' . $version . ' ignored: an author affiliation references an unknown structure'
            );
        }

        return $resolvedAuthors;
    }

    /**
     * Build DB author records directly from HAL TEI authors (names with middle names, ORCID, affiliations)
     *
     * The OAI-DC authors stay the reference for the author list: TEI records are only used
     * when both sources list the same authors (same family names, in the same order),
     * otherwise the DC records are returned.
     *
     * @param array<int, array<string, mixed>> $dcAuthors authors built from OAI-DC metadata
     * @param array<int, array<string, mixed>> $teiAuthors authors parsed from TEI
     * @return array<int, array<string, mixed>> authors ready for DB storage
     */
    public static function buildAuthorsFromHalTei(array $dcAuthors, array $teiAuthors): array
    {
        if (!self::haveSameFamilyNames($dcAuthors, $teiAuthors)) {
            return $dcAuthors;
        }

        $authors = [];

        foreach ($teiAuthors as $authorIndex => $teiAuthor) {
            $givenName = $teiAuthor[self::KEY_TEI_GIVEN_NAME] ?? '';
            $authors[$authorIndex] = [
                self::KEY_FULLNAME => $teiAuthor[self::KEY_FULLNAME],
                self::KEY_GIVEN => $givenName !== '' ? $givenName : null,
                self::KEY_FAMILY => $teiAuthor[self::KEY_FAMILY],
            ];

            if (isset($teiAuthor[self::KEY_ORCID])) {
                $authors[$authorIndex][self::KEY_ORCID] = $teiAuthor[self::KEY_ORCID];
            }

            foreach ($teiAuthor[self::KEY_AFFILIATIONS] ?? [] as $teiAffiliation) {
                self::appendAffiliation($authors, $authorIndex, $teiAffiliation);
            }
        }

        self::recordCorrection('Authors inserted from HAL TEI instead of OAI-DC: ' . implode(', ', array_column($authors, self::KEY_FULLNAME)));

        return array_values($authors);
    }

    /**
     * Check that OAI-DC and TEI list the same authors: same count, same family names in the same order
     * (case and accent insensitive)
     *
     * @param array<int, array<string, mixed>> $dcAuthors authors built from OAI-DC metadata
     * @param array<int, array<string, mixed>> $teiAuthors authors parsed from TEI
     */
    private static function haveSameFamilyNames(array $dcAuthors, array $teiAuthors): bool
    {
        if ($teiAuthors === [] || count($teiAuthors) !== count($dcAuthors)) {
            return false;
        }

        $normalize = static fn(mixed $family): string
            => mb_strtolower(Episciences_Tools::replaceAccents(trim((string)$family)));

        $teiAuthors = array_values($teiAuthors);

        foreach (array_values($dcAuthors) as $position => $dcAuthor) {
            if ($normalize($dcAuthor[self::KEY_FAMILY] ?? '') !== $normalize($teiAuthors[$position][self::KEY_FAMILY] ?? '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Merge database author records with TEI-parsed author data (names, ORCID, affiliations)
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

                self::enrichAuthorGivenName($dbAuthors, $authorIndex, $dbAuthor, $teiAuthor);
                self::enrichAuthorOrcid($dbAuthors, $authorIndex, $dbAuthor, $teiAuthor);
                self::enrichAuthorAffiliations($dbAuthors, $authorIndex, $dbAuthor, $teiAuthor);
                break;
            }
        }

        return $dbAuthors;
    }

    /**
     * Check whether a DB author and a TEI author represent the same person (by name)
     *
     * Authors inserted from OAI-DC lack middle names, so the TEI name is also compared
     * without its middle names (first forename + surname).
     *
     * @param array $dbAuthor single author from database
     * @param array $teiAuthor single author from TEI
     * @return bool true if names match (exact or accent-insensitive)
     */
    private static function matchAuthorByName(array $dbAuthor, array $teiAuthor): bool
    {
        $dbFullname = $dbAuthor[self::KEY_FULLNAME];
        $teiFullnames = [$teiAuthor[self::KEY_FULLNAME]];

        if (isset($teiAuthor[self::KEY_TEI_FIRST_NAME], $teiAuthor[self::KEY_FAMILY])) {
            $teiFullnames[] = trim($teiAuthor[self::KEY_TEI_FIRST_NAME] . ' ' . $teiAuthor[self::KEY_FAMILY]);
        }

        foreach ($teiFullnames as $teiFullname) {
            if (($dbFullname === $teiFullname)
                || (Episciences_Tools::replaceAccents($teiFullname) === Episciences_Tools::replaceAccents($dbFullname))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Complete the DB given name with the TEI middle names (e.g. "Eva" -> "Eva C")
     *
     * Only applied when the DB given name is the TEI first forename alone,
     * so that names already edited or completed are left untouched.
     *
     * @param array<int|string, array<string, mixed>> &$dbAuthors full authors array (modified by reference)
     * @param int|string $authorIndex index of the author being enriched
     * @param array<string, mixed> $dbAuthor current author data from DB
     * @param array<string, mixed> $teiAuthor matching author data from TEI
     */
    private static function enrichAuthorGivenName(array &$dbAuthors, int|string $authorIndex, array $dbAuthor, array $teiAuthor): void
    {
        $teiGivenName = $teiAuthor[self::KEY_TEI_GIVEN_NAME] ?? '';
        $teiFirstName = $teiAuthor[self::KEY_TEI_FIRST_NAME] ?? '';
        $dbGivenName = (string)($dbAuthor[self::KEY_GIVEN] ?? '');

        if ($teiFirstName === '' || $teiGivenName === $teiFirstName) {
            return;
        }

        if (Episciences_Tools::replaceAccents($dbGivenName) !== Episciences_Tools::replaceAccents($teiFirstName)) {
            return;
        }

        $dbAuthors[$authorIndex][self::KEY_GIVEN] = $teiGivenName;
        $dbAuthors[$authorIndex][self::KEY_FULLNAME] = trim($teiGivenName . ' ' . ($dbAuthor[self::KEY_FAMILY] ?? ''));

        self::recordCorrection('Middle name added: ' . $dbAuthor[self::KEY_FULLNAME] . ' -> ' . $dbAuthors[$authorIndex][self::KEY_FULLNAME]);
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

        self::recordCorrection('ORCID ' . $teiAuthor[self::KEY_ORCID] . ' added for ' . $dbAuthors[$authorIndex][self::KEY_FULLNAME]);
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
     * A missing ROR is added from the TEI. An existing ROR is only fixed when it is also
     * carried by another affiliation of the same author (ROR copied from a sibling
     * affiliation by a former enrichment bug); any other ROR, e.g. set manually, is kept.
     *
     * @param array &$dbAuthors full authors array (modified by reference)
     * @param int|string $authorIndex index of the author being enriched
     * @param array $dbAuthor current author data from DB
     * @param array $teiAuthor matching author data from TEI
     */
    private static function mergeExistingAffiliations(array &$dbAuthors, int|string $authorIndex, array $dbAuthor, array $teiAuthor): void
    {
        foreach ($teiAuthor[self::KEY_AFFILIATIONS] as $teiAffiliation) {
            $existingNames = array_map(
                static fn($aff) => $aff[self::KEY_NAME] ?? null,
                $dbAuthors[$authorIndex][self::KEY_AFFILIATION]
            );
            $affiliationName = $teiAffiliation[self::KEY_NAME];
            $matchingKeys = array_keys($existingNames, $affiliationName, true);

            if ($matchingKeys === []) {
                self::appendAffiliation($dbAuthors, $authorIndex, $teiAffiliation);

                self::recordAffiliationAdded($dbAuthors[$authorIndex][self::KEY_FULLNAME], $teiAffiliation);
                continue;
            }

            foreach ($matchingKeys as $matchingKey) {
                self::alignAffiliationRor($dbAuthors, $authorIndex, $matchingKey, $teiAffiliation, $dbAuthor[self::KEY_AFFILIATION]);
            }
        }
    }

    /**
     * Align the ROR of an existing DB affiliation on the TEI affiliation with the same name
     *
     * @param array<int|string, array<string, mixed>> &$dbAuthors full authors array (modified by reference)
     * @param int|string $authorIndex index of the author being enriched
     * @param int|string $affiliationKey index of the DB affiliation matching the TEI one
     * @param array{name: string, ROR?: string, acronym?: string} $teiAffiliation affiliation data from TEI
     * @param array<int|string, array<string, mixed>> $originalAffiliations author affiliations before this enrichment
     */
    private static function alignAffiliationRor(
        array &$dbAuthors,
        int|string $authorIndex,
        int|string $affiliationKey,
        array $teiAffiliation,
        array $originalAffiliations
    ): void
    {
        $dbAffiliation = $dbAuthors[$authorIndex][self::KEY_AFFILIATION][$affiliationKey];
        $dbRor = self::getAffiliationRor($dbAffiliation);
        $teiRor = $teiAffiliation[self::KEY_ROR] ?? null;
        $logSuffix = $dbAuthors[$authorIndex][self::KEY_FULLNAME] . ' - ' . $teiAffiliation[self::KEY_NAME];

        if ($teiRor === $dbRor) {
            return;
        }

        if ($dbRor !== null && !self::isRorCopiedFromSibling($originalAffiliations, $affiliationKey, $dbRor)) {
            return;
        }

        if ($teiRor === null) {
            unset($dbAuthors[$authorIndex][self::KEY_AFFILIATION][$affiliationKey][self::KEY_ID]);

            self::recordCorrection('ROR ' . $dbRor . ' unknown in HAL, removed for ' . $logSuffix);
            return;
        }

        $dbAuthors[$authorIndex][self::KEY_AFFILIATION][$affiliationKey][self::KEY_ID] =
            Episciences_Paper_Authors_AffiliationHelper::buildRorOnly($teiRor, $teiAffiliation[self::KEY_ACRONYM] ?? '');

        $action = $dbRor === null ? 'ROR ' . $teiRor . ' added for ' : 'ROR ' . $dbRor . ' replaced by ' . $teiRor . ' for ';
        self::recordCorrection($action . $logSuffix);
    }

    /**
     * Check whether a ROR is also carried by another affiliation (with a different name) of the same author
     *
     * @param array<int|string, array<string, mixed>> $affiliations author affiliations
     * @param int|string $affiliationKey index of the affiliation being checked
     * @param string $ror ROR URL of that affiliation
     */
    private static function isRorCopiedFromSibling(array $affiliations, int|string $affiliationKey, string $ror): bool
    {
        $name = $affiliations[$affiliationKey][self::KEY_NAME] ?? null;

        foreach ($affiliations as $key => $affiliation) {
            if ($key !== $affiliationKey
                && ($affiliation[self::KEY_NAME] ?? null) !== $name
                && self::getAffiliationRor($affiliation) === $ror) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the ROR URL of a DB affiliation, or null if it has none
     *
     * @param array<string, mixed> $dbAffiliation single affiliation entry from the DB
     */
    private static function getAffiliationRor(array $dbAffiliation): ?string
    {
        foreach ($dbAffiliation[self::KEY_ID] ?? [] as $identifier) {
            if (($identifier['id-type'] ?? null) === Episciences_Paper_Authors_AffiliationHelper::ID_TYPE_ROR) {
                return $identifier[self::KEY_ID] ?? null;
            }
        }

        return null;
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
            self::appendAffiliation($dbAuthors, $authorIndex, $teiAffiliation);
            self::recordAffiliationAdded($dbAuthors[$authorIndex][self::KEY_FULLNAME], $teiAffiliation);
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
     * Corrections applied to author records since the last resetCorrections() call
     *
     * @return string[] human-readable correction messages, in application order
     */
    public static function getCorrections(): array
    {
        return self::$corrections;
    }

    public static function resetCorrections(): void
    {
        self::$corrections = [];
    }

    /**
     * Keep track of a correction, and log it to the enrichment log file when running in CLI
     */
    private static function recordCorrection(string $message): void
    {
        self::$corrections[] = $message;

        if (PHP_SAPI === 'cli') {
            self::logInfoMessage($message);
        }
    }

    /**
     * @param array{name: string, ROR?: string} $teiAffiliation affiliation data from TEI
     */
    private static function recordAffiliationAdded(string $fullname, array $teiAffiliation): void
    {
        $rorLabel = isset($teiAffiliation[self::KEY_ROR]) ? ' (ROR ' . $teiAffiliation[self::KEY_ROR] . ')' : ' (no ROR)';
        self::recordCorrection('Affiliation added for ' . $fullname . ' - ' . $teiAffiliation[self::KEY_NAME] . $rorLabel);
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
     * @throws Exception
     */
    private static function getLogger(): Logger
    {
        if (!self::$logger instanceof \Monolog\Logger) {
            $logFile = EPISCIENCES_LOG_PATH . self::LOG_FILE_PREFIX . date('Y-m-d') . '.log';
            self::$logger = new Logger(self::LOGGER_CHANNEL);
            self::$logger->pushHandler(new StreamHandler($logFile, Level::Info));
        }

        return self::$logger;
    }
}
