<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;


$localopts = [
    'dry-run' => 'Work with Test API',
];


if (file_exists(__DIR__ . "/loadHeader.php")) {
    require_once __DIR__ . '/loadHeader.php';
} else {
    require_once 'loadHeader.php';
}
require_once "JournalScript.php";

class getFundingData extends JournalScript
{
    public const DIR = '../data';

    public const ONE_MONTH = 3600 * 24 * 31;

    /**
     * @var bool
     */
    protected bool $_dryRun = true;

    /**
     * getDoi constructor.
     * @param $localopts
     */


    public function __construct($localopts)
    {

        // missing required parameters will be asked later
        $this->setRequiredParams([]);
        $this->setArgs(array_merge($this->getArgs(), $localopts));
        parent::__construct();

        if ($this->getParam('dry-run')) {
            $this->setDryRun(true);
        } else {
            $this->setDryRun(false);
        }
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function run(): void
    {
        $this->initApp();
        $this->initDb();
        $this->initTranslator();
        define_review_constants();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(T_PAPERS, ['PAPERID', 'DOI','IDENTIFIER','VERSION','REPOID','STATUS'])->order('REPOID DESC'); // prevent empty row
        $cache = new FilesystemAdapter('enrichmentFunding', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        foreach ($db->fetchAll($select) as $value) {
            if (isset($value['DOI']) && $value['DOI'] !== '' && $value['STATUS'] === (string) Episciences_Paper::STATUS_PUBLISHED) {
                $doiTrim = trim($value['DOI']);
                // CHECK IF GLOBAL OPENAIRE RESEARCH GRAPH EXIST
                Episciences_OpenAireResearchGraphTools::checkOpenAireGlobalInfoByDoi(trim($value['DOI']),$value['PAPERID']);
                $fileOpenAireGlobalResponse = trim(explode("/", trim($value['DOI']))[1]) . ".json";
                $cacheOARG = new FilesystemAdapter('openAireResearchGraph', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
                $setsGlobalOARG = $cacheOARG->getItem($fileOpenAireGlobalResponse);
                // cache system only for fundings
                $fileName = trim(explode("/", $doiTrim)[1]) . "_funding.json";
                $sets = $cache->getItem($fileName);
                $sets->expiresAfter(self::ONE_MONTH);
                //////////////////////////////////////
                if ($setsGlobalOARG->isHit() && !$sets->isHit()) {
                    echo PHP_EOL. $fileName. PHP_EOL;
                    // WE PUT EMPTY ARRAY IF RESPONSE IS NOT OK
                    try {
                        $decodeOpenAireResp = json_decode($setsGlobalOARG->get(), true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                        $this->putInFileResponseOpenAireCall($decodeOpenAireResp, $doiTrim);
                        //create cache with the global cache of OpenAire Research Graph created or not before -> ("checkOpenAireGlobalInfoByDoi")
                        $this->displayInfo('Create Cache from Global openAireResearchGraph cache file for ' . trim($value['DOI']), true);
                    } catch (JsonException $e) {
                        // OPENAIRE CAN RETURN MALFORMED JSON SO WE LOG URL OPENAIRE
                        self::logErrorMsg($e->getMessage() . ' URL called https://api.openaire.eu/search/publications/?doi=' . $doiTrim . '&format=json');
                        $sets->set(json_encode([""]));
                        $cache->save($sets);
                    }
                    sleep('1');
                }
                $cache = new FilesystemAdapter('enrichmentFunding', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
                $sets = $cache->getItem($fileName);
                $sets->expiresAfter(self::ONE_MONTH);
                $fileFound = json_decode($sets->get(), true, 512, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $globalfundingArray = [];
                $this->displayInfo('CALL CACHE OPENAIRE FOR '. $doiTrim , true);
                if (!empty($fileFound[0])) {
                    $fundingArray = [];
                    foreach ($fileFound as $openAireKey => $valueOpenAire){
                        if(array_key_exists('to', $valueOpenAire) && array_key_exists('@type', $valueOpenAire['to']) && $valueOpenAire['to']['@type'] === "project") {
                            if (array_key_exists('title',$valueOpenAire)){
                                $fundingArray['projectTitle'] = $valueOpenAire['title']['$'];
                            }
                            if (array_key_exists('acronym',$valueOpenAire)){
                                $fundingArray['acronym'] = $valueOpenAire['acronym']['$'];
                            }
                            if (array_key_exists('funder',$valueOpenAire['funding'])){
                                $fundingArray['funderName']  = $valueOpenAire['funding']['funder']['@name'];
                            }
                            if (array_key_exists('code',$valueOpenAire)){
                                $fundingArray['code']  = $valueOpenAire['code']['$'];
                            }

                            $globalfundingArray[] = $fundingArray;

                        }
                    }
                    $rowInDBGraph = Episciences_Paper_ProjectsManager::getProjectsByPaperIdAndSourceId($value['PAPERID'],Episciences_Repositories::GRAPH_OPENAIRE_ID);
                    if (!empty($globalfundingArray) && empty($rowInDBGraph)){
                        Episciences_Paper_ProjectsManager::insert(
                            [
                                'funding'=>json_encode($globalfundingArray,JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                                'paperId'=> $value['PAPERID'],
                                'source_id' => Episciences_Repositories::GRAPH_OPENAIRE_ID
                            ]
                        );
                        $this->displayInfo('Project Founded '.json_encode($globalfundingArray,JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), true);
                    }elseif(!empty($globalfundingArray) && !empty($rowInDBGraph)){
                        Episciences_Paper_ProjectsManager::update(
                            [
                                'funding'=>json_encode($globalfundingArray,JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                                'paperId'=> $value['PAPERID'],
                                'source_id' => Episciences_Repositories::GRAPH_OPENAIRE_ID
                            ]
                        );
                    }
                }
            }
            // add extra funding for hal identifier
            if (($value['REPOID'] === Episciences_Repositories::HAL_REPO_ID) && !is_null(trim($value['IDENTIFIER']))) {
                $trimIdentifier = trim($value['IDENTIFIER']);
                $cache = new FilesystemAdapter('enrichmentFunding', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
                $this->displayInfo('CALL HAL '. $trimIdentifier , true);
                $arrayIdEuAnr =  self::CallHAlApiForIdEuAndAnrFunding($trimIdentifier,$value["VERSION"]);
                $decodeHalIdsResp = json_decode($arrayIdEuAnr, true, 512, JSON_THROW_ON_ERROR);
                $globalArrayJson = [];
                if (!empty($decodeHalIdsResp['response']['docs'])) {
                    foreach ($decodeHalIdsResp['response']['docs'] as $halValue) {
                        if (isset($halValue['europeanProjectId_i'])) {

                            foreach ($halValue['europeanProjectId_i'] as $idEuro) {
                                $this->displayInfo('Project EUROPEAN ON HAL FOUNDED '.$idEuro, true);
                                $fileNameEuro = $trimIdentifier.'_'.$idEuro. "_EU_funding.json";
                                $setsEU = $cache->getItem($fileNameEuro);
                                $setsEU->expiresAfter(self::ONE_MONTH);
                                if (!$setsEU->isHit()) {
                                    $halEuroResp = self::CallHAlApiForEuroProject($idEuro);
                                    $setsEU->set($halEuroResp);
                                    $cache->save($setsEU);
                                    $globalArrayJson[] = self::formatEuHalResp(json_decode($halEuroResp, true, 512, JSON_THROW_ON_ERROR));
                                } else {
                                    $globalArrayJson[] = self::formatEuHalResp(json_decode($setsEU->get(), true, 512, JSON_THROW_ON_ERROR));
                                }
                            }
                        }
                        if (isset($halValue['anrProjectId_i'])) {
                            foreach ($halValue['anrProjectId_i'] as $idAnr) {
                                $this->displayInfo('Project ANR ON HAL FOUNDED '.$idAnr, true);
                                $fileNameAnr = $trimIdentifier.'_'.$idAnr. "_ANR_funding.json";
                                $setsANR = $cache->getItem($fileNameAnr);
                                $setsANR->expiresAfter(self::ONE_MONTH);
                                if (!$setsANR->isHit()) {
                                    $halAnrResp = self::CallHAlApiForAnrProject($idAnr);
                                    $this->displayInfo('Project ANR ON HAL FOUNDED '.$idAnr, true);
                                    $setsANR->set($halAnrResp);
                                    $cache->save($setsANR);
                                    $globalArrayJson[] = self::formatAnrHalResp(json_decode($halAnrResp, true, 512, JSON_THROW_ON_ERROR));
                                } else {
                                    $this->displayInfo('Project ANR IN CACHE FOUNDED', true);
                                    $globalArrayJson[] = self::formatAnrHalResp(json_decode($setsANR->get(), true, 512, JSON_THROW_ON_ERROR));
                                }
                            }
                        }
                    }
                }
                $mergeArrayANREU = [];
                if (!empty($globalArrayJson)) {
                    foreach ($globalArrayJson as $globalPreJson) {
                        $mergeArrayANREU[] = $globalPreJson[0];
                    }
                    $rowInDbHal = Episciences_Paper_ProjectsManager::getProjectsByPaperIdAndSourceId($value['PAPERID'],Episciences_Repositories::HAL_REPO_ID);
                    if (!empty($rowInDbHal) && !empty($mergeArrayANREU)) {
                        Episciences_Paper_ProjectsManager::update(
                            [
                                'funding' => json_encode($mergeArrayANREU, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                                'paperId' => $value['PAPERID'],
                                'source_id' => Episciences_Repositories::HAL_REPO_ID
                            ]
                        );
                        $this->displayInfo('HAL PROJECT UPDATED', true);
                    } elseif (!empty($mergeArrayANREU) && empty($rowInDbHal)) {
                        Episciences_Paper_ProjectsManager::insert(
                            [
                                'funding' => json_encode($mergeArrayANREU, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                                'paperId' => $value['PAPERID'],
                                'source_id' => Episciences_Repositories::HAL_REPO_ID
                            ]
                        );
                        $this->displayInfo('NEW HAL PROJECT INSERTED', true);
                    }

                    if (empty($mergeArrayANREU)){
                        $this->displayInfo('NO INFO FOUND FOR '.$trimIdentifier, true);
                    }
                }
            }
        }
        $this->displayInfo('Funding Data Enrichment completed. Good Bye ! =)', true);
    }

    /**
     * @return bool
     */
    public
    function isDryRun(): bool
    {
        return $this->_dryRun;
    }

    /**
     * @param bool $dryRun
     */
    public
    function setDryRun(bool $dryRun)
    {
        $this->_dryRun = $dryRun;
    }

    public function putInFileResponseOpenAireCall($decodeOpenAireResp, $doi): void
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
    public static function logErrorMsg($msg)
    {
        $logger = new Logger('my_logger');
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'fundingEnrichment_'.date('Y-m-d').'.log', Logger::INFO));
        $logger->info($msg);
    }
    public static function CallHAlApiForIdEuAndAnrFunding($identifier,$version) {
        $client = new Client();
        $halCallArrayResp = '';
        $url = "https://api.archives-ouvertes.fr/search/?q=((halId_s:" . $identifier . " OR halIdSameAs_s:" . $identifier . ") AND version_i:" . $version . ")&fl=europeanProjectId_i,anrProjectId_i";
        try {
            return $client->get($url, [
                'headers' => [
                    'User-Agent' => 'CCSD Episciences support@episciences.org',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ])->getBody()->getContents();

        } catch (GuzzleException $e) {

            trigger_error($e->getMessage());

        }
        return $halCallArrayResp;
    }

    public static function CallHAlApiForEuroProject($halDocId){


        $client = new Client();
        $halCallArrayResp = '';
        $url = "https://api.archives-ouvertes.fr/ref/europeanproject/?q=docid:".$halDocId."&fl=projectTitle:title_s,acronym:acronym_s,code:reference_s,callId:callId_s,projectFinancing:financing_s";
        try {
            return $client->get($url, [
                'headers' => [
                    'User-Agent' => 'CCSD Episciences support@episciences.org',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ])->getBody()->getContents();

        } catch (GuzzleException $e) {

            trigger_error($e->getMessage());

        }
        return $halCallArrayResp;
    }

    public static function CallHAlApiForAnrProject($halDocId){


        $client = new Client();
        $halCallArrayResp = '';
        $url = "https://api.archives-ouvertes.fr/ref/anrproject/?q=docid:".$halDocId."&fl=projectTitle:title_s,acronym:acronym_s,code:reference_s";
        try {
            return $client->get($url, [
                'headers' => [
                    'User-Agent' => 'CCSD Episciences support@episciences.org',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ])->getBody()->getContents();

        } catch (GuzzleException $e) {

            trigger_error($e->getMessage());

        }
        return $halCallArrayResp;
    }



    public static function formatEuHalResp($respEuHAl){

        $arrayAllValuesExpected = [
            'projectTitle'=>'unidentified',
            'acronym'=>'unidentified',
            'funderName'=>'European Commission',
            'code'=>'unidentified',
            'callId'=>'unidentified',
            'projectFinancing'=>'unidentified'
        ];
        $arrayEuropean = [];
        if (!empty($respEuHAl['response']['docs'])){
            $i = 0;
            foreach ($respEuHAl['response']['docs'] as $key => $value){
                $arrayEuropean[$key] = $value;
                //add unidentified to all key not founded
                if (!empty(array_diff_key($arrayAllValuesExpected, $value))) {
                    foreach (array_diff_key($arrayAllValuesExpected,$value) as $keyDiff => $valueDiff) {
                        $arrayEuropean[$i][$keyDiff] = $valueDiff;
                    }
                }
                $i++;
            }
        }
        return $arrayEuropean;
    }
    public static function formatAnrHalResp($respAnrHAl){

        $arrayAllValuesExpected = [
            'projectTitle'=>'unidentified',
            'acronym'=>'unidentified',
            'funderName'=>'French National Research Agency (ANR)',
            'code'=>'unidentified',
        ];
        $arrayAnr = [];
        if (!empty($respAnrHAl['response']['docs'])){
            $i = 0;
            foreach ($respAnrHAl['response']['docs'] as $key => $value){
                $arrayAnr[$key] = $value;
                //add unidentified to all key not founded
                if (!empty(array_diff_key($arrayAllValuesExpected, $value))) {
                    foreach (array_diff_key($arrayAllValuesExpected,$value) as $keyDiff => $valueDiff) {
                        $arrayAnr[$i][$keyDiff] = $valueDiff;
                    }
                }
                $i++;
            }
        }
        return $arrayAnr;
    }


}


$script = new getFundingData($localopts);
$script->run();
