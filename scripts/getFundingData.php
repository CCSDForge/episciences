<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;


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
        $select = $db->select()->from(T_PAPERS, ['PAPERID', 'DOI', 'IDENTIFIER', 'VERSION', 'REPOID', 'STATUS'])->order('REPOID DESC'); // prevent empty row
        $cache = new FilesystemAdapter('enrichmentFunding', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        foreach ($db->fetchAll($select) as $value) {
            if (isset($value['DOI']) && $value['DOI'] !== '' && $value['STATUS'] === (string)Episciences_Paper::STATUS_PUBLISHED) {
                $doiTrim = trim($value['DOI']);
                // CHECK IF GLOBAL OPENAIRE RESEARCH GRAPH EXIST
                Episciences_OpenAireResearchGraphTools::checkOpenAireGlobalInfoByDoi(trim($value['DOI']), $value['PAPERID']);
                $fileOpenAireGlobalResponse = trim(explode("/", trim($value['DOI']))[1]) . ".json";
                $cacheOARG = new FilesystemAdapter('openAireResearchGraph', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
                $setsGlobalOARG = $cacheOARG->getItem($fileOpenAireGlobalResponse);
                // cache system only for fundings
                list($cache, $pathOpenAireFunding, $setOAFunding) = Episciences_OpenAireResearchGraphTools::getFundingCacheOA($doiTrim);
                //////////////////////////////////////
                if ($setsGlobalOARG->isHit() && !$setOAFunding->isHit()) {
                    echo PHP_EOL. $pathOpenAireFunding. PHP_EOL;
                    // WE PUT EMPTY ARRAY IF RESPONSE IS NOT OK
                    try {
                        $decodeOpenAireResp = json_decode($setsGlobalOARG->get(), true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                        Episciences_OpenAireResearchGraphTools::putFundingsInCache($decodeOpenAireResp, $doiTrim);
                        //create cache with the global cache of OpenAire Research Graph created or not before -> ("checkOpenAireGlobalInfoByDoi")
                        $this->displayInfo('Create Cache from Global openAireResearchGraph cache file for ' . trim($value['DOI']), true);
                    } catch (JsonException $e) {
                        // OPENAIRE CAN RETURN MALFORMED JSON SO WE LOG URL OPENAIRE
                        self::logErrorMsg($e->getMessage() . ' URL called https://api.openaire.eu/search/publications/?doi=' . $doiTrim . '&format=json');
                        $setOAFunding->set(json_encode([""]));
                        $cache->save($setOAFunding);
                    }
                    sleep('1');
                }
                list($cache, $pathOpenAireFunding, $setOAFunding) = Episciences_OpenAireResearchGraphTools::getFundingCacheOA($doiTrim);
                try {
                    $fileFound = json_decode($setOAFunding->get(), true, 512, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                } catch (JsonException $jsonException) {
                    self::logErrorMsg(sprintf('Error Code %s / Error Message %s', $jsonException->getCode(), $jsonException->getMessage()));
                }

                $globalfundingArray = [];
                $this->displayInfo('CALL CACHE OPENAIRE FOR ' . $doiTrim, true);
                if (!empty($fileFound[0])) {
                    $fundingArray = [];
                    $globalfundingArray = Episciences_Paper_ProjectsManager::formatFundingOAForDB($fileFound, $fundingArray, $globalfundingArray);
                    $rowInDBGraph = Episciences_Paper_ProjectsManager::getProjectsByPaperIdAndSourceId($value['PAPERID'],Episciences_Repositories::GRAPH_OPENAIRE_ID);
                    Episciences_Paper_ProjectsManager::insertOrUpdateFundingOA($globalfundingArray, $rowInDBGraph, (int) $value['PAPERID']);
                }
            }
            // add extra funding for hal identifier
            if (($value['REPOID'] === Episciences_Repositories::HAL_REPO_ID) && !is_null(trim($value['IDENTIFIER']))) {
                $trimIdentifier = trim($value['IDENTIFIER']);
                $this->displayInfo('CALL HAL '. $trimIdentifier , true);
                $arrayIdEuAnr =  Episciences_Paper_ProjectsManager::CallHAlApiForIdEuAndAnrFunding($trimIdentifier,$value["VERSION"]);
                $decodeHalIdsResp = json_decode($arrayIdEuAnr, true, 512, JSON_THROW_ON_ERROR);
                $globalArrayJson = [];
                if (!empty($decodeHalIdsResp['response']['docs'])) {
                    $globalArrayJson = Episciences_Paper_ProjectsManager::FormatFundingANREuToArray($decodeHalIdsResp['response']['docs'], $trimIdentifier, $globalArrayJson);
                }
                $mergeArrayANREU = [];
                if (!empty($globalArrayJson)) {
                    foreach ($globalArrayJson as $globalPreJson) {
                        $mergeArrayANREU[] = $globalPreJson[0];
                    }
                    $rowInDbHal = Episciences_Paper_ProjectsManager::getProjectsByPaperIdAndSourceId($value['PAPERID'],Episciences_Repositories::HAL_REPO_ID);
                    Episciences_Paper_ProjectsManager::insertOrUpdateHalFunding($rowInDbHal, $mergeArrayANREU, $value['PAPERID']);

                    if (empty($mergeArrayANREU)) {
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

    public static function logErrorMsg($msg)
    {
        $logger = new Logger('my_logger');
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'fundingEnrichment_' . date('Y-m-d') . '.log', Logger::INFO));
        $logger->info($msg);
    }
}


$script = new getFundingData($localopts);
$script->run();
