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
    public static function setsGlobalOARGCache(string $doiTrim)
    {
        ////// CACHE GLOBAL RESEARCH GRAPH
        $fileOpenAireGlobalResponse = trim(explode("/", $doiTrim)[1]) . ".json";
        $cacheOARG = new FilesystemAdapter('openAireResearchGraph', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $setsGlobalOARG = $cacheOARG->getItem($fileOpenAireGlobalResponse);
        return $setsGlobalOARG;
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
     * @return void
     * @throws JsonException
     */
    public static function insertOrcidAuthorFromOARG($setsOpenAireCreator, $paperId): void
    {
        if ($setsOpenAireCreator->isHit() && !empty($fileFound = json_decode($setsOpenAireCreator->get(), true, 512, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) && $fileFound !== [""]) {
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
                // WE NEED TO DECODE JSON IN DB TO LOOP IN
                foreach ($decodeAuthor as $keyDbJson => $authorFromDB) {
                    $needleFullName = $authorFromDB['fullname'];
                    $flagNewOrcid = 0;
                    // GET EACH FULLNAME TO COMPARE IN THE API ARRAY
                    foreach ($reformatFileFound as $authorInfoFromApi) {
                        // TRY TO FIND CORRESPONDING AUTHOR AND ORCID (IF EXIST)
                        [$decodeAuthor, $flagNewOrcid] = self::getOrcidApiForDb($needleFullName, $authorInfoFromApi, $decodeAuthor, $keyDbJson, $flagNewOrcid);
                    }
                    if ($flagNewOrcid === 1) {
                        self::insertAuthors($decodeAuthor, $paperId, $key);
                        if (PHP_SAPI ==='cli') {
                            echo PHP_EOL . 'new Orcid for id ' . $key . ' and paper ' . $paperId . PHP_EOL;
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $decodeOpenAireResp
     * @param $doi
     * @return void
     * @throws JsonException
     */
    public static function putInFileResponseOpenAireCall($decodeOpenAireResp, $doi): void
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
     * @return void
     */
    public static function insertAuthors($decodeAuthor, $paperId, $key): void
    {
        $newAuthorInfos = new Episciences_Paper_Authors();
        $newAuthorInfos->setAuthors(json_encode($decodeAuthor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT));
        $newAuthorInfos->setPaperId($paperId);
        $newAuthorInfos->setAuthorsId($key);
        Episciences_Paper_AuthorsManager::update($newAuthorInfos);
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
        if (array_search($needleFullName, $authorInfoFromApi, false) !== false || array_search(Episciences_Tools::replace_accents($needleFullName), $authorInfoFromApi, false)) {
            self::logErrorMsg($msgLogAuthorFound);
            if (array_key_exists("@orcid", $authorInfoFromApi) && !isset($decodeAuthor[$keyDbJson]['orcid'])) {
                $decodeAuthor[$keyDbJson]['orcid'] = Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($authorInfoFromApi['@orcid']);
                $flagNewOrcid = 1;
            }

        } elseif (Episciences_Tools::replace_accents($needleFullName) === Episciences_Tools::replace_accents($authorInfoFromApi['$'])) {
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

}
