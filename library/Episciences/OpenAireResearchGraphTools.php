<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Episciences_OpenAireResearchGraphTools
{
    public const ONE_MONTH = 3600 * 24 * 31;

    // check if we have already the cache for the doi
    // if not call one time api and put cache for all concerned info like licences and creator (at this moment) and all the response from this api
    // avoid multiple call for this api

    public static function checkOpenAireGlobalInfoByDoi($doi,$paperId): void
    {

        $dir = CACHE_PATH_METADATA . 'openAireResearchGraph/';

        if (!file_exists($dir)) {
            $result = mkdir($dir);
            if (!$result) {
                die('Fatal error: Failed to create directory: ' . $dir);
            }
        }


        $fileOpenAireGlobalResponse = trim(explode("/", $doi)[1]) . ".json";
        $cache = new FilesystemAdapter('openAireResearchGraph', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $sets = $cache->getItem($fileOpenAireGlobalResponse);
        $sets->expiresAfter(self::ONE_MONTH);
        if (!$sets->isHit()) {
            $client = new Client();
            if (PHP_SAPI === 'cli'){
                $info = PHP_EOL . 'https://api.openaire.eu/search/publications/?doi=' . $doi . '&format=json'. PHP_EOL ;
                echo $info;
            }
            $openAireCallArrayResp = self::callOpenAireApi($client, $doi);
            try {
                $decodeOpenAireResp = json_decode($openAireCallArrayResp, true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $sets->set(json_encode($decodeOpenAireResp,JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                $cache->save($sets);
            }catch (JsonException $e) {
                $eMsg = $e->getMessage() . " for PAPER " . $paperId . ' URL called https://api.openaire.eu/search/publications?doi=' . $doi . '&format=json ';
                if (PHP_SAPI === 'cli') {
                    echo PHP_EOL. $eMsg .PHP_EOL;
                }
                // OPENAIRE CAN RETURN MALFORMED JSON SO WE LOG URL OPENAIRE
                self::logErrorMsg($eMsg);
                $sets->set(json_encode([""], JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                $cache->save($sets);
            }
        }
    }

    /**
     * @param Client $client
     * @param $doi
     * @return string
     */
    public static function callOpenAireApi(Client $client, $doi): string
    {

        $openAireCallArrayResp = '';

        try {

            return $client->get('https://api.openaire.eu/search/publications?doi=' . $doi . '&format=json', [
                'headers' => [
                    'User-Agent' => 'CCSD Episciences support@episciences.org',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ])->getBody()->getContents();

        } catch (GuzzleException $e) {

            trigger_error($e->getMessage());

        }
        sleep(1);
        return $openAireCallArrayResp;
    }

    public static function logErrorMsg($msg): void
    {
        $logger = new Logger('openaire_researchgraph_tools');
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'openAireResearchGraph_'.date('Y-m-d').'.log', Logger::INFO));

        $logger->info($msg);
    }
    /**
     * @param string $doiTrim
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getsGlobalOARGCache(string $doiTrim)
    {
        ////// CACHE GLOBAL RESEARCH GRAPH
        $fileOpenAireGlobalResponse = trim(explode("/", $doiTrim)[1]) . ".json";
        $cacheOARG = new FilesystemAdapter('openAireResearchGraph', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        return $cacheOARG->getItem($fileOpenAireGlobalResponse);
    }

    /**
     * @param string $doiTrim
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getCreatorCacheOA(string $doiTrim): array
    {
        ////// CACHE CREATOR ONLY
        $cacheCreator = new FilesystemAdapter('enrichmentAuthors', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $pathOpenAireCreator = trim(explode("/", $doiTrim)[1]) . "_creator.json";
        $setsOpenAireCreator = $cacheCreator->getItem($pathOpenAireCreator);
        return array($cacheCreator, $pathOpenAireCreator, $setsOpenAireCreator);
    }

    /**
     * @param $setsOpenAireCreator
     * @param $paperId
     * @return int
     * @throws JsonException
     */
    public static function insertOrcidAuthorFromOARG($setsOpenAireCreator, $paperId): int
    {
        $affectedRow = 0;
        if ($setsOpenAireCreator->isHit()){
            return $affectedRow;
        }
        try {
            $fileFound = json_decode($setsOpenAireCreator->get(), true, 512, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (empty($fileFound) || $fileFound === [""]) {
                return $affectedRow;
            }
            $reformatFileFound = [];
            if (!array_key_exists(0, $fileFound)) {
                $reformatFileFound[] = $fileFound;
            } else {
                $reformatFileFound = $fileFound;
            }
            $selectAuthor = Episciences_Paper_AuthorsManager::getAuthorByPaperId($paperId);
            foreach ($selectAuthor as $key => $authorInfo) {
                // LOOP IN ARRAY FROM DB
                $decodeAuthor = json_decode($authorInfo['authors'], true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $originalAuthorsArray = $decodeAuthor;
                $recordUpdated = false;
                // WE NEED TO DECODE JSON IN DB TO LOOP IN
                foreach ($decodeAuthor as $authorIndex => $singleAuthor) {
                    $authorFullName = $singleAuthor['fullname'] ?? '';
                    if (empty($authorFullName)) {
                        continue;
                    }
                    $foundOrcid = self::findOrcidForAuthor($authorFullName, $reformatFileFound, $authorIndex);
                    if ($foundOrcid && empty($singleAuthor['orcid'])) {
                        $decodeAuthor[$authorIndex]['orcid'] = Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($foundOrcid);
                        $recordUpdated = true;
                        if (PHP_SAPI === 'cli') {
                            echo PHP_EOL . "Added ORCID $foundOrcid for author $authorFullName (record $key, paper $paperId)" . PHP_EOL;
                        }
                    }
                    }
                if ($recordUpdated && $decodeAuthor  !== $originalAuthorsArray) {
                    self::insertAuthors($decodeAuthor, $paperId, $key);
                    $affectedRow++;
                }
            }
        } catch (JsonException $e) {
            self::logErrorMsg("JSON decode error in insertOrcidAuthorFromOARG: " . $e->getMessage());
        }

        return $affectedRow;
    }

    /**
     * Find ORCID for a specific author from API data
     *
     * This method searches through the OpenAire Research Graph API data
     * to find a matching ORCID for the given author name.
     *
     * @param string $needleFullName The author's full name from database
     * @param array $reformatFileFound The formatted API data array
     * @param int $authorIndex The index of the author being processed
     * @return string|null The ORCID if found, null otherwise
     */
    private static function findOrcidForAuthor(string $needleFullName, array $reformatFileFound, int $authorIndex): ?string
    {
        // Loop through each author record from the API
        foreach ($reformatFileFound as $authorInfoFromApi) {
            // Initialize match flag
            $isMatch = false;

            // see if the author name appears anywhere in the API data
            if (array_search($needleFullName, $authorInfoFromApi, false) !== false ||
                array_search(Episciences_Tools::replaceAccents($needleFullName), $authorInfoFromApi, false)) {
                $isMatch = true;
            }
            elseif (isset($authorInfoFromApi['$']) &&
                Episciences_Tools::replaceAccents($needleFullName) === Episciences_Tools::replaceAccents($authorInfoFromApi['$'])) {
                $isMatch = true;
            }

            // If found a match, process the ORCID
            if ($isMatch) {
                // Log the successful match (using original log format)
                $msgLogAuthorFound = "Author Found \n Searching :\n" . print_r($needleFullName, true) .
                    "\n API: \n" . print_r($authorInfoFromApi, true);
                self::logErrorMsg($msgLogAuthorFound);

                // Extract and return the ORCID if it exists
                if (array_key_exists("@orcid", $authorInfoFromApi)) {
                    $orcid = Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($authorInfoFromApi['@orcid']);
                    self::logErrorMsg("ORCID found: $orcid for author: $needleFullName");
                    return $orcid;
                }
            } else {
                // Log when no match is found
                $apiName = $authorInfoFromApi['$'] ?? 'UNKNOWN';
                self::logErrorMsg("No matching : API " . $apiName . " #DB# " . $needleFullName);
            }
        }

        // Log when no ORCID is found for this author
        self::logErrorMsg("ORCID not found for author: " . $needleFullName);
        return null;
    }
    /**
     * @param $decodeOpenAireResp
     * @param $doi
     * @return void
     * @throws JsonException
     */
    public static function putCreatorInCache($decodeOpenAireResp, $doi): void
    {
        $cache = new FilesystemAdapter('enrichmentAuthors', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $fileName = trim(explode("/", $doi)[1]) . "_creator.json";
        $sets = $cache->getItem($fileName);
        $sets->expiresAfter(self::ONE_MONTH);
        if ($decodeOpenAireResp !== [""] && !is_null($decodeOpenAireResp) && !empty($decodeOpenAireResp['response']['results'])) {
            if (array_key_exists('result', $decodeOpenAireResp['response']['results'])) {
                $creatorArrayOpenAire = $decodeOpenAireResp['response']['results']['result'][0]['metadata']['oaf:entity']['oaf:result']['creator'];
                $sets->set(json_encode($creatorArrayOpenAire, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                $cache->save($sets);
            }
        } else {
            $sets->set(json_encode([""]));
            $cache->save($sets);
        }
    }

    /**
     * @param $decodeAuthor
     * @param $paperId
     * @param $key
     * @return int
     */
    public static function insertAuthors($decodeAuthor, $paperId, $key): int
    {
        $newAuthorInfos = new Episciences_Paper_Authors();
        $newAuthorInfos->setAuthors(json_encode($decodeAuthor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT));
        $newAuthorInfos->setPaperId($paperId);
        $newAuthorInfos->setAuthorsId($key);
        return Episciences_Paper_AuthorsManager::update($newAuthorInfos);
    }
    /**
     * @param $needleFullName
     * @param $authorInfoFromApi
     * @param $decodeAuthor
     * @param $keyDbJson
     * @param int $flagNewOrcid
     * @return array
     */
    public static function getOrcidApiForDb($needleFullName, $authorInfoFromApi, $decodeAuthor, $keyDbJson, int $flagNewOrcid): array
    {
        /*
         * FIRST IF PRETTY RAW SEARCHING
         * SECOND IF REPLACE ALL ACCENT IN BOTH FULLNAME
         */
        $msgLogAuthorFound = "Author Found \n Searching :\n" . print_r($needleFullName, true) . "\n API: \n" . print_r($authorInfoFromApi, true) . " DB DATA:\n " . print_r($decodeAuthor, true);
        if (array_search($needleFullName, $authorInfoFromApi, false) !== false || array_search(Episciences_Tools::replaceAccents($needleFullName), $authorInfoFromApi, false)) {
            self::logErrorMsg($msgLogAuthorFound);
            if (array_key_exists("@orcid", $authorInfoFromApi) && !isset($decodeAuthor[$keyDbJson]['orcid'])) {
                $decodeAuthor[$keyDbJson]['orcid'] = Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($authorInfoFromApi['@orcid']);
                $flagNewOrcid = 1;
            }

        } elseif (Episciences_Tools::replaceAccents($needleFullName) === Episciences_Tools::replaceAccents($authorInfoFromApi['$'])) {
            self::logErrorMsg($msgLogAuthorFound);
            if (array_key_exists("@orcid", $authorInfoFromApi)) {
                $decodeAuthor[$keyDbJson]['orcid'] = Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($authorInfoFromApi['@orcid']);
                $flagNewOrcid = 1;
            }
        } else {
            self::logErrorMsg("No matching : API " . $authorInfoFromApi['$'] . " #DB# " . $needleFullName);
        }
        //SOME LOGGING TO KNOW IF OCCURENCE WAS FOUND EACH LOOP OF ARRAYS
        if (!isset($decodeAuthor[$keyDbJson]['orcid'])) {
            self::logErrorMsg("ORCID not found \n Searching :\n" . print_r($needleFullName, true) . "\n API: \n" . print_r($authorInfoFromApi, true) . " DB DATA:\n " . print_r($decodeAuthor, true));
        }
        if ($flagNewOrcid === 1) {
            self::logErrorMsg("ORCID found \n Searching :\n" . print_r($needleFullName, true) . "\n API: \n" . print_r($authorInfoFromApi, true) . " DB DATA:\n " . print_r($decodeAuthor, true));

        }
        return [$decodeAuthor, $flagNewOrcid];
    }

    public static function putFundingsInCache($decodeOpenAireResp, $doi): void
    {
        $cache = new FilesystemAdapter('enrichmentFunding', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $fileName = trim(explode("/", $doi)[1]) . "_funding.json";
        $sets = $cache->getItem($fileName);
        if ($decodeOpenAireResp !== [""] && !is_null($decodeOpenAireResp) && !is_null($decodeOpenAireResp['response']['results'])) {
            if (array_key_exists('result', $decodeOpenAireResp['response']['results'])) {
                $preFundingArrayOpenAire = $decodeOpenAireResp['response']['results']['result'][0]['metadata']['oaf:entity']['oaf:result'];
                if (array_key_exists('rels',$preFundingArrayOpenAire)) {
                    if (!empty($preFundingArrayOpenAire['rels']) && array_key_exists('rel',$preFundingArrayOpenAire['rels'])){
                        $arrayFunding = $preFundingArrayOpenAire['rels']['rel'];
                        $sets->set(json_encode($arrayFunding,JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                        $cache->save($sets);
                    }else{
                        $sets->set(json_encode([""]));
                        $cache->save($sets);
                    }
                } else {
                    $sets->set(json_encode([""]));
                    $cache->save($sets);
                }
            }
        } else {
            $sets->set(json_encode([""]));
            $cache->save($sets);
        }
    }
    /**
     * @param string $doiTrim
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getFundingCacheOA(string $doiTrim): array
    {
        $cacheFundingOA = new FilesystemAdapter('enrichmentFunding', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $pathOpenAireFunding = trim(explode("/", $doiTrim)[1]) . "_funding.json";
        $sets = $cacheFundingOA->getItem($pathOpenAireFunding);
        $sets->expiresAfter(self::ONE_MONTH);
        return array($cacheFundingOA, $pathOpenAireFunding, $sets);
    }

}
